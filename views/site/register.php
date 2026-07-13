<?php
// FILE: .\views\site\register.php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Регистрация';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="site-register">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-lg-6">
            <?php $form = ActiveForm::begin(['id' => 'form-register']); ?>
            
            <?= $form->field($model, 'username')->textInput(['autofocus' => true]) ?>
            
            <?= $form->field($model, 'email')->textInput() ?>
            
            <?= $form->field($model, 'password')->passwordInput() ?>
            
            <?= $form->field($model, 'password_repeat')->passwordInput() ?>
            
            <hr>
            <h5>Контактная информация (необязательно)</h5>
            
            <?= $form->field($model, 'phone')->textInput(['placeholder' => '+7 (999) 123-45-67']) ?>
            
            <?= $form->field($model, 'city')->textInput(['placeholder' => 'Москва']) ?>
            
            <?= $form->field($model, 'telegram')->textInput(['placeholder' => '@username или username']) ?>
            
            <?= $form->field($model, 'whatsapp')->textInput(['placeholder' => '+7 (999) 123-45-67']) ?>
            
            <?= $form->field($model, 'vk_profile_url')->textInput([
                'placeholder' => 'https://vk.com/durov',
            ])->hint('Введите ссылку на ваш профиль VK (необязательно)') ?>
            
            <div class="form-group">
                <?= Html::submitButton('Зарегистрироваться', ['class' => 'btn btn-primary', 'name' => 'register-button']) ?>
            </div>
            
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>