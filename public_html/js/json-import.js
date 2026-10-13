/**
 * JSON Import - заполнение формы объявления из JSON
 *
 * Опции передаются через window.jsonImportConfig:
 *   - producerMap: { "ozone": 15, ... }
 *   - certMap: { "en b": 3, ... }
 *   - resetRatingUrl: '/advertisements/reset-rating'
 *   - importImageUrl: '/advertisements/import-image'
 *   - maxImagesPerImport: 20
 *   - csrfToken: '...'
 *   - tempId: 12345
 */

(function($) {
    'use strict';

    var config = window.jsonImportConfig || {};

    var producerMap = config.producerMap || {};
    var certMap = config.certMap || {};
    var resetRatingUrl = config.resetRatingUrl || '';
    var importImageUrl = config.importImageUrl || '';
    var MAX_IMAGES_PER_IMPORT = parseInt(config.maxImagesPerImport, 10) || 20;
    var csrfToken = config.csrfToken || '';
    var tempId = config.tempId || null;

    // Кэш опций select по типам
    var optionsCache = {
        glider: { producer: null, cert: null },
        harness: { producer: null },
        device: { producer: null }
    };

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

    function resetRating(advertisementId) {
        if (!advertisementId) return;

        var ratingContainer = document.getElementById('rating-container');
        if (!ratingContainer) return;

        var xhr = new XMLHttpRequest();
        xhr.open('POST', resetRatingUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
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
    // ИМПОРТ ИЗОБРАЖЕНИЙ ИЗ JSON
    // ============================================================

    function handleImagesImport(urls, onComplete) {
        var truncated = false;
        if (urls.length > MAX_IMAGES_PER_IMPORT) {
            urls = urls.slice(0, MAX_IMAGES_PER_IMPORT);
            truncated = true;
            console.warn('Слишком много изображений в JSON, взяты первые ' + MAX_IMAGES_PER_IMPORT);
        }

        var advertisementId = getAdvertisementId();
        var currentTempId = tempId;

        var existingCount = document.querySelectorAll('#images-container .sortable-item').length;

        function proceed(mode) {
            if (mode === 'cancel') {
                onComplete(0, 0, [], truncated);
                return;
            }

            if (mode === 'replace') {
                deleteAllExistingImages(function() {
                    importUrlsSequentially(urls, advertisementId, currentTempId, function(loaded, failed, errors) {
                        onComplete(loaded, failed, errors, truncated);
                    });
                });
            } else {
                importUrlsSequentially(urls, advertisementId, currentTempId, function(loaded, failed, errors) {
                    onComplete(loaded, failed, errors, truncated);
                });
            }
        }

        if (advertisementId && existingCount > 0) {
            showImagesModeModal(existingCount, proceed);
        } else {
            proceed('append');
        }
    }

    function importUrlsSequentially(urls, advertisementId, currentTempId, onComplete) {
        var loaded = 0;
        var failed = 0;
        var errors = [];
        var total = urls.length;

        var overlay = showProgressOverlay(total);

        function next(index) {
            if (index >= urls.length) {
                hideProgressOverlay(overlay);
                onComplete(loaded, failed, errors);
                return;
            }

            var url = urls[index];
            updateProgressOverlay(overlay, index, total, url);

            var postData = {
                url: url,
                _csrf: csrfToken
            };

            if (advertisementId) {
                postData.mode = 'update';
                postData.id = advertisementId;
            } else {
                postData.mode = 'create';
                postData.tempId = currentTempId;
            }

            $.ajax({
                url: importImageUrl,
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loaded++;
                        addImportedFileToContainer(response);
                    } else {
                        failed++;
                        errors.push(url + ' — ' + (response.error || 'ошибка'));
                    }
                },
                error: function(xhr) {
                    failed++;
                    var errMsg = 'ошибка соединения';
                    try {
                        var resp = JSON.parse(xhr.responseText);
                        if (resp && resp.error) errMsg = resp.error;
                    } catch (e) {}
                    errors.push(url + ' — ' + errMsg);
                },
                complete: function() {
                    next(index + 1);
                }
            });
        }

        next(0);
    }

    function showImagesModeModal(existingCount, callback) {
        var old = document.getElementById('images-mode-modal');
        if (old) old.remove();

        var modal = document.createElement('div');
        modal.id = 'images-mode-modal';
        modal.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9999; display: flex; align-items: center; justify-content: center;';

        var box = document.createElement('div');
        box.style.cssText = 'background: #fff; color: #333; border-radius: 8px; padding: 24px; max-width: 480px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);';

        box.innerHTML = '' +
            '<h4 style="margin-top: 0;">У объявления уже есть ' + existingCount + ' изображений</h4>' +
            '<p style="color: #666;">Что сделать с новыми изображениями из JSON?</p>' +
            '<div style="display: flex; gap: 8px; justify-content: flex-end; margin-top: 20px;">' +
                '<button type="button" class="btn btn-default" data-action="cancel">Отмена</button>' +
                '<button type="button" class="btn btn-warning" data-action="replace">Заменить</button>' +
                '<button type="button" class="btn btn-primary" data-action="append">Добавить</button>' +
            '</div>';

        modal.appendChild(box);
        document.body.appendChild(modal);

        box.addEventListener('click', function(e) {
            var action = e.target.getAttribute('data-action');
            if (!action) return;
            modal.remove();
            callback(action);
        });

        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
                callback('cancel');
            }
        });
    }

    function deleteAllExistingImages(onComplete) {
        var buttons = document.querySelectorAll('#images-container .sortable-item .delete-image-btn');
        if (buttons.length === 0) {
            onComplete();
            return;
        }

        var items = Array.prototype.slice.call(buttons);
        var index = 0;

        function next() {
            if (index >= items.length) {
                onComplete();
                return;
            }

            var $btn = $(items[index]);
            var id = $btn.data('id');
            var type = $btn.data('type');
            var url = $btn.closest('[data-delete-url]').data('delete-url');

            if (type === 'create') {
                url = url + '?tempId=' + tempId + '&index=' + id;
            } else {
                url = url + '?id=' + id;
            }

            $.ajax({
                url: url,
                type: 'POST',
                data: { _csrf: csrfToken },
                dataType: 'json',
                complete: function() {
                    $btn.closest('.sortable-item').remove();
                    index++;
                    next();
                }
            });
        }

        next();
    }

    function addImportedFileToContainer(response) {
        var $container = $('#images-container');
        var $noImagesMessage = $('#no-images-message');
        var isCreateMode = !getAdvertisementId();

        var newOrder = $container.find('.sortable-item').length;
        var displayUrl = response.thumbnailUrl;
        var isVideo = response.isVideo || false;

        var contentHtml = '';
        if (isVideo) {
            contentHtml = '' +
                '<div style="position: relative; width: 100%; height: 120px; overflow: hidden; background: #000;">' +
                    '<img src="' + displayUrl + '" alt="Video preview" style="width: 100%; height: 100%; object-fit: cover; cursor: grab;">' +
                    '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 36px; opacity: 0.85; text-shadow: 0 0 20px rgba(0,0,0,0.8); pointer-events: none;">' +
                        '<span class="glyphicon glyphicon-play"></span>' +
                    '</div>' +
                '</div>';
        } else {
            contentHtml = '<img src="' + displayUrl + '" alt="Image" class="img-responsive" style="height: 120px; width: 100%; object-fit: cover; cursor: grab;">';
        }

        var dataIdAttr = '';
        if (isCreateMode) {
            dataIdAttr = 'data-image-index="' + response.index + '"';
        } else {
            dataIdAttr = 'data-image-id="' + response.imageId + '"';
        }

        var itemHtml = '' +
            '<div class="col-md-3 col-sm-4 col-xs-6 sortable-item" ' + dataIdAttr + ' data-sort-order="' + newOrder + '">' +
                '<div class="thumbnail" style="position: relative;">' +
                    contentHtml +
                    '<div class="caption" style="padding: 5px;">' +
                        '<button type="button" class="btn btn-danger btn-sm btn-block delete-image-btn" ' +
                            'data-id="' + (isCreateMode ? response.index : response.imageId) + '" ' +
                            'data-type="' + (isCreateMode ? 'create' : 'update') + '">' +
                            '<span class="glyphicon glyphicon-trash"></span> Удалить' +
                        '</button>' +
                    '</div>' +
                    '<div class="sort-order-badge">#<span class="order-number">' + (newOrder + 1) + '</span></div>' +
                '</div>' +
            '</div>';

        $container.append(itemHtml);
        $noImagesMessage.hide();

        if (typeof ImageSortable !== 'undefined') {
            ImageSortable.updateNumbers($container);
        }
    }

    function showProgressOverlay(total) {
        var old = document.getElementById('images-progress-overlay');
        if (old) old.remove();

        var overlay = document.createElement('div');
        overlay.id = 'images-progress-overlay';
        overlay.style.cssText = 'position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;';

        overlay.innerHTML = '' +
            '<div style="background: #fff; color: #333; border-radius: 8px; padding: 24px; min-width: 320px; text-align: center;">' +
                '<h4 style="margin-top: 0;">Загрузка изображений</h4>' +
                '<p id="images-progress-text" style="margin: 12px 0;">0 из ' + total + '</p>' +
                '<div style="height: 8px; background: #eee; border-radius: 4px; overflow: hidden;">' +
                    '<div id="images-progress-bar" style="height: 100%; width: 0%; background: #007bff; transition: width 0.3s ease;"></div>' +
                '</div>' +
                '<p id="images-progress-url" style="margin: 8px 0 0 0; font-size: 12px; color: #888; word-break: break-all;"></p>' +
            '</div>';

        document.body.appendChild(overlay);
        return overlay;
    }

    function updateProgressOverlay(overlay, currentIndex, total, url) {
        var text = overlay.querySelector('#images-progress-text');
        var bar = overlay.querySelector('#images-progress-bar');
        var urlEl = overlay.querySelector('#images-progress-url');

        if (text) text.textContent = currentIndex + ' из ' + total;
        if (bar) bar.style.width = ((currentIndex / total) * 100) + '%';
        if (urlEl) urlEl.textContent = url.length > 60 ? url.substring(0, 57) + '...' : url;
    }

    function hideProgressOverlay(overlay) {
        if (overlay && overlay.parentNode) {
            overlay.remove();
        }
    }

    function showImagesErrors(errors) {
        if (!errors || errors.length === 0) return;

        var old = document.getElementById('images-import-errors');
        if (old) old.remove();

        var box = document.createElement('div');
        box.id = 'images-import-errors';
        box.className = 'alert alert-warning';
        box.style.cssText = 'margin-top: 10px; max-height: 200px; overflow-y: auto;';

        var html = '<strong>Не удалось загрузить ' + errors.length + ' изображений:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px;">';
        for (var i = 0; i < errors.length; i++) {
            html += '<li style="font-size: 13px; word-break: break-all;">' + escapeHtml(errors[i]) + '</li>';
        }
        html += '</ul>';

        box.innerHTML = html;

        var container = document.querySelector('#images-block');
        if (container) {
            container.appendChild(box);
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // ============================================================
    // СТАТУСЫ И ОШИБКИ
    // ============================================================

    function showStatus(message, type) {
        var statusEl = document.getElementById('json-import-status');
        if (!statusEl) return;
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
        var errorsEl = document.getElementById('json-import-errors');
        if (errorsEl) {
            errorsEl.textContent = message;
            errorsEl.style.display = 'block';
        }
        showStatus('❌ Ошибка импорта', 'error');
    }

    function hideError() {
        var errorsEl = document.getElementById('json-import-errors');
        if (errorsEl) {
            errorsEl.style.display = 'none';
        }
    }

    // ============================================================
    // ГЛАВНАЯ ФУНКЦИЯ ИМПОРТА
    // ============================================================

    function fillFormFromJSON(jsonData) {
        var data;
        try {
            // Нормализуем «типографские» кавычки
            var normalized = typeof jsonData === 'string'
                ? jsonData.replace(/[\u201C\u201D]/g, '"')
                : jsonData;
            data = typeof normalized === 'string' ? JSON.parse(normalized) : normalized;
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

            // 6. Импорт изображений
            if (Array.isArray(data.images) && data.images.length > 0) {
                handleImagesImport(data.images, function(loaded, failed, errors, truncated) {
                    var ignoredMsg = ignoredFields.length > 0
                        ? ' Проигнорированы поля: ' + ignoredFields.join(', ') + '.'
                        : '';

                    var imgMsg = '';
                    if (loaded > 0 || failed > 0) {
                        imgMsg = ' Загружено изображений: ' + loaded;
                        if (failed > 0) {
                            imgMsg += ', не удалось: ' + failed;
                        }
                        imgMsg += '.';
                    }

                    var truncMsg = truncated
                        ? ' Взяты первые ' + MAX_IMAGES_PER_IMPORT + ' URL-ов.'
                        : '';

                    if (failed > 0) {
                        showStatus('⚠️ Форма заполнена.' + imgMsg + truncMsg + ignoredMsg, 'warning');
                    } else {
                        showStatus('✅ Форма успешно заполнена!' + imgMsg + truncMsg, 'success');
                    }

                    if (errors.length > 0) {
                        showImagesErrors(errors);
                    }
                });
            } else {
                if (ignoredFields.length > 0) {
                    showStatus(
                        '⚠️ Форма заполнена. Проигнорированы поля (нет в форме): ' +
                        ignoredFields.join(', ') + '.',
                        'warning'
                    );
                } else {
                    showStatus('✅ Форма успешно заполнена!', 'success');
                }
            }
        }, 100);

        return true;
    }

    function getAdvertisementId() {
        var idField = document.querySelector('input[name="Advertisement[id]"]');
        if (idField && idField.value) {
            var parsed = parseInt(idField.value, 10);
            return isNaN(parsed) ? null : parsed;
        }
        return null;
    }

    // ============================================================
    // ИНИЦИАЛИЗАЦИЯ
    // ============================================================

    $(document).ready(function() {
        var importBtn = document.getElementById('json-import-btn');
        var clearBtn = document.getElementById('json-import-clear');
        var textarea = document.getElementById('json-import');

        if (!importBtn || !textarea) {
            return;
        }

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
    });

})(jQuery);