<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class NotificationSubscription extends ActiveRecord
{
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
}