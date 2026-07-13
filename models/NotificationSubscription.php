<?php
// FILE: .\models\NotificationSubscription.php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class NotificationSubscription extends ActiveRecord
{
    // Список доступных событий
    const EVENT_SEARCH_SUBSCRIPTION = 'search_subscription';
    const EVENT_NEW_ADVERTISEMENT = 'new_advertisement';
    const EVENT_NEW_MESSAGE = 'new_message';
    
    // Список доступных каналов
    const CHANNEL_EMAIL = 'email';
    const CHANNEL_SMS = 'sms';
    const CHANNEL_VK = 'vk';
    const CHANNEL_TELEGRAM = 'telegram';
    const CHANNEL_WHATSAPP = 'whatsapp';
    
    public static function tableName()
    {
        return 'notification_subscriptions';
    }
    
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }
    
    public function rules()
    {
        return [
            [['user_id', 'event', 'channel'], 'required'],
            [['user_id'], 'integer'],
            [['is_active'], 'boolean'],
            [['settings'], 'safe'],
            [['event'], 'string', 'max' => 100],
            [['channel'], 'string', 'max' => 50],
            [['user_id', 'event', 'channel'], 'unique', 'targetAttribute' => ['user_id', 'event', 'channel']],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Пользователь',
            'event' => 'Событие',
            'channel' => 'Канал',
            'is_active' => 'Активна',
            'settings' => 'Настройки',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }
    
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
    
    /**
     * Получить все активные подписки для пользователя
     */
    public static function getActiveSubscriptions($userId, $event = null)
    {
        $query = static::find()
            ->where(['user_id' => $userId, 'is_active' => true]);
        
        if ($event) {
            $query->andWhere(['event' => $event]);
        }
        
        return $query->all();
    }
    
    /**
     * Получить всех пользователей, подписанных на событие
     */
    public static function getSubscribersForEvent($event, $channel = null)
    {
        $query = static::find()
            ->where(['event' => $event, 'is_active' => true])
            ->joinWith('user');
        
        if ($channel) {
            $query->andWhere(['channel' => $channel]);
        }
        
        return $query->all();
    }
    
    /**
     * Подписать пользователя на событие
     */
    public static function subscribe($userId, $event, $channel, $settings = null)
    {
        $subscription = static::findOne([
            'user_id' => $userId,
            'event' => $event,
            'channel' => $channel,
        ]);
        
        if (!$subscription) {
            $subscription = new static();
            $subscription->user_id = $userId;
            $subscription->event = $event;
            $subscription->channel = $channel;
        }
        
        $subscription->is_active = true;
        if ($settings !== null) {
            $subscription->settings = $settings;
        }
        
        return $subscription->save();
    }
    
    /**
     * Отписать пользователя от события
     */
    public static function unsubscribe($userId, $event, $channel)
    {
        $subscription = static::findOne([
            'user_id' => $userId,
            'event' => $event,
            'channel' => $channel,
        ]);
        
        if ($subscription) {
            $subscription->is_active = false;
            return $subscription->save();
        }
        
        return true;
    }

    /**
     * Получить статус подписки пользователя по каналу
     */
    public static function getChannelStatus($userId, $channel)
    {
        $count = static::find()
            ->where([
                'user_id' => $userId,
                'channel' => $channel,
                'is_active' => true,
            ])
            ->count();
        
        return $count > 0;
    }

    /**
     * Включить канал для пользователя (подписать на все события)
     */
    public static function enableChannel($userId, $channel)
    {
        $events = [
            self::EVENT_SEARCH_SUBSCRIPTION,
            self::EVENT_NEW_ADVERTISEMENT,
            self::EVENT_NEW_MESSAGE,
        ];
        
        $success = true;
        foreach ($events as $event) {
            if (!self::subscribe($userId, $event, $channel)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Выключить канал для пользователя (отписать от всех событий)
     */
    public static function disableChannel($userId, $channel)
    {
        $events = [
            self::EVENT_SEARCH_SUBSCRIPTION,
            self::EVENT_NEW_ADVERTISEMENT,
            self::EVENT_NEW_MESSAGE,
        ];
        
        $success = true;
        foreach ($events as $event) {
            if (!self::unsubscribe($userId, $event, $channel)) {
                $success = false;
            }
        }
        
        return $success;
    }

    /**
     * Получить список всех событий
     */
    public static function getEventsList()
    {
        return [
            self::EVENT_SEARCH_SUBSCRIPTION => 'Новые объявления по подписке',
            self::EVENT_NEW_ADVERTISEMENT => 'Новые объявления на сайте',
            self::EVENT_NEW_MESSAGE => 'Новые сообщения',
        ];
    }

    /**
     * Получить список всех каналов
     */
    public static function getChannelsList()
    {
        return [
            self::CHANNEL_EMAIL => 'Email',
            self::CHANNEL_SMS => 'SMS',
            self::CHANNEL_VK => 'VK',
            self::CHANNEL_TELEGRAM => 'Telegram',
            self::CHANNEL_WHATSAPP => 'WhatsApp',
        ];
    }

    /**
     * Получить описание канала
     */
    public static function getChannelDescription($channel)
    {
        $descriptions = [
            self::CHANNEL_EMAIL => 'Получать уведомления на электронную почту',
            self::CHANNEL_SMS => 'Получать уведомления по SMS',
            self::CHANNEL_VK => 'Получать уведомления в VK',
            self::CHANNEL_TELEGRAM => 'Получать уведомления в Telegram',
            self::CHANNEL_WHATSAPP => 'Получать уведомления в WhatsApp',
        ];
        return $descriptions[$channel] ?? $channel;
    }

    /**
     * Проверить, доступен ли канал для пользователя
     */
    public static function isChannelAvailableForUser($user, $channel)
    {
        switch ($channel) {
            case self::CHANNEL_EMAIL:
                return !empty($user->email);
            case self::CHANNEL_SMS:
                return !empty($user->phone);
            case self::CHANNEL_VK:
                return !empty($user->vk_profile_url);
            case self::CHANNEL_TELEGRAM:
                return !empty($user->telegram);
            case self::CHANNEL_WHATSAPP:
                return !empty($user->whatsapp);
            default:
                return false;
        }
    }
}