<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Регистрация';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-register">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-lg-5">
            <?php $form = ActiveForm::begin(['id' => 'form-register']); ?>
            
            <?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>
            
            <?= $form->field($model, 'email')->textInput() ?>
            
            <?= $form->field($model, 'password')->passwordInput() ?>
            
            <?= $form->field($model, 'password_repeat')->passwordInput() ?>
            
            <?= $form->field($model, 'phone')->textInput() ?>
            
            <?= $form->field($model, 'city')->textInput() ?>
            
            <div class="form-group">
                <?= Html::submitButton('Зарегистрироваться', ['class' => 'btn btn-primary', 'name' => 'register-button']) ?>
            </div>
            
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>