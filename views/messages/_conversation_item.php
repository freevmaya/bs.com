<?php

use yii\helpers\Html;
use yii\helpers\Url;
use app\models\User;

/**
 * @var \app\models\Conversation $model
 */

$userId = Yii::$app->user->id;
$otherUser = $model->getOtherUser($userId);
$unreadCount = $model->getUnreadCount($userId);
$lastMessage = $model->lastMessage;
$advertisement = $model->advertisement;

$isUnread = $unreadCount > 0;
$cssClass = $isUnread ? 'conversation-item unread' : 'conversation-item';

// Определяем аватар пользователя
$avatar = '<span style="font-size: 24px;">👤</span>';
if ($otherUser) {
    $avatar = $otherUser->username ? mb_substr($otherUser->username, 0, 1) : '?';
}

?>

<div class="<?= $cssClass ?>" onclick="window.location.href='<?= Url::to(['view', 'id' => $model->id]) ?>'">
    <div class="row align-items-center">
        <div class="col-auto">
            <div class="avatar">
                <?= Html::encode($avatar) ?>
            </div>
        </div>
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <strong>
                    <?= $otherUser ? Html::encode($otherUser->username) : 'Пользователь удален' ?>
                    <?php if ($advertisement): ?>
                        <small class="text-muted">
                            &middot; <?= Html::encode($advertisement->title) ?>
                        </small>
                    <?php endif; ?>
                </strong>
                <span class="time">
                    <?= $lastMessage ? Yii::$app->formatter->asDatetime($lastMessage->created_at) : '' ?>
                </span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="message-preview">
                    <?php if ($lastMessage): ?>
                        <?php if ($lastMessage->sender_id == $userId): ?>
                            <span class="text-muted">Вы: </span>
                        <?php endif; ?>
                        <?= Html::encode($lastMessage->message) ?>
                    <?php else: ?>
                        <span class="text-muted">Нет сообщений</span>
                    <?php endif; ?>
                </span>
                <?php if ($isUnread): ?>
                    <span class="unread-badge"><?= $unreadCount ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>