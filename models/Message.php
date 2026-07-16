<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Message extends ActiveRecord
{
    public static function tableName()
    {
        return 'messages';
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
            ],
        ];
    }
    
    public function rules()
    {
        return [
            [['conversation_id', 'sender_id', 'receiver_id', 'message'], 'required'],
            [['conversation_id', 'sender_id', 'receiver_id', 'read_at'], 'integer'],
            [['message'], 'string'],
            [['is_read'], 'boolean'],
            ['message', 'trim'],
            ['message', 'filter', 'filter' => function($value) {
                return strip_tags($value);
            }],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'conversation_id' => 'Диалог',
            'sender_id' => 'Отправитель',
            'receiver_id' => 'Получатель',
            'message' => 'Сообщение',
            'is_read' => 'Прочитано',
            'read_at' => 'Время прочтения',
            'created_at' => 'Отправлено',
        ];
    }
    
    public function getConversation()
    {
        return $this->hasOne(Conversation::class, ['id' => 'conversation_id']);
    }
    
    public function getSender()
    {
        return $this->hasOne(User::class, ['id' => 'sender_id']);
    }
    
    public function getReceiver()
    {
        return $this->hasOne(User::class, ['id' => 'receiver_id']);
    }
    
    public function afterSave($insert, $changedAttributes)
    {
        parent::afterSave($insert, $changedAttributes);
        
        if ($insert) {
            // Обновляем время последнего сообщения в диалоге
            $this->conversation->last_message_at = $this->created_at;
            $this->conversation->save(false);
            
            // Отправляем уведомление получателю
            $this->notifyReceiver();
        }
    }
    
    protected function notifyReceiver()
    {
        $receiver = $this->receiver;
        $sender = $this->sender;

        if (!$receiver || !$sender) {
            return;
        }

        try {
            $manager = Yii::$app->notificationManager;
            $advertisement = $this->conversation->advertisement;

            // Формируем тему и текст сообщения
            $subject = "Новое сообщение от {$sender->username}";
            $message = $this->buildNotificationMessage($sender, $advertisement);
            $htmlMessage = $this->buildHtmlNotificationMessage($sender, $advertisement);

            // Ставим уведомление в очередь через NotificationLog
            $this->queueNotification($receiver->id, $subject, $message, $htmlMessage);

        } catch (\Exception $e) {
            Yii::error('Failed to queue message notification: ' . $e->getMessage(), 'messages');
        }
    }

    /**
     * Добавляет уведомление о сообщении в очередь.
     *
     * @param int $userId ID получателя
     * @param string $subject Тема
     * @param string $message Текст сообщения
     * @param string $htmlMessage HTML версия
     */
    protected function queueNotification($userId, $subject, $message, $htmlMessage)
    {
        // Получаем активные подписки пользователя на событие 'new_message'
        $subscriptions = NotificationSubscription::getActiveSubscriptions($userId, 'new_message');

        if (empty($subscriptions)) {
            Yii::info("User {$userId} has no active subscriptions for event 'new_message'", 'messages');
            return;
        }

        $queued = 0;
        foreach ($subscriptions as $subscription) {
            $channelName = $subscription->channel;
            $channel = Yii::$app->notificationManager->getChannel($channelName);

            if (!$channel || !$channel->isAvailable()) {
                continue;
            }

            // Создаем запись в очереди
            $log = NotificationLog::createQueued(
                $userId,
                $channelName,
                'new_message',
                $subject,
                $message,
                ['html_body' => $htmlMessage]
            );

            if ($log->save()) {
                $queued++;
                Yii::info("Message notification queued for user {$userId} via '{$channelName}' (log_id: {$log->id})", 'messages');
            } else {
                Yii::error("Failed to queue message notification: " . json_encode($log->errors), 'messages');
            }
        }

        if ($queued === 0) {
            Yii::warning("No active channels found to queue message notification for user {$userId}", 'messages');
        }
    }
    
    /**
     * Сборка текстового сообщения для уведомления.
     *
     * @param User $sender Отправитель
     * @param Advertisement|null $advertisement Объявление
     * @return string
     */
    protected function buildNotificationMessage($sender, $advertisement)
    {
        $link = Yii::$app->urlManager->createAbsoluteUrl(['messages/view', 'id' => $this->conversation_id]);
        $adTitle = $advertisement ? $advertisement->title : 'не указано';
        $adLink = $advertisement ? Yii::$app->urlManager->createAbsoluteUrl(['advertisements/view', 'id' => $advertisement->id]) : null;

        $parts = [
            "Новое сообщение от {$sender->username}",
            "",
            "Объявление: {$adTitle}",
            "",
            "Сообщение:",
            $this->message,
            "",
            "Перейти в диалог: {$link}",
        ];
        
        // Добавляем ссылку на объявление, если она есть
        if ($adLink) {
            $parts[] = "Ссылка на объявление: {$adLink}";
        }

        return implode("\n", array_filter($parts));
    }
    
    /**
     * Сборка HTML сообщения для уведомления.
     *
     * @param User $sender Отправитель
     * @param Advertisement|null $advertisement Объявление
     * @return string
     */
    protected function buildHtmlNotificationMessage($sender, $advertisement)
    {
        $link = Yii::$app->urlManager->createAbsoluteUrl(['messages/view', 'id' => $this->conversation_id]);
        $adLink = $advertisement ? Yii::$app->urlManager->createAbsoluteUrl(['advertisements/view', 'id' => $advertisement->id]) : null;
        $adTitle = $advertisement ? $advertisement->title : 'не указано';

        return "
            <html>
            <head><style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .message-box { background: #fff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin: 10px 0; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 5px 5px 5px 0; }
                .footer { text-align: center; padding: 15px; color: #6c757d; font-size: 12px; }
            </style></head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📩 Новое сообщение</h2>
                    </div>
                    <div class='content'>
                        <p><strong>От:</strong> {$sender->username}</p>
                        <p><strong>Объявление:</strong> <a href='{$adLink}'>{$adTitle}</a></p>
                        <div class='message-box'>
                            <strong>Сообщение:</strong><br>
                            " . nl2br($this->message) . "
                        </div>
                        <p style='margin-top: 20px;'>
                            <a href='{$link}' class='btn'>Перейти в диалог</a>
                            " . ($adLink ? "<a href='{$adLink}' class='btn' style='background: #28a745;'>К объявлению</a>" : "") . "
                        </p>
                    </div>
                    <div class='footer'>
                        &copy; " . Yii::$app->name . " " . date('Y') . "
                    </div>
                </div>
            </body>
            </html>
        ";
    }
}