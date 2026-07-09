<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\web\Response;
use app\models\NotificationSubscription;
use app\models\User;

class NotificationController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
        ];
    }
    
    /**
     * Управление подписками
     */
    public function actionIndex()
    {
        $userId = Yii::$app->user->id;
        $user = User::findOne($userId);
        
        // Получаем все подписки пользователя
        $subscriptions = NotificationSubscription::find()
            ->where(['user_id' => $userId])
            ->indexBy(function($model) {
                return $model->event . '_' . $model->channel;
            })
            ->all();
        
        // Получаем доступные события и каналы из менеджера
        $events = Yii::$app->notificationManager->getEvents();
        $channels = Yii::$app->notificationManager->getChannels();
        
        // Формируем данные для представления
        $channelData = [];
        foreach ($channels as $channelKey => $channel) {
            // Проверяем, подписан ли пользователь хотя бы на одно событие через этот канал
            $isActive = NotificationSubscription::getChannelStatus($userId, $channelKey);
            $isAvailable = NotificationSubscription::isChannelAvailableForUser($user, $channelKey);
            
            $channelData[$channelKey] = [
                'label' => $channel->getDescription(),
                'description' => $channel->getDescription(),
                'isActive' => $isActive,
                'isAvailable' => $isAvailable,
                'contactInfo' => $this->getContactInfo($user, $channelKey),
            ];
        }
        
        return $this->render('index', [
            'user' => $user,
            'channels' => $channelData,
            'events' => $events,
        ]);
    }
    
    /**
     * Получить контактную информацию для канала
     */
    private function getContactInfo($user, $channel)
    {
        switch ($channel) {
            case NotificationSubscription::CHANNEL_EMAIL:
                return $user->email ?: 'не указан';
            case NotificationSubscription::CHANNEL_SMS:
                return $user->phone ?: 'не указан';
            case NotificationSubscription::CHANNEL_VK:
                // Теперь используем vk_profile_url
                return $user->vk_profile_url ?: 'не указан';
            default:
                return null;
        }
    }
    
    /**
     * Включить канал уведомлений (подписать на все события)
     */
    public function actionEnableChannel()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $channel = Yii::$app->request->post('channel');
        $userId = Yii::$app->user->id;
        
        if (!$channel) {
            return ['success' => false, 'error' => 'Не указан канал'];
        }
        
        // Проверяем, доступен ли канал для пользователя
        $user = User::findOne($userId);
        if (!NotificationSubscription::isChannelAvailableForUser($user, $channel)) {
            return ['success' => false, 'error' => 'Контактные данные для этого канала не заполнены'];
        }
        
        try {
            $success = NotificationSubscription::enableChannel($userId, $channel);
            if ($success) {
                Yii::info("Channel {$channel} enabled for user {$userId}", 'notification');
                return ['success' => true, 'message' => 'Уведомления включены'];
            } else {
                return ['success' => false, 'error' => 'Ошибка при включении уведомлений'];
            }
        } catch (\Exception $e) {
            Yii::error('Enable channel error: ' . $e->getMessage(), 'notification');
            return ['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()];
        }
    }
    
    /**
     * Выключить канал уведомлений (отписать от всех событий)
     */
    public function actionDisableChannel()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $channel = Yii::$app->request->post('channel');
        $userId = Yii::$app->user->id;
        
        if (!$channel) {
            return ['success' => false, 'error' => 'Не указан канал'];
        }
        
        try {
            $success = NotificationSubscription::disableChannel($userId, $channel);
            if ($success) {
                Yii::info("Channel {$channel} disabled for user {$userId}", 'notification');
                return ['success' => true, 'message' => 'Уведомления выключены'];
            } else {
                return ['success' => false, 'error' => 'Ошибка при выключении уведомлений'];
            }
        } catch (\Exception $e) {
            Yii::error('Disable channel error: ' . $e->getMessage(), 'notification');
            return ['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()];
        }
    }
}