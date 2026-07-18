<?php
// FILE: .\views\site\register.php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

$this->title = 'Регистрация';
$this->params['breadcrumbs'][] = $this->title;

// Если есть токен приглашения, добавляем подсказку в breadcrumbs
if (isset($invitationToken) && $invitationToken) {
    $this->params['breadcrumbs'][] = 'По приглашению';
}

// Выводим все ошибки модели вверху
if ($model->hasErrors()) {
    echo '<div class="alert alert-danger">';
    echo '<strong>Ошибки в форме:</strong><ul>';
    foreach ($model->errors as $attribute => $errors) {
        foreach ($errors as $error) {
            echo '<li>' . Html::encode($error) . '</li>';
        }
    }
    echo '</ul></div>';
}
?>

<div class="site-register">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <?php if (isset($invitationToken) && $invitationToken): ?>
        <div class="alert alert-success">
            <strong><span class="glyphicon glyphicon-gift"></span> Регистрация по приглашению!</strong>
            <p style="margin-top: 8px;">
                После регистрации вы станете владельцем объявления, которое вам передали.
                Пожалуйста, заполните форму ниже.
            </p>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <?php $form = ActiveForm::begin([
                'id' => 'form-register',
                'enableClientValidation' => true,
                'enableAjaxValidation' => false,
                'options' => [
                    'class' => 'register-form',
                ],
            ]); ?>
            
            <!-- CSRF токен добавляется автоматически через ActiveForm -->
            
            <?= $form->field($model, 'username')->textInput([
                'autofocus' => true,
                'placeholder' => 'Введите имя пользователя',
            ]) ?>
            
            <?= $form->field($model, 'email')->textInput([
                'placeholder' => 'email@example.com',
            ]) ?>
            
            <?= $form->field($model, 'password')->passwordInput([
                'placeholder' => 'Минимум 6 символов',
            ]) ?>
            
            <?= $form->field($model, 'password_repeat')->passwordInput([
                'placeholder' => 'Повторите пароль',
            ]) ?>
            
            <hr>
            <h5>Контактная информация (необязательно)</h5>
            
            <?= $form->field($model, 'phone')->textInput([
                'placeholder' => '+7 (999) 123-45-67',
            ]) ?>
            
            <?= $form->field($model, 'city')->textInput([
                'placeholder' => 'Москва',
            ]) ?>
            
            <?= $form->field($model, 'telegram')->textInput([
                'placeholder' => '@username или username',
            ])->hint(
                'Введите ваш username в Telegram (без @ или с @). ' .
                'Для получения уведомлений необходимо ' .
                Html::a('начать диалог с ботом', 'https://t.me/Parasell_Bot', [
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                    'class' => 'hint-link',
                ]) .
                ' и нажать "Старт".'
            ) ?>
            
            <?= $form->field($model, 'whatsapp')->textInput([
                'placeholder' => '+7 (999) 123-45-67',
            ])->hint('Введите номер WhatsApp в международном формате') ?>
            
            <?= $form->field($model, 'vk_profile_url')->textInput([
                'placeholder' => 'https://vk.com/durov',
            ])->hint(
                'Введите ссылку на ваш профиль VK. ' .
                'Для получения уведомлений необходимо ' .
                Html::a('подписаться на сообщество', 'https://vk.com/club240146240', [
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                    'class' => 'hint-link',
                ]) .
                ' и разрешить отправку сообщений.'
            ) ?>
            
            <div class="form-group" style="margin-top: 20px;">
                <?= Html::submitButton('Зарегистрироваться', [
                    'class' => 'btn btn-primary btn-lg btn-block',
                    'name' => 'register-button',
                    'id' => 'register-button',
                ]) ?>
            </div>
            
            <div style="margin-top: 15px;">
                <?= Html::a('Уже есть аккаунт? Войти', ['login'], ['class' => 'btn btn-link']) ?>
            </div>
            
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<style>
.btn-block {
    display: block;
    width: 100%;
}

/* Стили для подсказок */
.help-block {
    font-size: 13px;
    line-height: 1.6;
    color: #6c757d;
}

.help-block .hint-link {
    color: #007bff;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s ease;
}

.help-block .hint-link:hover {
    text-decoration: underline;
    color: #0056b3;
}

.register-form .form-group {
    margin-bottom: 20px;
}

.register-form .form-control {
    border-radius: 6px;
    padding: 10px 14px;
    font-size: 15px;
}

.register-form .form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.register-form .btn-primary {
    padding: 12px 20px;
    font-size: 16px;
    border-radius: 6px;
}

/* Адаптивность для мобильных */
@media (max-width: 576px) {
    .register-form .form-control {
        font-size: 14px;
        padding: 8px 12px;
    }
    
    .register-form .btn-primary {
        font-size: 15px;
        padding: 10px 16px;
    }
}
</style>

<?php
// JavaScript для валидации пароля на клиенте
$script = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    var passwordField = document.getElementById('user-password');
    var passwordRepeatField = document.getElementById('user-password_repeat');
    var registerButton = document.getElementById('register-button');
    
    if (passwordField && passwordRepeatField) {
        function checkPasswords() {
            var password = passwordField.value;
            var repeat = passwordRepeatField.value;
            
            if (repeat.length > 0 && password !== repeat) {
                passwordRepeatField.style.borderColor = '#dc3545';
                passwordRepeatField.style.backgroundColor = '#fff8f8';
            } else if (repeat.length > 0) {
                passwordRepeatField.style.borderColor = '#28a745';
                passwordRepeatField.style.backgroundColor = '#f0fff4';
            } else {
                passwordRepeatField.style.borderColor = '';
                passwordRepeatField.style.backgroundColor = '';
            }
        }
        
        passwordField.addEventListener('input', checkPasswords);
        passwordRepeatField.addEventListener('input', checkPasswords);
    }
});
JS;
$this->registerJs($script);
?>