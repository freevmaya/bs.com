<?php
// FILE: .\views\user\change-password.php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Смена пароля';
$this->params['breadcrumbs'][] = ['label' => 'Профиль', 'url' => ['profile']];
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем JS для проверки пароля
$this->registerJs("
    var checkPasswordTimer = null;
    
    $('#old_password').on('input', function() {
        var password = $(this).val();
        var feedback = $('#old-password-feedback');
        
        if (password.length === 0) {
            feedback.html('').removeClass('text-success text-danger');
            return;
        }
        
        clearTimeout(checkPasswordTimer);
        checkPasswordTimer = setTimeout(function() {
            $.ajax({
                url: '" . \yii\helpers\Url::to(['user/check-password']) . "',
                type: 'POST',
                data: { password: password },
                dataType: 'json',
                success: function(response) {
                    if (response.valid) {
                        feedback.html('<span class=\"glyphicon glyphicon-ok\"></span> Пароль верный')
                            .removeClass('text-danger').addClass('text-success');
                    } else {
                        feedback.html('<span class=\"glyphicon glyphicon-remove\"></span> ' + response.message)
                            .removeClass('text-success').addClass('text-danger');
                    }
                },
                error: function() {
                    feedback.html('Ошибка проверки').removeClass('text-success').addClass('text-danger');
                }
            });
        }, 500);
    });
    
    // Проверка совпадения новых паролей
    $('#new_password, #new_password_repeat').on('input', function() {
        var password = $('#new_password').val();
        var repeat = $('#new_password_repeat').val();
        var feedback = $('#new-password-feedback');
        
        if (password.length === 0 && repeat.length === 0) {
            feedback.html('').removeClass('text-success text-danger');
            return;
        }
        
        if (password.length > 0 && repeat.length > 0 && password === repeat) {
            feedback.html('<span class=\"glyphicon glyphicon-ok\"></span> Пароли совпадают')
                .removeClass('text-danger').addClass('text-success');
        } else if (repeat.length > 0) {
            feedback.html('<span class=\"glyphicon glyphicon-remove\"></span> Пароли не совпадают')
                .removeClass('text-success').addClass('text-danger');
        }
    });
", \yii\web\View::POS_READY);
?>

<div class="user-change-password">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'change-password-form',
                        'options' => ['data-ajax' => 'false'],
                    ]); ?>
                    
                    <div class="form-group">
                        <label for="old_password" class="control-label">Текущий пароль</label>
                        <input type="password" id="old_password" name="old_password" class="form-control" required autofocus>
                        <div id="old-password-feedback" class="help-block"></div>
                    </div>
                    
                    <hr>
                    
                    <div class="form-group">
                        <label for="new_password" class="control-label">Новый пароль</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" required minlength="6">
                        <div class="help-block">Пароль должен содержать не менее 6 символов</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="new_password_repeat" class="control-label">Повторите новый пароль</label>
                        <input type="password" id="new_password_repeat" name="new_password_repeat" class="form-control" required>
                        <div id="new-password-feedback" class="help-block"></div>
                    </div>
                    
                    <div class="form-group" style="margin-top: 20px;">
                        <?= Html::submitButton('Сменить пароль', [
                            'class' => 'btn btn-primary',
                            'id' => 'change-password-btn',
                        ]) ?>
                        <?= Html::a('Отмена', ['profile'], ['class' => 'btn btn-default']) ?>
                    </div>
                    
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
            
            <div class="alert alert-info">
                <span class="glyphicon glyphicon-info-sign"></span>
                <strong>Рекомендации по созданию пароля:</strong>
                <ul style="margin-top: 8px; padding-left: 20px;">
                    <li>Используйте не менее 8 символов</li>
                    <li>Комбинируйте буквы (заглавные и строчные), цифры и специальные символы</li>
                    <li>Не используйте личную информацию (имя, дату рождения)</li>
                    <li>Не используйте один и тот же пароль для разных сайтов</li>
                </ul>
            </div>
            
            <p>
                <?= Html::a('← Назад в профиль', ['profile'], ['class' => 'btn btn-default']) ?>
            </p>
        </div>
    </div>
</div>

<style>
#old-password-feedback, #new-password-feedback {
    margin-top: 5px;
    font-size: 13px;
}
.text-success {
    color: #28a745;
}
.text-danger {
    color: #dc3545;
}
</style>