<?php

use yii\helpers\Html;

/**
 * @var \app\models\Message $message
 */

$userId = Yii::$app->user->id;
$isSent = $message->sender_id == $userId;
$cssClass = $isSent ? 'message-item message-sent' : 'message-item message-received';

?>

<div class="<?= $cssClass ?>" data-message-id="<?= $message->id ?>">
    <div class="message-bubble">
        <?= nl2br(Html::encode($message->message)) ?>
    </div>
    <div class="message-time">
        <?= Yii::$app->formatter->asTime($message->created_at, 'php:H:i') ?>
        <?php if ($isSent): ?>
            <span class="message-status">
                <?php if ($message->is_read): ?>
                    ✓✓
                <?php else: ?>
                    ✓
                <?php endif; ?>
            </span>
        <?php endif; ?>
    </div>
</div>