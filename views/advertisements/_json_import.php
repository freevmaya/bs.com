<?php
// FILE: .\views\advertisements\_json_import.php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 */

// Получаем списки производителей и сертификаций для маппинга
$producers = \app\models\Producer::find()->all();
$certifications = \app\models\Certification::find()->all();

$producerMap = [];
foreach ($producers as $p) {
    $producerMap[strtolower($p->name)] = $p->id;
    if ($p->short) {
        $producerMap[strtolower($p->short)] = $p->id;
    }
}
$producerMapJson = json_encode($producerMap);

$certMap = [];
foreach ($certifications as $c) {
    $certMap[strtolower($c->name)] = $c->id;
}
$certMapJson = json_encode($certMap);

// URL для сброса оценки
$resetRatingUrl = Url::to(['advertisements/reset-rating']);
?>

<div class="panel panel-default" style="margin-top: 20px;">
    <div class="panel-body">
        <div class="alert alert-info">
            <span class="glyphicon glyphicon-info-sign"></span>
            Вставьте JSON, сгенерированный AI-моделью, для автоматического заполнения всех полей объявления.
            <strong>При импорте оценка AI будет сброшена</strong>, чтобы не показывать устаревшие данные.
        </div>

        <div class="form-group">
            <label for="json-import">JSON данные объявления</label>
            <textarea id="json-import" class="form-control" rows="8" style="font-family: monospace; font-size: 13px;" placeholder='{
    "type": "harness",
    "section": "sell",
    "description": "Продаю подвеску...",
    "price": 45000,
    "currency": "RUB",
    "price_negotiable": false,
    "city": "Москва",
    "phone": "79687927864",
    "source_url": "https://example.com/product",
    "item_info_link": "https://example.com/info",
    "harness": {
        "model": "Genie Light",
        "producer_name": "Gin",
        "size": "M",
        "date_release": "2023",
        "condition": "excellent",
        "defects": ""
    }
}'></textarea>
        </div>

        <div class="form-group">
            <button type="button" class="btn btn-primary" id="json-import-btn">
                <span class="glyphicon glyphicon-play"></span> Заполнить из JSON
            </button>
            <button type="button" class="btn btn-default" id="json-import-clear">
                <span class="glyphicon glyphicon-erase"></span> Очистить
            </button>
            <span id="json-import-status" style="margin-left: 15px;"></span>
        </div>

        <div id="json-import-errors" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
    </div>
</div>

<?php
$importScript = <<<JS
(function() {
    'use strict';

    var producerMap = {$producerMapJson};
    var certMap = {$certMapJson};
    var resetRatingUrl = '{$resetRatingUrl}';

    var optionsCache = {
        glider: { producer: null, cert: null },
        harness: { producer: null },
        device: { producer: null }
    };

    var importBtn = document.getElementById('json-import-btn');
    var clearBtn = document.getElementById('json-import-clear');
    var textarea = document.getElementById('json-import');
    var statusEl = document.getElementById('json-import-status');
    var errorsEl = document.getElementById('json-import-errors');

    if (!importBtn || !textarea) {
        return;
    }

    // ============================================================
    // ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
    // ============================================================

    function getProducerSelect(type) {
        var selectId;
        switch (type) {
            case 'glider':  selectId = 'advertisementglider-producer_id'; break;
            case 'harness': selectId = 'advertisementharness-producer_id'; break;
            case 'device':  selectId = 'advertisementdevice-producer_id'; break;
            default: return null;
        }
        return document.getElementById(selectId);
    }

    function getCertSelect() {
        return document.getElementById('advertisementglider-certification_id');
    }

    function getProducerOptions(type) {
        if (optionsCache[type] && optionsCache[type].producer !== null) {
            return optionsCache[type].producer;
        }
        var select = getProducerSelect(type);
        if (!select) return [];
        var result = [];
        for (var i = 0; i < select.options.length; i++) {
            result.push({
                value: select.options[i].value,
                text: select.options[i].text.toLowerCase().trim()
            });
        }
        optionsCache[type].producer = result;
        return result;
    }

    function getCertOptions() {
        if (optionsCache.glider.cert !== null) return optionsCache.glider.cert;
        var select = getCertSelect();
        if (!select) return [];
        var result = [];
        for (var i = 0; i < select.options.length; i++) {
            result.push({
                value: select.options[i].value,
                text: select.options[i].text.toLowerCase().trim()
            });
        }
        optionsCache.glider.cert = result;
        return result;
    }

    function findProducerId(name, type) {
        if (!name) return null;
        var searchName = String(name).toLowerCase().trim();
        if (producerMap[searchName]) return producerMap[searchName];

        var keys = Object.keys(producerMap);
        for (var i = 0; i < keys.length; i++) {
            if (searchName.indexOf(keys[i]) !== -1 || keys[i].indexOf(searchName) !== -1) {
                return producerMap[keys[i]];
            }
        }

        var options = getProducerOptions(type);
        for (var i = 0; i < options.length; i++) {
            var opt = options[i];
            if (opt.text === searchName) return opt.value;
            if (opt.text.indexOf(searchName) !== -1 || searchName.indexOf(opt.text) !== -1) {
                return opt.value;
            }
        }
        return null;
    }

    function findCertId(name) {
        if (!name) return null;
        var searchName = String(name).toLowerCase().trim();
        if (certMap[searchName]) return certMap[searchName];

        var keys = Object.keys(certMap);
        for (var i = 0; i < keys.length; i++) {
            if (searchName.indexOf(keys[i]) !== -1 || keys[i].indexOf(searchName) !== -1) {
                return certMap[keys[i]];
            }
        }

        var options = getCertOptions();
        for (var i = 0; i < options.length; i++) {
            var opt = options[i];
            if (opt.text === searchName ||
                opt.text.indexOf(searchName) !== -1 ||
                searchName.indexOf(opt.text) !== -1) {
                return opt.value;
            }
        }
        return null;
    }

    function setFieldValue(fieldId, value) {
        var el = document.getElementById(fieldId);
        if (!el) return false;

        if (el.type === 'checkbox') {
            el.checked = value === true || value === 'true' || value === 1 || value === '1';
        } else if (el.tagName === 'SELECT') {
            var found = false;
            for (var i = 0; i < el.options.length; i++) {
                if (el.options[i].value == value) {
                    el.value = value;
                    found = true;
                    break;
                }
            }
            if (!found && value !== null && value !== undefined && value !== '') {
                var searchText = String(value).toLowerCase().trim();
                for (var i = 0; i < el.options.length; i++) {
                    var optText = el.options[i].text.toLowerCase().trim();
                    if (optText === searchText || optText.indexOf(searchText) !== -1) {
                        el.value = el.options[i].value;
                        found = true;
                        break;
                    }
                }
            }
        } else {
            el.value = value;
        }

        var event = new Event('change', { bubbles: true });
        el.dispatchEvent(event);
        return true;
    }

    function setIfNotEmpty(fieldId, value) {
        if (value === undefined || value === null || value === '') return false;
        return setFieldValue(fieldId, value);
    }

    // ============================================================
    // МАППИНГ ПОЛЕЙ
    // ============================================================

    var mainFieldMap = {
        'type': 'advertisement-type',
        'section': 'advertisement-section',
        'title': 'advertisement-title',
        'description': 'advertisement-description',
        'price': 'advertisement-price',
        'currency': 'advertisement-currency',
        'price_negotiable': 'advertisement-price_negotiable',
        'city': 'advertisement-city',
        'phone': 'advertisement-phone',
        'email': 'advertisement-email',
        'telegram': 'advertisement-telegram',
        'vk_profile_url': 'advertisement-vk_profile_url',
        'whatsapp': 'advertisement-whatsapp',
        'source_url': 'advertisement-source_url',
        'item_info_link': 'advertisement-item_info_link'
    };

    var gliderFieldMap = {
        'model': 'advertisementglider-model',
        'producer_id': 'advertisementglider-producer_id',
        'certification_id': 'advertisementglider-certification_id',
        'weight_min': 'advertisementglider-weight_min',
        'weight_max': 'advertisementglider-weight_max',
        'date_release': 'advertisementglider-date_release',
        'flight_time': 'advertisementglider-flight_time',
        'condition': 'advertisementglider-condition',
        'defects': 'advertisementglider-defects',
        'cause': 'advertisementglider-cause'
    };

    var harnessFieldMap = {
        'model': 'advertisementharness-model',
        'producer_id': 'advertisementharness-producer_id',
        'size': 'advertisementharness-size',
        'date_release': 'advertisementharness-date_release',
        'condition': 'advertisementharness-condition',
        'defects': 'advertisementharness-defects'
    };

    var deviceFieldMap = {
        'model': 'advertisementdevice-model',
        'producer_id': 'advertisementdevice-producer_id',
        'condition': 'advertisementdevice-condition',
        'defects': 'advertisementdevice-defects'
    };

    var ignoredFieldsByType = {
        glider: [],
        harness: [
            'certification_name', 'certification_id',
            'pilot_height_min', 'pilot_height_max',
            'weight', 'max_load', 'rucksack_volume',
            'protection', 'cause'
        ],
        device: []
    };

    var ignoredLabels = {
        'certification_name': 'Сертификация',
        'certification_id': 'ID сертификации',
        'pilot_height_min': 'Рост пилота (мин)',
        'pilot_height_max': 'Рост пилота (макс)',
        'weight': 'Вес',
        'max_load': 'Макс. нагрузка',
        'rucksack_volume': 'Объём рюкзака',
        'protection': 'Тип протектора',
        'cause': 'Причина продажи'
    };

    // ============================================================
    // ФУНКЦИИ ЗАПОЛНЕНИЯ ПО ТИПАМ
    // ============================================================

    function fillGliderFields(data) {
        if (!data) return;

        var directFields = ['model', 'weight_min', 'weight_max', 'date_release',
                           'flight_time', 'condition', 'defects', 'cause'];
        for (var i = 0; i < directFields.length; i++) {
            var key = directFields[i];
            if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                setIfNotEmpty(gliderFieldMap[key], data[key]);
            }
        }

        var producerId = null;
        if (data.producer_id) producerId = data.producer_id;
        if (!producerId && data.producer_name) {
            producerId = findProducerId(data.producer_name, 'glider');
        }
        if (!producerId && data.model) {
            var modelLower = String(data.model).toLowerCase();
            var producerKeys = Object.keys(producerMap);
            for (var i = 0; i < producerKeys.length; i++) {
                if (modelLower.indexOf(producerKeys[i]) !== -1) {
                    producerId = producerMap[producerKeys[i]];
                    break;
                }
            }
        }
        if (producerId) setFieldValue(gliderFieldMap.producer_id, producerId);

        var certId = null;
        if (data.certification_id) certId = data.certification_id;
        if (!certId && data.certification_name) {
            certId = findCertId(data.certification_name);
        }
        if (certId) setFieldValue(gliderFieldMap.certification_id, certId);
    }

    function fillHarnessFields(data) {
        if (!data) return;

        var directFields = ['model', 'size', 'date_release', 'condition', 'defects'];
        for (var i = 0; i < directFields.length; i++) {
            var key = directFields[i];
            if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                setIfNotEmpty(harnessFieldMap[key], data[key]);
            }
        }

        var producerId = null;
        if (data.producer_id) producerId = data.producer_id;
        if (!producerId && data.producer_name) {
            producerId = findProducerId(data.producer_name, 'harness');
        }
        if (!producerId && data.model) {
            var modelLower = String(data.model).toLowerCase();
            var producerKeys = Object.keys(producerMap);
            for (var i = 0; i < producerKeys.length; i++) {
                if (modelLower.indexOf(producerKeys[i]) !== -1) {
                    producerId = producerMap[producerKeys[i]];
                    break;
                }
            }
        }
        if (producerId) setFieldValue(harnessFieldMap.producer_id, producerId);
    }

    function fillDeviceFields(data) {
        if (!data) return;

        var directFields = ['model', 'condition', 'defects'];
        for (var i = 0; i < directFields.length; i++) {
            var key = directFields[i];
            if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                setIfNotEmpty(deviceFieldMap[key], data[key]);
            }
        }

        var producerId = null;
        if (data.producer_id) producerId = data.producer_id;
        if (!producerId && data.producer_name) {
            producerId = findProducerId(data.producer_name, 'device');
        }
        if (!producerId && data.model) {
            var modelLower = String(data.model).toLowerCase();
            var producerKeys = Object.keys(producerMap);
            for (var i = 0; i < producerKeys.length; i++) {
                if (modelLower.indexOf(producerKeys[i]) !== -1) {
                    producerId = producerMap[producerKeys[i]];
                    break;
                }
            }
        }
        if (producerId) setFieldValue(deviceFieldMap.producer_id, producerId);
    }

    function collectIgnoredFields(type, typeData) {
        var ignored = [];
        var ignoredList = ignoredFieldsByType[type] || [];
        if (!typeData) return ignored;

        for (var i = 0; i < ignoredList.length; i++) {
            var key = ignoredList[i];
            var value = typeData[key];
            if (value !== undefined && value !== null && value !== '') {
                var label = ignoredLabels[key] || key;
                ignored.push(label + ' (' + key + ')');
            }
        }
        return ignored;
    }

    // ============================================================
    // СБРОС ОЦЕНКИ AI
    // ============================================================

    /**
     * Сбрасывает оценку AI для текущего объявления (если это update).
     * Для create-режима ничего не делает (id ещё нет).
     */
    function resetRating(advertisementId) {
        if (!advertisementId) return;

        // Ищем контейнер с оценкой в DOM
        var ratingContainer = document.getElementById('rating-container');
        if (!ratingContainer) return; // это create-режим, оценки нет

        // Делаем AJAX-запрос на удаление оценки
        var xhr = new XMLHttpRequest();
        xhr.open('POST', resetRatingUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        var csrfToken = '';
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) csrfToken = csrfMeta.getAttribute('content');

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        // Перерисовываем контейнер оценки в исходное состояние
                        ratingContainer.innerHTML =
                            '<p class="text-muted" style="margin-bottom: 12px;">' +
                                '<span class="glyphicon glyphicon-info-sign"></span> ' +
                                'Получите объективную оценку данного снаряжения от искусственного интеллекта ' +
                                'на основе анализа цены, состояния и сравнения с другими объявлениями.' +
                            '</p>' +
                            '<button type="button" class="btn btn-primary rate-button" data-id="' + advertisementId + '">' +
                                '<span class="glyphicon glyphicon-stats"></span> Оценить' +
                            '</button>';
                    }
                } catch (e) {
                    console.warn('Failed to parse reset-rating response:', e);
                }
            }
        };

        xhr.send('id=' + encodeURIComponent(advertisementId) + '&_csrf=' + encodeURIComponent(csrfToken));
    }

    // ============================================================
    // ГЛАВНАЯ ФУНКЦИЯ ИМПОРТА
    // ============================================================

    function fillFormFromJSON(jsonData) {
        var data;
        try {
            data = typeof jsonData === 'string' ? JSON.parse(jsonData) : jsonData;
        } catch (e) {
            showError('Ошибка парсинга JSON: ' + e.message);
            return false;
        }

        hideError();

        optionsCache = {
            glider: { producer: null, cert: null },
            harness: { producer: null },
            device: { producer: null }
        };

        // 1. Основные поля
        var mainFields = Object.keys(mainFieldMap);
        for (var i = 0; i < mainFields.length; i++) {
            var key = mainFields[i];
            if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                setFieldValue(mainFieldMap[key], data[key]);
            }
        }

        // 2. Показываем нужные поля
        if (data.type) {
            var typeSelect = document.getElementById('type-select');
            if (typeSelect) {
                typeSelect.value = data.type;
                var event = new Event('change', { bubbles: true });
                typeSelect.dispatchEvent(event);
            }
        }

        // 3. Заполняем поля типа (с задержкой, чтобы DOM обновился)
        setTimeout(function() {
            var type = data.type;
            var ignoredFields = [];

            if (type === 'glider' && data.glider) {
                fillGliderFields(data.glider);
                ignoredFields = collectIgnoredFields('glider', data.glider);
            } else if (type === 'harness' && data.harness) {
                fillHarnessFields(data.harness);
                ignoredFields = collectIgnoredFields('harness', data.harness);
            } else if (type === 'device' && data.device) {
                fillDeviceFields(data.device);
                ignoredFields = collectIgnoredFields('device', data.device);
            }

            // 4. Показываем блок изображений
            if (data.section === 'sell') {
                var imagesBlock = document.getElementById('images-block');
                if (imagesBlock) {
                    imagesBlock.style.display = 'block';
                }
            }

            // 5. Сбрасываем оценку AI (только если это update)
            var advertisementId = getAdvertisementId();
            if (advertisementId) {
                resetRating(advertisementId);
            }

            // 6. Статус
            if (ignoredFields.length > 0) {
                showStatus(
                    '⚠️ Форма заполнена. Проигнорированы поля (нет в форме): ' +
                    ignoredFields.join(', ') + '.',
                    'warning'
                );
            } else {
                showStatus('✅ Форма успешно заполнена!', 'success');
            }
        }, 100);

        return true;
    }

    /**
     * Определяет ID объявления из формы (для update-режима).
     * В create-режиме возвращает null.
     */
    function getAdvertisementId() {
        // Ищем скрытое поле с id
        var idField = document.querySelector('input[name="Advertisement[id]"]');
        if (idField && idField.value) {
            var parsed = parseInt(idField.value, 10);
            return isNaN(parsed) ? null : parsed;
        }
        return null;
    }

    // ============================================================
    // СТАТУСЫ И ОШИБКИ
    // ============================================================

    function showStatus(message, type) {
        statusEl.innerHTML = message;
        statusEl.className = type || 'info';
        if (type === 'success') {
            statusEl.style.color = '#28a745';
        } else if (type === 'error') {
            statusEl.style.color = '#dc3545';
        } else if (type === 'warning') {
            statusEl.style.color = '#ffc107';
        } else {
            statusEl.style.color = '#17a2b8';
        }
    }

    function showError(message) {
        errorsEl.textContent = message;
        errorsEl.style.display = 'block';
        showStatus('❌ Ошибка импорта', 'error');
    }

    function hideError() {
        errorsEl.style.display = 'none';
    }

    // ============================================================
    // ОБРАБОТЧИКИ
    // ============================================================

    importBtn.addEventListener('click', function() {
        var jsonText = textarea.value.trim();
        if (!jsonText) {
            showError('Пожалуйста, вставьте JSON данные');
            return;
        }
        fillFormFromJSON(jsonText);
    });

    clearBtn.addEventListener('click', function() {
        textarea.value = '';
        hideError();
        showStatus('', '');
    });

    textarea.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            importBtn.click();
        }
    });
})();
JS;

$this->registerJs($importScript, \yii\web\View::POS_END);