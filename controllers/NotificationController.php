<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\web\Response;
use app\models\NotificationSubscription;
use app\models\User;
use app\components\notifications\events\NewAdvertisementEvent;

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
        
        // Получаем доступные события и каналы
        $events = Yii::$app->notificationManager->getEvents();
        $channels = Yii::$app->notificationManager->getChannels();
        
        return $this->render('index', [
            'user' => $user,
            'subscriptions' => $subscriptions,
            'events' => $events,
            'channels' => $channels,
        ]);
    }
    
    /**
     * Подписаться на событие
     */
    public function actionSubscribe()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $event = Yii::$app->request->post('event');
        $channel = Yii::$app->request->post('channel');
        $userId = Yii::$app->user->id;
        
        if (!$event || !$channel) {
            return ['success' => false, 'error' => 'Не указаны событие или канал'];
        }
        
        try {
            $success = NotificationSubscription::subscribe($userId, $event, $channel);
            return ['success' => $success];
        } catch (\Exception $e) {
            Yii::error('Subscribe error: ' . $e->getMessage(), 'notification');
            return ['success' => false, 'error' => 'Ошибка при подписке: ' . $e->getMessage()];
        }
    }
    
    /**
     * Отписаться от события
     */
    public function actionUnsubscribe()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        
        $event = Yii::$app->request->post('event');
        $channel = Yii::$app->request->post('channel');
        $userId = Yii::$app->user->id;
        
        if (!$event || !$channel) {
            return ['success' => false, 'error' => 'Не указаны событие или канал'];
        }
        
        try {
            $success = NotificationSubscription::unsubscribe($userId, $event, $channel);
            return ['success' => $success];
        } catch (\Exception $e) {
            Yii::error('Unsubscribe error: ' . $e->getMessage(), 'notification');
            return ['success' => false, 'error' => 'Ошибка при отписке: ' . $e->getMessage()];
        }
    }
}