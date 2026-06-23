<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Вход';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-login">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-lg-5">
            <?php $form = ActiveForm::begin(['id' => 'login-form']); ?>
            
            <?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>
            
            <?= $form->field($model, 'password')->passwordInput() ?>
            
            <?= $form->field($model, 'rememberMe')->checkbox() ?>
            
            <div class="form-group">
                <?= Html::submitButton('Войти', ['class' => 'btn btn-primary', 'name' => 'login-button']) ?>
            </div>
            
            <?php ActiveForm::end(); ?>
            
            <hr>
            <p>Ещё нет аккаунта? <?= Html::a('Зарегистрироваться', ['register']) ?></p>
        </div>
    </div>
</div>