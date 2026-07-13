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
                    
                    <hr>
                    <p class="text-muted"><small>Контактная информация (необязательно)</small></p>
                    
                    <?= $form->field($model, 'city')->textInput(['maxlength' => true, 'placeholder' => 'Город (необязательно)']) ?>
                    
                    <?= $form->field($model, 'phone')->textInput(['maxlength' => true, 'placeholder' => 'Телефон (необязательно)']) ?>
                    
                    <?= $form->field($model, 'email')->textInput(['maxlength' => true, 'placeholder' => 'Email (необязательно)']) ?>
                    
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
            <div id="images-block" style="display: none;" data-delete-url="<?= \yii\helpers\Url::to(['advertisements/delete-temp-image-ajax']) ?>">
                <?= $this->render('_images_block', [
                    'images' => $tempImages,
                    'type' => 'create',
                    'id' => $tempId,
                ]) ?>
            </div>
        </div>
    </div>
</div>