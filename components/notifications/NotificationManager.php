<?php
// components/notifications/NotificationManager.php

namespace app\components\notifications;

use Yii;
use app\models\NotificationSubscription;
use app\models\NotificationLog;
use app\models\User;
use app\components\notifications\channels\NotificationChannelInterface;
use yii\base\Component;

class NotificationManager extends Component
{
    private $channels = [];
    private $events = [];
    private $logEnabled = true;
    
    /**
     * Инициализация компонента
     */
    public function init()
    {
        parent::init();
        
        // Регистрируем каналы уведомлений
        $this->registerChannel(new \app\components\notifications\channels\EmailChannel(Yii::$app->mailer));
        //$this->registerChannel(new \app\components\notifications\channels\SmsChannel());
        $this->registerChannel(new \app\components\notifications\channels\VkChannel());
        $this->registerChannel(new \app\components\notifications\channels\TelegramChannel());
        
        // Регистрируем WhatsApp только если настроен API ключ
        if ($this->isWhatsAppConfigured()) {
            $this->registerChannel(new \app\components\notifications\channels\WhatsAppChannel());
        }
        
        // Регистрируем все события
        $this->registerEvent('search_subscription', 'Новое объявление по критериям подписки');
        $this->registerEvent('new_advertisement', 'Новое объявление на сайте');
        $this->registerEvent('new_message', 'Новое сообщение в диалоге');
    }
    
    /**
     * Проверяет, настроен ли WhatsApp
     */
    public function isWhatsAppConfigured()
    {
        $apiKey = Yii::$app->params['whatsapp_api_key'] ?? null;
        $apiUrl = Yii::$app->params['whatsapp_api_url'] ?? null;
        
        return !empty($apiKey) && !empty($apiUrl) && 
               $apiKey !== 'ВАШ_WHATSAAP_API_KEY' && 
               $apiUrl !== 'https://whatsapp-api.example.com/send';
    }
    
    /**
     * Получить только доступные каналы (без неактивных)
     */
    public function getAvailableChannels()
    {
        $channels = [];
        foreach ($this->channels as $key => $channel) {
            if ($channel->isAvailable()) {
                $channels[$key] = $channel;
            }
        }
        return $channels;
    }
    
    /**
     * Регистрация канала
     */
    public function registerChannel(NotificationChannelInterface $channel)
    {
        $this->channels[$channel->getName()] = $channel;
        return $this;
    }
    
    /**
     * Получить канал по имени
     */
    public function getChannel($name)
    {
        return $this->channels[$name] ?? null;
    }
    
    /**
     * Получить все зарегистрированные каналы
     */
    public function getChannels()
    {
        return $this->channels;
    }
    
    /**
     * Регистрация события
     */
    public function registerEvent($eventKey, $eventLabel)
    {
        $this->events[$eventKey] = $eventLabel;
        return $this;
    }
    
    /**
     * Получить все события
     */
    public function getEvents()
    {
        return $this->events;
    }
    
    /**
     * Отправить уведомление пользователю
     */
    public function sendToUser($userId, $event, $subject, $message, $options = [])
    {
        $user = User::findOne($userId);
        if (!$user) {
            Yii::warning("User {$userId} not found", 'notification');
            return false;
        }
        
        // Получаем активные подписки пользователя на это событие
        $subscriptions = NotificationSubscription::getActiveSubscriptions($userId, $event);
        
        if (empty($subscriptions)) {
            Yii::info("User {$userId} has no active subscriptions for event '{$event}'", 'notification');
            return false;
        }
        
        $results = [];
        foreach ($subscriptions as $subscription) {
            $channelName = $subscription->channel;
            $channel = $this->getChannel($channelName);
            
            if (!$channel) {
                Yii::warning("Channel '{$channelName}' not found", 'notification');
                continue;
            }
            
            if (!$channel->isAvailable()) {
                Yii::warning("Channel '{$channelName}' is not available", 'notification');
                continue;
            }
            
            // Определяем получателя для канала
            $to = $this->getRecipient($user, $channelName);
            if (!$to) {
                Yii::warning("No recipient found for channel '{$channelName}'", 'notification');
                continue;
            }
            
            // Создаем лог
            $log = NotificationLog::createLog(
                $userId,
                $channelName,
                $event,
                $subject,
                $message
            );
            
            // Отправляем
            try {
                $success = $channel->send($to, $subject, $message, $options);
                
                if ($success) {
                    $log->markAsSent();
                    $results[$channelName] = true;
                    Yii::info("Notification sent via '{$channelName}' to user {$userId}", 'notification');
                } else {
                    $log->markAsFailed('Send failed');
                    $results[$channelName] = false;
                    Yii::error("Notification failed via '{$channelName}' to user {$userId}", 'notification');
                }
            } catch (\Exception $e) {
                $log->markAsFailed($e->getMessage());
                $results[$channelName] = false;
                Yii::error("Notification exception via '{$channelName}': " . $e->getMessage(), 'notification');
            }
        }
        
        return $results;
    }
    
    /**
     * Отправить уведомление всем подписчикам события
     */
    public function sendToSubscribers($event, $subject, $message, $options = [])
    {
        $subscriptions = NotificationSubscription::getSubscribersForEvent($event);
        
        if (empty($subscriptions)) {
            Yii::info("No subscribers for event '{$event}'", 'notification');
            return ['total' => 0, 'sent' => 0];
        }
        
        $total = count($subscriptions);
        $sent = 0;
        
        foreach ($subscriptions as $subscription) {
            $result = $this->sendToUser(
                $subscription->user_id,
                $event,
                $subject,
                $message,
                $options
            );
            
            if ($result && in_array(true, $result)) {
                $sent++;
            }
        }
        
        return [
            'total' => $total,
            'sent' => $sent,
        ];
    }
    
    /**
     * Получить получателя для канала
     */
    protected function getRecipient($user, $channelName)
    {
        switch ($channelName) {
            case 'email':
                return $user->email;
            case 'sms':
                return $user->phone;
            case 'vk':
                return $user->vk_id ?? null;
            case 'telegram':
                // Сначала пробуем использовать chat_id, если есть
                if (!empty($user->telegram_chat_id)) {
                    return $user->telegram_chat_id;
                }
                // Иначе используем username
                return $user->telegram ?? null;
            case 'whatsapp':
                return $user->whatsapp;
            default:
                return null;
        }
    }
    
    /**
     * Включить/выключить логирование
     */
    public function setLogEnabled($enabled)
    {
        $this->logEnabled = $enabled;
        return $this;
    }
}