<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class NotificationLog extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    
    public static function tableName()
    {
        return 'notification_logs';
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
            [['user_id', 'channel', 'event'], 'required'],
            [['user_id'], 'integer'],
            [['message', 'error'], 'string'],
            [['subject'], 'string', 'max' => 255],
            [['channel'], 'string', 'max' => 50],
            [['event'], 'string', 'max' => 100],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_SENT, self::STATUS_FAILED]],
            [['sent_at'], 'integer'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Пользователь',
            'channel' => 'Канал',
            'event' => 'Событие',
            'subject' => 'Тема',
            'message' => 'Сообщение',
            'status' => 'Статус',
            'error' => 'Ошибка',
            'created_at' => 'Создано',
            'sent_at' => 'Отправлено',
        ];
    }
    
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
    
    /**
     * Создать запись в логе
     */
    public static function createLog($userId, $channel, $event, $subject, $message)
    {
        $log = new static();
        $log->user_id = $userId;
        $log->channel = $channel;
        $log->event = $event;
        $log->subject = $subject;
        // Очищаем сообщение от эмодзи и других 4-байтовых символов
        $log->message = self::cleanUtf8String($message);
        $log->status = self::STATUS_PENDING;
        
        return $log;
    }

    /**
     * Очистка строки от 4-байтовых символов (эмодзи)
     */
    private static function cleanUtf8String($string)
    {
        if ($string === null || $string === '') {
            return $string;
        }
        
        // Удаляем 4-байтовые символы (эмодзи)
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $string);
    }
    
    /**
     * Отметить как отправленное
     */
    public function markAsSent()
    {
        $this->status = self::STATUS_SENT;
        $this->sent_at = time();
        return $this->save(false);
    }
    
    /**
     * Отметить как неудачное
     */
    public function markAsFailed($error = null)
    {
        $this->status = self::STATUS_FAILED;
        if ($error !== null) {
            $this->error = $error;
        }
        return $this->save(false);
    }
}