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
                                <?php foreach ($channels as $channelKey => $channel): ?>
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
                                                    data-subscribed="<?= $isSubscribed ? 'true' : 'false' ?>"
                                                    data-subscribe-url="<?= Url::to(['notification/subscribe']) ?>"
                                                    data-unsubscribe-url="<?= Url::to(['notification/unsubscribe']) ?>">
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
// Передаем CSRF токен в JS
$this->registerJs(
    'var csrfToken = ' . json_encode($csrfToken) . ';',
    \yii\web\View::POS_BEGIN
);
?>