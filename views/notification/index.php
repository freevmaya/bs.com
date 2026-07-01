<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Управление уведомлениями';
$this->params['breadcrumbs'][] = $this->title;

// Получаем CSRF токен
$csrfToken = Yii::$app->request->csrfToken;
?>

<div class="notification-index">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">Настройки уведомлений</h3>
                </div>
                <div class="panel-body">
                    <p>Выберите события и каналы, по которым вы хотите получать уведомления.</p>
                    
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Событие</th>
                                <?php foreach ($channels as $channel): ?>
                                    <th><?= $channel->getDescription() ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($events as $eventKey => $eventLabel): ?>
                                <tr>
                                    <td><strong><?= $eventLabel ?></strong></td>
                                    <?php foreach ($channels as $channelKey => $channel): ?>
                                        <td class="text-center">
                                            <?php 
                                            $subscriptionKey = $eventKey . '_' . $channelKey;
                                            $isSubscribed = isset($subscriptions[$subscriptionKey]) && $subscriptions[$subscriptionKey]->is_active;
                                            ?>
                                            <button class="btn btn-<?= $isSubscribed ? 'success' : 'default' ?> btn-sm toggle-subscription"
                                                    data-event="<?= $eventKey ?>"
                                                    data-channel="<?= $channelKey ?>"
                                                    data-subscribed="<?= $isSubscribed ? 'true' : 'false' ?>">
                                                <?= $isSubscribed ? '✓ Подписан' : 'Подписаться' ?>
                                            </button>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="alert alert-info">
                        <h4>Доступные каналы уведомлений:</h4>
                        <ul>
                            <?php foreach ($channels as $channel): ?>
                                <li>
                                    <strong><?= $channel->getDescription() ?></strong>
                                    <?php if ($channel->isAvailable()): ?>
                                        <span class="label label-success">Доступен</span>
                                    <?php else: ?>
                                        <span class="label label-danger">Недоступен</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Правильные URL для AJAX запросов
$subscribeUrl = Url::to(['notification/subscribe']);
$unsubscribeUrl = Url::to(['notification/unsubscribe']);

$script = <<<JS
$(document).on('click', '.toggle-subscription', function() {
    var button = $(this);
    var event = button.data('event');
    var channel = button.data('channel');
    
    // Определяем текущее состояние по классу и тексту кнопки
    var isSubscribed = button.hasClass('btn-success');
    
    // Определяем URL в зависимости от действия
    var url = isSubscribed ? '{$unsubscribeUrl}' : '{$subscribeUrl}';
    var action = isSubscribed ? 'отписка' : 'подписка';
    
    // Блокируем кнопку
    button.prop('disabled', true);
    button.text('Обработка...');
    
    console.log('Action:', action, 'Event:', event, 'Channel:', channel);
    
    $.ajax({
        url: url,
        type: 'POST',
        data: {
            event: event,
            channel: channel,
            _csrf: '{$csrfToken}'
        },
        dataType: 'json',
        success: function(response) {
            console.log('Response:', response);
            
            if (response.success) {
                if (isSubscribed) {
                    // Отписка
                    button.removeClass('btn-success').addClass('btn-default');
                    button.text('Подписаться');
                    button.data('subscribed', 'false');
                    showNotification('Вы отписались от уведомлений', 'info');
                } else {
                    // Подписка
                    button.removeClass('btn-default').addClass('btn-success');
                    button.text('✓ Подписан');
                    button.data('subscribed', 'true');
                    showNotification('Вы подписались на уведомления', 'success');
                }
            } else {
                var errorMsg = response.error || 'Неизвестная ошибка';
                showNotification('Ошибка: ' + errorMsg, 'danger');
                // Восстанавливаем состояние
                if (isSubscribed) {
                    button.text('✓ Подписан');
                } else {
                    button.text('Подписаться');
                }
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Error:', status, error);
            console.error('Response:', xhr.responseText);
            showNotification('Произошла ошибка при отправке запроса', 'danger');
            // Восстанавливаем состояние
            if (isSubscribed) {
                button.text('✓ Подписан');
            } else {
                button.text('Подписаться');
            }
        },
        complete: function() {
            button.prop('disabled', false);
        }
    });
});

// Функция для показа уведомлений
function showNotification(message, type) {
    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.innerHTML = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';
    alertDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
    
    document.body.appendChild(alertDiv);
    
    setTimeout(function() {
        alertDiv.style.opacity = '0';
        alertDiv.style.transition = 'opacity 0.5s ease';
        setTimeout(function() {
            if (alertDiv && alertDiv.remove) {
                alertDiv.remove();
            }
        }, 500);
    }, 3000);
}
JS;
$this->registerJs($script);
?>