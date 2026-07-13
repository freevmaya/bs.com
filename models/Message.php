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
            
            $subject = "Новое сообщение от {$sender->username}";
            $message = $this->buildNotificationMessage($sender, $this->conversation->advertisement, $this->message);
            
            // Отправляем уведомление через все доступные каналы
            $manager->sendToUser(
                $receiver->id,
                'new_message',
                $subject,
                $message,
                ['html_body' => $this->buildHtmlNotificationMessage($sender, $this->conversation->advertisement, $this->message)]
            );
        } catch (\Exception $e) {
            Yii::error('Failed to send message notification: ' . $e->getMessage(), 'messages');
        }
    }
    
    protected function buildNotificationMessage($sender, $advertisement, $message)
    {
        $parts = [
            "Новое сообщение от {$sender->username}",
            "",
            "Объявление: " . ($advertisement ? $advertisement->title : 'не указано'),
            "",
            "Сообщение:",
            $message,
            "",
            "Перейти в диалог: " . Yii::$app->urlManager->createAbsoluteUrl(['messages/view', 'id' => $this->conversation_id]),
        ];
        
        return implode("\n", $parts);
    }
    
    protected function buildHtmlNotificationMessage($sender, $advertisement, $message)
    {
        $link = Yii::$app->urlManager->createAbsoluteUrl(['messages/view', 'id' => $this->conversation_id]);
        
        return "
            <html>
            <head><style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .message-box { background: #fff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff; margin: 10px 0; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; padding: 15px; color: #6c757d; font-size: 12px; }
            </style></head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📩 Новое сообщение</h2>
                    </div>
                    <div class='content'>
                        <p><strong>От:</strong> {$sender->username}</p>
                        <p><strong>Объявление:</strong> " . ($advertisement ? $advertisement->title : 'не указано') . "</p>
                        <div class='message-box'>
                            <strong>Сообщение:</strong><br>
                            " . nl2br($message) . "
                        </div>
                        <p style='margin-top: 20px;'>
                            <a href='{$link}' class='btn'>Перейти в диалог</a>
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