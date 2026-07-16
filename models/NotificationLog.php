<?php
// models/NotificationLog.php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class NotificationLog extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_QUEUED = 'queued';
    
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
            [['user_id', 'retry_count', 'queued_at', 'sent_at'], 'integer'],
            [['message', 'error', 'html_body'], 'string'],
            [['subject'], 'string', 'max' => 255],
            [['channel'], 'string', 'max' => 50],
            [['event'], 'string', 'max' => 100],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_SENT, self::STATUS_FAILED, self::STATUS_QUEUED]],
            [['retry_count'], 'default', 'value' => 0],
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
            'html_body' => 'HTML тело',
            'status' => 'Статус',
            'error' => 'Ошибка',
            'retry_count' => 'Попыток',
            'created_at' => 'Создано',
            'sent_at' => 'Отправлено',
            'queued_at' => 'В очереди с',
        ];
    }
    
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
    
    /**
     * Создать запись в логе в статусе QUEUED
     */
    public static function createQueued($userId, $channel, $event, $subject, $message, $options = [])
    {
        $log = new static();
        $log->user_id = $userId;
        $log->channel = $channel;
        $log->event = $event;
        $log->subject = self::cleanUtf8String($subject);
        $log->message = self::cleanUtf8String($message);
        $log->status = self::STATUS_QUEUED;
        $log->queued_at = time();
        $log->retry_count = 0;
        
        if (isset($options['html_body'])) {
            $log->html_body = self::cleanUtf8String($options['html_body']);
        }
        
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
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $string);
    }
    
    /**
     * Получить текстовое сообщение
     */
    public function getTextMessage()
    {
        return $this->message;
    }
    
    /**
     * Получить HTML тело сообщения
     */
    public function getHtmlBody()
    {
        return $this->html_body;
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
            $this->error = substr(self::cleanUtf8String($error), 0, 1000);
        }
        return $this->save(false);
    }
    
    /**
     * Увеличить счетчик попыток
     */
    public function incrementRetry()
    {
        $this->retry_count++;
        return $this->save(false, ['retry_count']);
    }
    
    /**
     * Получить записи для отправки (не более указанного количества)
     */
    public static function getPendingNotifications($limit = 100)
    {
        return static::find()
            ->where(['status' => self::STATUS_QUEUED])
            ->andWhere(['<', 'retry_count', 5])
            ->orderBy(['queued_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit($limit)
            ->all();
    }
    
    /**
     * Получить количество ожидающих уведомлений
     */
    public static function getPendingCount()
    {
        return static::find()
            ->where(['status' => self::STATUS_QUEUED])
            ->andWhere(['<', 'retry_count', 5])
            ->count();
    }
}