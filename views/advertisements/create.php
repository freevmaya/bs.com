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
    
    // Маппинг производителей и сертификаций из БД
    var producerMap = {$producerMapJson};
    var certMap = {$certMapJson};
    
    // Кэш для опций select
    var producerOptions = null;
    var certOptions = null;
    
    var importBtn = document.getElementById('json-import-btn');
    var clearBtn = document.getElementById('json-import-clear');
    var textarea = document.getElementById('json-import');
    var statusEl = document.getElementById('json-import-status');
    var errorsEl = document.getElementById('json-import-errors');
    
    function getProducerSelect() {
        return document.getElementById('advertisementglider-producer_id');
    }
    
    function getCertSelect() {
        return document.getElementById('advertisementglider-certification_id');
    }
    
    function getProducerOptions() {
        if (producerOptions !== null) return producerOptions;
        var select = getProducerSelect();
        if (!select) return [];
        producerOptions = [];
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            producerOptions.push({
                value: opt.value,
                text: opt.text.toLowerCase().trim()
            });
        }
        return producerOptions;
    }
    
    function getCertOptions() {
        if (certOptions !== null) return certOptions;
        var select = getCertSelect();
        if (!select) return [];
        certOptions = [];
        for (var i = 0; i < select.options.length; i++) {
            var opt = select.options[i];
            certOptions.push({
                value: opt.value,
                text: opt.text.toLowerCase().trim()
            });
        }
        return certOptions;
    }
    
    function findProducerId(name) {
        if (!name) return null;
        
        var searchName = String(name).toLowerCase().trim();
        
        // 1. Проверяем по маппингу (точное совпадение)
        if (producerMap[searchName]) {
            return producerMap[searchName];
        }
        
        // 2. Проверяем по частичному совпадению в маппинге
        var keys = Object.keys(producerMap);
        for (var i = 0; i < keys.length; i++) {
            if (searchName.indexOf(keys[i]) !== -1 || keys[i].indexOf(searchName) !== -1) {
                return producerMap[keys[i]];
            }
        }
        
        // 3. Проверяем по опциям select
        var options = getProducerOptions();
        for (var i = 0; i < options.length; i++) {
            var opt = options[i];
            // Точное совпадение
            if (opt.text === searchName) {
                return opt.value;
            }
            // Частичное совпадение
            if (opt.text.indexOf(searchName) !== -1 || searchName.indexOf(opt.text) !== -1) {
                return opt.value;
            }
        }
        
        return null;
    }
    
    function findCertId(name) {
        if (!name) return null;
        
        var searchName = String(name).toLowerCase().trim();
        
        // 1. Проверяем по маппингу
        if (certMap[searchName]) {
            return certMap[searchName];
        }
        
        // 2. Проверяем по частичному совпадению
        var keys = Object.keys(certMap);
        for (var i = 0; i < keys.length; i++) {
            if (searchName.indexOf(keys[i]) !== -1 || keys[i].indexOf(searchName) !== -1) {
                return certMap[keys[i]];
            }
        }
        
        // 3. Проверяем по опциям select
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
    
    // Маппинг полей формы
    var fieldMap = {
        // Основные поля
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
        'item_info_link': 'advertisement-item_info_link',
        
        // Поля glider (прямой маппинг)
        'glider_model': 'advertisementglider-model',
        'glider_producer_id': 'advertisementglider-producer_id',
        'glider_certification_id': 'advertisementglider-certification_id',
        'glider_weight_min': 'advertisementglider-weight_min',
        'glider_weight_max': 'advertisementglider-weight_max',
        'glider_date_release': 'advertisementglider-date_release',
        'glider_flight_time': 'advertisementglider-flight_time',
        'glider_condition': 'advertisementglider-condition',
        'glider_defects': 'advertisementglider-defects',
        'glider_cause': 'advertisementglider-cause',
    };
    
    function setFieldValue(fieldId, value) {
        var el = document.getElementById(fieldId);
        if (!el) return false;
        
        if (el.type === 'checkbox') {
            el.checked = value === true || value === 'true' || value === 1 || value === '1';
        } else if (el.tagName === 'SELECT') {
            // Для select пробуем найти опцию с таким значением
            var found = false;
            for (var i = 0; i < el.options.length; i++) {
                if (el.options[i].value == value) {
                    el.value = value;
                    found = true;
                    break;
                }
            }
            if (!found && value !== null && value !== undefined && value !== '') {
                // Если значение не найдено, пробуем найти по тексту
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
            if (!found && value && el.options.length > 0) {
                // Если все равно не найдено, пробуем числовое совпадение
                for (var i = 0; i < el.options.length; i++) {
                    if (el.options[i].value == value) {
                        el.value = value;
                        found = true;
                        break;
                    }
                }
            }
        } else {
            el.value = value;
        }
        
        // Триггерим события для обновления зависимых полей
        var event = new Event('change', { bubbles: true });
        el.dispatchEvent(event);
        
        return true;
    }
    
    function fillFormFromJSON(jsonData) {
        var data;
        try {
            data = typeof jsonData === 'string' ? JSON.parse(jsonData) : jsonData;
        } catch (e) {
            showError('Ошибка парсинга JSON: ' + e.message);
            return false;
        }
        
        // Очищаем старые ошибки
        hideError();
        
        // 1. Заполняем основные поля
        var mainFields = ['type', 'section', 'title', 'description', 'price', 'currency', 
                         'price_negotiable', 'city', 'phone', 'email', 'telegram', 
                         'vk_profile_url', 'whatsapp', 'source_url', 'item_info_link'];
        
        for (var i = 0; i < mainFields.length; i++) {
            var key = mainFields[i];
            if (data[key] !== undefined && data[key] !== null && data[key] !== '') {
                var fieldId = fieldMap[key];
                if (fieldId) {
                    setFieldValue(fieldId, data[key]);
                    console.log('Set ' + key + ' = ' + data[key]);
                }
            }
        }
        
        // 2. Заполняем поля glider
        if (data.glider) {
            var glider = data.glider;
            
            // Прямые поля
            var directFields = ['model', 'weight_min', 'weight_max', 'date_release', 
                               'flight_time', 'condition', 'defects', 'cause'];
            
            for (var i = 0; i < directFields.length; i++) {
                var key = directFields[i];
                if (glider[key] !== undefined && glider[key] !== null && glider[key] !== '') {
                    var fieldKey = 'glider_' + key;
                    var fieldId = fieldMap[fieldKey];
                    if (fieldId) {
                        setFieldValue(fieldId, glider[key]);
                        console.log('Set glider.' + key + ' = ' + glider[key]);
                    }
                }
            }
            
            // --- ПРОИЗВОДИТЕЛЬ (по названию) ---
            var producerId = null;
            
            // 1. Если указан producer_id напрямую
            if (glider.producer_id) {
                producerId = glider.producer_id;
                console.log('Using producer_id from JSON: ' + producerId);
            }
            
            // 2. Если указан producer_name - ищем в БД
            if (!producerId && glider.producer_name) {
                producerId = findProducerId(glider.producer_name);
                if (producerId) {
                    console.log('Found producer by name "' + glider.producer_name + '" -> ID ' + producerId);
                } else {
                    console.warn('Producer not found: ' + glider.producer_name);
                    showStatus('⚠️ Производитель "' + glider.producer_name + '" не найден в БД', 'warning');
                }
            }
            
            // 3. Если указан model - пробуем найти производителя по модели
            if (!producerId && glider.model) {
                // Пробуем найти по полному названию модели с производителем
                var modelLower = String(glider.model).toLowerCase();
                var producerKeys = Object.keys(producerMap);
                for (var i = 0; i < producerKeys.length; i++) {
                    if (modelLower.indexOf(producerKeys[i]) !== -1) {
                        producerId = producerMap[producerKeys[i]];
                        console.log('Found producer by model "' + glider.model + '" -> ' + producerKeys[i]);
                        break;
                    }
                }
            }
            
            // Устанавливаем производителя
            if (producerId) {
                var producerFieldId = fieldMap['glider_producer_id'];
                if (producerFieldId) {
                    setFieldValue(producerFieldId, producerId);
                    console.log('Set producer_id = ' + producerId);
                }
            }
            
            // --- СЕРТИФИКАЦИЯ (по названию) ---
            var certId = null;
            
            // 1. Если указан certification_id напрямую
            if (glider.certification_id) {
                certId = glider.certification_id;
                console.log('Using certification_id from JSON: ' + certId);
            }
            
            // 2. Если указан certification_name - ищем в БД
            if (!certId && glider.certification_name) {
                certId = findCertId(glider.certification_name);
                if (certId) {
                    console.log('Found certification by name "' + glider.certification_name + '" -> ID ' + certId);
                } else {
                    console.warn('Certification not found: ' + glider.certification_name);
                    showStatus('⚠️ Сертификация "' + glider.certification_name + '" не найдена в БД', 'warning');
                }
            }
            
            // Устанавливаем сертификацию
            if (certId) {
                var certFieldId = fieldMap['glider_certification_id'];
                if (certFieldId) {
                    setFieldValue(certFieldId, certId);
                    console.log('Set certification_id = ' + certId);
                }
            }
        }
        
        // 3. Автоматически показываем нужные поля
        if (data.type) {
            var typeSelect = document.getElementById('type-select');
            if (typeSelect) {
                var event = new Event('change', { bubbles: true });
                typeSelect.dispatchEvent(event);
            }
        }
        
        // 4. Показываем блок изображений если раздел 'sell'
        if (data.section === 'sell') {
            var imagesBlock = document.getElementById('images-block');
            if (imagesBlock) {
                imagesBlock.style.display = 'block';
            }
        }
        
        showStatus('✅ Форма успешно заполнена!', 'success');
        return true;
    }
    
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
    
    // Обработчик кнопки импорта
    importBtn.addEventListener('click', function() {
        var jsonText = textarea.value.trim();
        if (!jsonText) {
            showError('Пожалуйста, вставьте JSON данные');
            return;
        }
        
        fillFormFromJSON(jsonText);
    });
    
    // Обработчик кнопки очистки
    clearBtn.addEventListener('click', function() {
        textarea.value = '';
        hideError();
        showStatus('', '');
    });
    
    // Поддержка Ctrl+Enter для быстрого импорта
    textarea.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            e.preventDefault();
            importBtn.click();
        }
    });
    
    console.log('JSON Import initialized with ' + Object.keys(producerMap).length + ' producers and ' + Object.keys(certMap).length + ' certifications');
    console.log('Producer map:', producerMap);
    console.log('Cert map:', certMap);
})();
JS;
$this->registerJs($importScript);
?>
<?php endif; ?>