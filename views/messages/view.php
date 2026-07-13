<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

$this->title = 'Диалог с ' . Html::encode($otherUser->username);
$this->params['breadcrumbs'][] = ['label' => 'Мои диалоги', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем CSS и JS
$this->registerCssFile('@web/css/messages.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/messages.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

?>

<div class="messages-view">
    <div class="row">
        <div>
            <div class="messages-container" id="messages-container" data-conversation-id="<?= $conversation->id ?>">
                <!-- Заголовок -->
                <div class="messages-header d-flex align-items-center">
                    <!-- Стрелка назад ведет на страницу объявления -->
                    <a href="<?= Url::to(['advertisements/view', 'id' => $conversation->advertisement_id]) ?>" class="back-link" title="Вернуться к объявлению">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="19" y1="12" x2="5" y2="12"/>
                            <polyline points="12 19 5 12 12 5"/>
                        </svg>
                    </a>
                    
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <strong><?= Html::encode($otherUser->username) ?></strong>
                        <?php if ($conversation->advertisement): ?>
                            <span class="text-muted d-none d-sm-inline">&middot;</span>
                            <a href="<?= Url::to(['advertisements/view', 'id' => $conversation->advertisement_id]) ?>" target="_blank" class="text-muted text-decoration-none">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 3px;">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <?= Html::encode($conversation->advertisement->title) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="ms-auto d-flex gap-1">
                        <!-- Кнопка закрытия диалога -->
                        <?= Html::a(
                            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
                            ['close', 'id' => $conversation->id],
                            [
                                'class' => 'btn btn-sm btn-outline-danger',
                                'data-method' => 'post',
                                'data-confirm' => 'Вы уверены, что хотите закрыть диалог?',
                                'title' => 'Закрыть диалог',
                            ]
                        ) ?>
                    </div>
                </div>
                
                <!-- Сообщения -->
                <div class="messages-body">
                    <?php if (empty($messages)): ?>
                        <div class="text-center text-muted" style="padding: 40px 0;">
                            <p style="font-size: 48px;">💬</p>
                            <p>Начните диалог с <?= Html::encode($otherUser->username) ?></p>
                            <p class="small">Напишите первое сообщение</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $currentDate = '';
                        foreach ($messages as $message): 
                            $messageDate = Yii::$app->formatter->asDate($message->created_at, 'php:Y-m-d');
                            if ($currentDate !== $messageDate):
                                $currentDate = $messageDate;
                        ?>
                            <div class="message-date-divider">
                                <span><?= Yii::$app->formatter->asDate($message->created_at, 'long') ?></span>
                            </div>
                        <?php endif; ?>
                        <?= $this->render('_message', ['message' => $message]) ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Форма отправки -->
                <div class="message-input-container">
                    <?php $form = ActiveForm::begin([
                        'id' => 'message-form',
                        'action' => ['send-ajax'],
                        'options' => [
                            'data-conversation-id' => $conversation->id,
                            'class' => 'input-group',
                        ],
                    ]); ?>
                    
                    <?= Html::textarea('message', '', [
                        'id' => 'message-text',
                        'class' => 'form-control',
                        'placeholder' => 'Напишите сообщение...',
                        'rows' => 1,
                        'maxlength' => 1000,
                    ]) ?>
                    
                    <button type="submit" class="btn-send" title="Отправить (Ctrl+Enter)">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"/>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"/>
                        </svg>
                    </button>
                    
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>