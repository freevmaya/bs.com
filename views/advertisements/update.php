<?php
// FILE: .\views\advertisements\update.php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Advertisement;

$this->title = 'Редактирование: ' . $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Объявления', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->title, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Редактирование';

// Регистрируем CSS и JS для формы
$this->registerCssFile('@web/css/advertisement-form.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/advertisement-form.js', [
    'depends' => [\yii\web\JqueryAsset::class, \yii\jui\JuiAsset::class],
    'position' => \yii\web\View::POS_END
]);

// Проверяем, является ли пользователь администратором
$isAdmin = !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();
?>

<div class="advertisements-update">
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
                        <div class="col-md-6">
                            <?= $form->field($model, 'price')->textInput(['placeholder' => '1000']) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'price_negotiable')->checkbox() ?>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- ДИНАМИЧЕСКИЕ ПОЛЯ ДЛЯ РАЗНЫХ ТИПОВ (ПЕРЕМЕЩЕНЫ ВЫШЕ) -->
                    <!-- ============================================ -->
                    <div id="glider-fields" style="display: <?= $model->type === 'glider' ? 'block' : 'none' ?>;">
                        <?= $this->render('_glider_fields', [
                            'form' => $form,
                            'gliderModel' => $gliderModel,
                        ]) ?>
                    </div>

                    <div id="harness-fields" style="display: <?= $model->type === 'harness' ? 'block' : 'none' ?>;">
                        <?= $this->render('_harness_fields', [
                            'form' => $form,
                            'harnessModel' => $harnessModel,
                        ]) ?>
                    </div>

                    <div id="device-fields" style="display: <?= $model->type === 'device' ? 'block' : 'none' ?>;">
                        <?= $this->render('_device_fields', [
                            'form' => $form,
                            'deviceModel' => $deviceModel,
                        ]) ?>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- КОНТАКТНАЯ ИНФОРМАЦИЯ (ПЕРЕМЕЩЕНА НИЖЕ) -->
                    <!-- ============================================ -->
                    <hr>
                    <p class="text-muted"><small>Контактная информация</small></p>
                    
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
                    
                    <div class="form-group">
                        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary']) ?>
                    </div>
                    
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
        
        <?php if ($model->section === 'sell'): ?>
            <div class="col-md-6">
                <div id="images-block" data-delete-url="<?= \yii\helpers\Url::to(['advertisements/delete-image-ajax']) ?>">
                    <?= $this->render('_images_block', [
                        'images' => $model->images,
                        'type' => 'update',
                        'id' => $model->id,
                    ]) ?>
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
        if (titleInput) {
            titleInput.disabled = false;
        }
    } else {
        titleField.style.display = 'none';
        // ПРИНУДИТЕЛЬНО ОЧИЩАЕМ И ОТКЛЮЧАЕМ ПОЛЕ ЗАГОЛОВКА
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