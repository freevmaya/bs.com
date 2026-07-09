<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Управление уведомлениями';
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем JS для управления подписками
$this->registerJsFile('@web/js/toggle-subscription.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

// Получаем CSRF токен
$csrfToken = Yii::$app->request->csrfToken;
?>

<div class="notification-index">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-md-8 col-lg-6">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Настройка каналов уведомлений</h3>
                </div>
                <div class="panel-body">
                    <p class="text-muted">
                        Включите или выключите уведомления для каждого канала.
                        Вы будете получать уведомления по всем событиям (новые объявления, подписки и т.д.)
                    </p>
                    
                    <hr>
                    
                    <?php if (empty($channels)): ?>
                        <div class="alert alert-warning">
                            Нет доступных каналов уведомлений.
                        </div>
                    <?php else: ?>
                        <?php foreach ($channels as $channelKey => $channel): ?>
                            <div class="channel-setting" style="padding: 12px 0; border-bottom: 1px solid #f0f0f0;">
                                <div class="row align-items-center">
                                    <div class="col-md-7 col-sm-7">
                                        <div class="channel-info">
                                            <strong><?= Html::encode($channel['label']) ?></strong>
                                            <p class="text-muted" style="font-size: 13px; margin: 2px 0 0 0;">
                                                <?= Html::encode($channel['description']) ?>
                                            </p>
                                            <small class="text-muted">
                                                Контакт: 
                                                <?php 
                                                // Для VK канала показываем ссылку, если она есть
                                                if ($channelKey === 'vk' && !empty($channel['contactInfo'])): ?>
                                                    <a href="<?= Html::encode($channel['contactInfo']) ?>" target="_blank" rel="noopener noreferrer">
                                                        <?= Html::encode($channel['contactInfo']) ?>
                                                    </a>
                                                <?php else: ?>
                                                    <?= Html::encode($channel['contactInfo']) ?>
                                                <?php endif; ?>
                                            </small>
                                            <?php if (!$channel['isAvailable']): ?>
                                                <br>
                                                <span class="label label-warning">Требуется заполнить контактные данные</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-5 col-sm-5 text-right" style="display: flex; align-items: center; justify-content: flex-end; padding-top: 5px;">
                                        <?php if ($channel['isAvailable']): ?>
                                            <div class="form-check form-switch" style="display: flex; align-items: center; gap: 12px; margin: 0;">
                                                <input class="form-check-input toggle-channel" 
                                                       type="checkbox" 
                                                       role="switch" 
                                                       id="switch-<?= $channelKey ?>"
                                                       data-channel="<?= $channelKey ?>"
                                                       data-active="<?= $channel['isActive'] ? 'true' : 'false' ?>"
                                                       data-enable-url="<?= Url::to(['notification/enable-channel']) ?>"
                                                       data-disable-url="<?= Url::to(['notification/disable-channel']) ?>"
                                                       <?= $channel['isActive'] ? 'checked' : '' ?>
                                                       style="width: 48px; height: 26px; cursor: pointer; transition: all 0.3s ease;">
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 13px;">
                                                <span class="glyphicon glyphicon-remove-circle" style="color: #dc3545;"></span>
                                                Недоступен
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    
                    <div class="alert alert-info" style="margin-top: 20px;">
                        <h4><span class="glyphicon glyphicon-info-sign"></span> Как это работает?</h4>
                        <ul style="margin-bottom: 0; padding-left: 20px;">
                            <li>Включение канала подписывает вас на <strong>все</strong> типы уведомлений (новые объявления, подписки и т.д.)</li>
                            <li>Выключение канала отключает <strong>все</strong> уведомления по этому каналу</li>
                            <li>Уведомления отправляются только на указанные контактные данные</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <p>
                <?= Html::a('← Назад в профиль', ['/user/profile'], ['class' => 'btn btn-default']) ?>
            </p>
        </div>
    </div>
</div>

<?php
// Передаем CSRF токен в JS
$this->registerJs(
    'var csrfToken = ' . json_encode($csrfToken) . ';',
    \yii\web\View::POS_BEGIN
);
?>

<style>
.channel-setting:hover {
    background-color: #f8f9fa;
    border-radius: 6px;
    margin: 0 -10px;
    padding-left: 10px !important;
    padding-right: 10px !important;
    transition: background-color 0.2s ease;
}

/* Анимация для переключателя */
.form-check-input.toggle-channel {
    transition: all 0.3s ease;
    cursor: pointer;
}

.form-check-input.toggle-channel:checked {
    background-color: #28a745;
    border-color: #28a745;
}

.form-check-input.toggle-channel:focus {
    box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
}

.form-check-input.toggle-channel:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

/* Анимация пульсации при переключении */
@keyframes switchPulse {
    0% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0.4); }
    50% { box-shadow: 0 0 0 8px rgba(40, 167, 69, 0.1); }
    100% { box-shadow: 0 0 0 0 rgba(40, 167, 69, 0); }
}

.form-check-input.toggle-channel.updating {
    animation: switchPulse 0.6s ease;
}

/* Стили для светлой темы */
[data-bs-theme="dark"] .channel-setting:hover {
    background-color: #2a2a2a;
}

[data-bs-theme="dark"] .form-check-input.toggle-channel:checked {
    background-color: #28a745;
    border-color: #28a745;
}

[data-bs-theme="dark"] .form-check-input.toggle-channel:focus {
    box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.3);
}
</style>