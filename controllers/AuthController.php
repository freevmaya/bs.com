<?php
// controllers/AuthController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\authclient\ClientInterface;
use yii\authclient\AuthAction;
use app\components\AuthHandler;

class AuthController extends Controller
{
    public function actions()
    {
        return [
            'auth' => [
                'class' => AuthAction::class,
                'successCallback' => [$this, 'onAuthSuccess'],
                'successUrl' => Yii::$app->user->getReturnUrl(),
                'cancelUrl' => ['site/login'],
            ],
        ];
    }

    /**
     * Обработка успешной авторизации через соцсеть
     * 
     * @param ClientInterface $client
     */
    public function onAuthSuccess(ClientInterface $client)
    {
        try {
            $handler = new AuthHandler($client);
            $user = $handler->handle();
            
            Yii::$app->session->setFlash('success', 'Добро пожаловать, ' . $user->username . '!');
            
        } catch (\Exception $e) {
            Yii::error('Auth error: ' . $e->getMessage(), 'auth');
            Yii::$app->session->setFlash('error', $e->getMessage());
            
            // Перенаправляем на страницу входа
            Yii::$app->response->redirect(['site/login']);
        }
    }
}