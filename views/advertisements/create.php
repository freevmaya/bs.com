<?php
// FILE: .\views\advertisements\create.php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Advertisement;

$this->title = 'Добавить объявление';
$this->params['breadcrumbs'][] = ['label' => 'Объявления', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем CSS и JS для формы
$this->registerCssFile('@web/css/advertisement-form.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/advertisement-form.js', [
    'depends' => [\yii\web\JqueryAsset::class, \yii\jui\JuiAsset::class],
    'position' => \yii\web\View::POS_END
]);

// Передаем параметры в JS
$this->registerJs(
    'window.tempId = ' . json_encode($tempId) . ';',
    \yii\web\View::POS_BEGIN
);

$section = Yii::$app->request->get('section');
if ($section) {
    $model->section = $section;
}

// Проверяем, является ли пользователь администратором
$isAdmin = !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();
?>

<div class="advertisements-create">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-body">
                    <?php $form = ActiveForm::begin([
                        'options' => [
                            'enctype' => 'multipart/form-data',
                            'id' => 'advertisement-form'
                        ]
                    ]); ?>
                    
                    <?= $form->field($model, 'section')->dropDownList([
                        '' => 'Выберите раздел',
                        'sell' => 'Продам',
                        'buy' => 'Куплю',
                    ], ['id' => 'section-select']) ?>
                    
                    <?= $form->field($model, 'type')->dropDownList(
                        Advertisement::getTypeList(),
                        ['prompt' => 'Выберите тип снаряжения', 'id' => 'type-select']
                    ) ?>
                    
                    <!-- Поле заголовка - показываем только для normal -->
                    <div id="title-field" style="display: <?= $model->type === 'normal' ? 'block' : 'none' ?>;">
                        <?= $form->field($model, 'title')->textInput(['maxlength' => true, 'placeholder' => 'Введите заголовок объявления'])->hint('Для парапланов, подвесок и приборов заголовок генерируется автоматически') ?>
                    </div>
                    
                    <?= $form->field($model, 'description')->textarea(['rows' => 6]) ?>
                    
                    <div class="row">
                        <div class="col-md-5">
                            <?= $form->field($model, 'price')->textInput(['placeholder' => '1000']) ?>
                        </div>
                        <div class="col-md-4">
                            <?= $form->field($model, 'currency')->dropDownList(Advertisement::getCurrencyList(), ['prompt' => 'Выберите валюту']) ?>
                        </div>
                        <div class="col-md-3" style="padding-top: 32px;">
                            <?= $form->field($model, 'price_negotiable')->checkbox() ?>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- ДИНАМИЧЕСКИЕ ПОЛЯ ДЛЯ РАЗНЫХ ТИПОВ (ПЕРЕМЕЩЕНЫ ВЫШЕ) -->
                    <!-- ============================================ -->
                    <div id="glider-fields" style="display: none;">
                        <?= $this->render('_glider_fields', [
                            'form' => $form,
                            'gliderModel' => $gliderModel,
                        ]) ?>
                    </div>
                    
                    <div id="harness-fields" style="display: none;">
                        <?= $this->render('_harness_fields', [
                            'form' => $form,
                            'harnessModel' => $harnessModel,
                        ]) ?>
                    </div>
                    
                    <div id="device-fields" style="display: none;">
                        <?= $this->render('_device_fields', [
                            'form' => $form,
                            'deviceModel' => $deviceModel,
                        ]) ?>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- КОНТАКТНАЯ ИНФОРМАЦИЯ (ПЕРЕМЕЩЕНА НИЖЕ) -->
                    <!-- ============================================ -->
                    <hr>
                    <p class="text-muted"><small>Контактная информация (заполняется из профиля, но можно изменить)</small></p>
                    
                    <?= $form->field($model, 'city')->textInput(['maxlength' => true, 'placeholder' => 'Город']) ?>
                    
                    <?= $form->field($model, 'phone')->textInput(['maxlength' => true, 'placeholder' => '+7 (999) 123-45-67']) ?>
                    
                    <?= $form->field($model, 'email')->textInput(['maxlength' => true, 'placeholder' => 'email@example.com']) ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'telegram')->textInput([
                                'maxlength' => true,
                                'placeholder' => '@username или username',
                            ])->hint('Введите username в Telegram (без @ или с @)') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'whatsapp')->textInput([
                                'maxlength' => true,
                                'placeholder' => '+7 (999) 123-45-67',
                            ])->hint('Введите номер WhatsApp в международном формате') ?>
                        </div>
                    </div>
                    
                    <?= $form->field($model, 'vk_profile_url')->textInput([
                        'maxlength' => true,
                        'placeholder' => 'https://vk.com/durov',
                    ])->hint('Ссылка на профиль VK') ?>
                    
                    <!-- Поле source_url - показываем только администраторам -->
                    <?php if ($isAdmin): ?>
                        <?= $form->field($model, 'source_url')->textInput([
                            'maxlength' => true,
                            'placeholder' => 'https://example.com/original',
                        ])->hint('Ссылка на источник объявления (доступно только администраторам)') ?>
                    <?php endif; ?>
                        
                    <?= $form->field($model, 'item_info_link')->textInput([
                        'maxlength' => true,
                        'placeholder' => 'https://example.com/product-info',
                    ])->hint('Ссылка на страницу с информацией о товаре от производителя') ?>
                    
                    <div class="form-group">
                        <?= Html::submitButton('Создать объявление', ['class' => 'btn btn-success btn-lg btn-block']) ?>
                    </div>
                    
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div id="images-block" style="display: none;" data-delete-url="<?= \yii\helpers\Url::to(['advertisements/delete-temp-image-ajax']) ?>">
                <?= $this->render('_images_block', [
                    'images' => $tempImages,
                    'type' => 'create',
                    'id' => $tempId,
                ]) ?>
            </div>
        </div>

        <!-- Поле для JSON-импорта (только для админов) -->
        <?php if ($isAdmin): ?>
            <hr>
            <div class="panel panel-default">
                <div class="panel-body">
                    <div class="alert alert-info">
                        <span class="glyphicon glyphicon-info-sign"></span>
                        Вставьте JSON, сгенерированный AI-моделью, для автоматического заполнения всех полей объявления.
                    </div>
                    
                    <div class="form-group">
                        <label for="json-import">JSON данные объявления</label>
                        <textarea id="json-import" class="form-control" rows="8" style="font-family: monospace; font-size: 13px;" placeholder='{
            "type": "glider",
            "section": "sell",
            "title": "Davinci CLASSIC 2",
            "description": "Продаю крыло Davinci CLASSIC 2...",
            "price": 165000,
            "currency": "RUB",
            "price_negotiable": false,
            "city": "Москва",
            "phone": "79687927864",
            "email": "",
            "telegram": "",
            "vk_profile_url": "",
            "whatsapp": "",
            "source_url": "https://altair-aero.ru/shop/588/desc/davinci-classic-2",
            "item_info_link": "https://altair-aero.ru/shop/588/desc/davinci-classic-2",
            "glider": {
                "model": "CLASSIC 2",
                "producer_id": null,
                "producer_name": "Davinci",
                "certification_id": null,
                "certification_name": "A",
                "weight_min": 85,
                "weight_max": 105,
                "date_release": "2026",
                "flight_time": 5,
                "condition": "excellent",
                "defects": "",
                "cause": "Продаю, так как не летаю"
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
        <?php endif; ?>
    </div>
</div>

<?php
// JavaScript для показа/скрытия поля заголовка в зависимости от типа
$script = <<<JS
document.getElementById('type-select').addEventListener('change', function() {
    var titleField = document.getElementById('title-field');
    var titleInput = document.querySelector('#title-field input');
    if (this.value === 'normal') {
        titleField.style.display = 'block';
        // Если тип normal - включаем поле
        if (titleInput) {
            titleInput.disabled = false;
        }
    } else {
        titleField.style.display = 'none';
        // ПРИНУДИТЕЛЬНО ОЧИЩАЕМ И ОТКЛЮЧАЕМ ПОЛЕ ЗАГОЛОВКА, ЧТОБЫ ОН НЕ ОТПРАВЛЯЛСЯ
        if (titleInput) {
            titleInput.value = '';
            titleInput.disabled = true;
        }
    }
});

// При загрузке также применяем
document.addEventListener('DOMContentLoaded', function() {
    var typeSelect = document.getElementById('type-select');
    var event = new Event('change');
    typeSelect.dispatchEvent(event);
});
JS;
$this->registerJs($script);
?>

<?php if ($isAdmin): ?>
<?php
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

// JavaScript для импорта JSON
$importScript = <<<JS
(function() {
    'use strict';
    
    var producerMap = {$producerMapJson};
    var certMap = {$certMapJson};
    
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
    
    // Поля, которые есть в JSON, но НЕТ в форме.
    // Для каждого типа перечисляем, что игнорируется.
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
    
    // Человекочитаемые названия игнорируемых полей
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
    
    // ============================================================
    // ПРОВЕРКА ИГНОРИРУЕМЫХ ПОЛЕЙ
    // ============================================================
    
    /**
     * Собирает список игнорируемых полей для указанного типа.
     * Возвращает массив человекочитаемых названий.
     */
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
            
            // 5. Формируем статус
            if (ignoredFields.length > 0) {
                showStatus(
                    '⚠️ Форма заполнена, но следующие поля из JSON проигнорированы ' +
                    '(их нет в форме подвески): ' + ignoredFields.join(', ') + '. ' +
                    'Их можно добавить вручную в описание или в БД.',
                    'warning'
                );
            } else {
                showStatus('✅ Форма успешно заполнена!', 'success');
            }
        }, 100);
        
        return true;
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
    
    console.log('JSON Import initialized. Producers: ' + Object.keys(producerMap).length + 
                ', Certs: ' + Object.keys(certMap).length);
})();
JS;
$this->registerJs($importScript);
?>
<?php endif; ?>