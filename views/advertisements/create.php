<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Url;
use app\models\Advertisement;

$this->title = 'Добавить объявление';
$this->params['breadcrumbs'][] = ['label' => 'Объявления', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="advertisements-create">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-body">
                    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data', 'id' => 'advertisement-form']]); ?>
                    
                    <?= $form->field($model, 'section')->dropDownList([
                        '' => 'Выберите раздел',
                        'sell' => 'Продам',
                        'buy' => 'Куплю',
                    ], ['id' => 'section-select']) ?>
                    
                    <?= $form->field($model, 'type')->dropDownList(
                        Advertisement::getTypeList(),
                        ['prompt' => 'Выберите тип снаряжения', 'id' => 'type-select']
                    ) ?>
                    
                    <?= $form->field($model, 'title')->textInput(['maxlength' => true]) ?>
                    
                    <?= $form->field($model, 'description')->textarea(['rows' => 6]) ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'price')->textInput(['placeholder' => '1000']) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'price_negotiable')->checkbox() ?>
                        </div>
                    </div>
                    
                    <?= $form->field($model, 'city')->textInput(['maxlength' => true]) ?>
                    
                    <?= $form->field($model, 'phone')->textInput(['maxlength' => true]) ?>
                    
                    <?= $form->field($model, 'email')->textInput(['maxlength' => true]) ?>
                    
                    <!-- Динамические поля для разных типов -->
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
                    
                    <div class="form-group">
                        <?= Html::submitButton('Создать объявление', ['class' => 'btn btn-success btn-lg btn-block']) ?>
                    </div>
                    
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div id="images-block" style="display: none;">
                <?= $this->render('_images_block', [
                    'images' => $tempImages,
                    'type' => 'create',
                    'id' => $tempId,
                ]) ?>
            </div>
        </div>
    </div>
</div>

<script>
// Отслеживаем изменение раздела
var sectionSelect = document.getElementById('section-select');
var imagesBlock = document.getElementById('images-block');

function toggleImagesBlock() {
    if (sectionSelect.value === 'sell') {
        imagesBlock.style.display = 'block';
    } else {
        imagesBlock.style.display = 'none';
    }
}

toggleImagesBlock();
sectionSelect.addEventListener('change', toggleImagesBlock);

// Динамическое отображение полей в зависимости от типа
var typeSelect = document.getElementById('type-select');
var gliderFields = document.getElementById('glider-fields');
var harnessFields = document.getElementById('harness-fields');
var deviceFields = document.getElementById('device-fields');

function toggleTypeFields() {
    // Скрываем все поля
    gliderFields.style.display = 'none';
    harnessFields.style.display = 'none';
    deviceFields.style.display = 'none';
    
    // Показываем нужные
    var selectedType = typeSelect.value;
    if (selectedType === 'glider') {
        gliderFields.style.display = 'block';
    } else if (selectedType === 'harness') {
        harnessFields.style.display = 'block';
    } else if (selectedType === 'device') {
        deviceFields.style.display = 'block';
    }
}

toggleTypeFields();
typeSelect.addEventListener('change', toggleTypeFields);
</script>

<script>
// AJAX валидация формы перед отправкой
document.getElementById('advertisement-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    var form = this;
    var formData = new FormData(form);
    var submitButton = form.querySelector('[type="submit"]');
    
    // Блокируем кнопку
    submitButton.disabled = true;
    submitButton.textContent = 'Проверка...';
    
    // Отправляем AJAX запрос для валидации
    var xhr = new XMLHttpRequest();
    xhr.open('POST', form.action + '?validate=1', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onload = function() {
        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    // Если валидация прошла, отправляем форму
                    form.submit();
                } else {
                    // Показываем ошибки
                    if (response.errors) {
                        for (var field in response.errors) {
                            var message = response.errors[field];
                            showNotification(message, 'danger');
                        }
                    } else if (response.message) {
                        showNotification(response.message, 'danger');
                    } else {
                        showNotification('Пожалуйста, заполните все обязательные поля', 'danger');
                    }
                    
                    // Подсвечиваем поля с ошибками
                    if (response.invalidFields) {
                        for (var i = 0; i < response.invalidFields.length; i++) {
                            var field = document.querySelector('[name="' + response.invalidFields[i] + '"]');
                            if (field) {
                                field.style.borderColor = '#dc3545';
                                field.style.backgroundColor = '#fff8f8';
                                field.addEventListener('focus', function() {
                                    this.style.borderColor = '';
                                    this.style.backgroundColor = '';
                                }, { once: true });
                            }
                        }
                    }
                    
                    // Разблокируем кнопку
                    submitButton.disabled = false;
                    submitButton.textContent = 'Создать объявление';
                    
                    // Прокручиваем к первому полю с ошибкой
                    var firstError = document.querySelector('[style*="border-color: rgb(220, 53, 69)"]');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
            } catch(e) {
                // Если не JSON, отправляем форму
                form.submit();
            }
        } else {
            // Если ошибка сервера, отправляем обычную форму
            form.submit();
        }
    };
    
    xhr.onerror = function() {
        form.submit();
    };
    
    xhr.send(formData);
});
</script>