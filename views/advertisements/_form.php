<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
?>

<div class="advertisements-form">
    <?php $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data', 'id' => 'advertisement-form']]); ?>
    
    <?= $form->field($model, 'section')->dropDownList([
        'sell' => 'Продам',
        'buy' => 'Куплю',
    ], ['id' => 'section-select']) ?>
    
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
    
    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Создать' : 'Сохранить', ['class' => 'btn btn-success']) ?>
    </div>
    
    <?php ActiveForm::end(); ?>
</div>

<script>
    document.getElementById('section-select').addEventListener('change', function() {
        if (this.value === 'sell') {
            // Показать блок с изображениями
            document.getElementById('images-section').style.display = 'block';
        } else {
            // Скрыть блок с изображениями
            document.getElementById('images-section').style.display = 'none';
        }
    });
</script>