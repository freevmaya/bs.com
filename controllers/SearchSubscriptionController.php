<?php
// FILE: .\controllers\SearchSubscriptionController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use app\models\SearchSubscription;
use app\models\AdvertisementSearch;

class SearchSubscriptionController extends Controller
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
     * Список подписок пользователя
     */
    public function actionIndex()
    {
        $userId = Yii::$app->user->id;
        $subscriptions = SearchSubscription::getUserSubscriptions($userId);

        return $this->render('index', [
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Создание подписки из параметров поиска
     * Исправлено: сохраняем все параметры, включая дополнительные поля
     */
    public function actionCreate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $params = Yii::$app->request->post('params');
        $section = Yii::$app->request->post('section');

        if (!$params || !$section) {
            return ['success' => false, 'error' => 'Не указаны параметры поиска'];
        }

        $userId = Yii::$app->user->id;

        // Проверяем, существует ли уже такая подписка
        $existing = SearchSubscription::find()
            ->where([
                'user_id' => $userId,
                'section' => $section,
                'is_active' => true,
            ])
            ->andWhere(['params' => json_encode($params, JSON_UNESCAPED_UNICODE)])
            ->one();

        if ($existing) {
            return ['success' => false, 'error' => 'Вы уже подписаны на эти параметры поиска'];
        }

        $subscription = new SearchSubscription();
        $subscription->user_id = $userId;
        $subscription->section = $section;
        $subscription->setParamsArray($params);
        $subscription->is_active = true;

        if ($subscription->save()) {
            return ['success' => true, 'message' => 'Подписка создана'];
        }

        return ['success' => false, 'error' => 'Ошибка при создании подписки: ' . json_encode($subscription->errors)];
    }

    /**
     * Отписка
     */
    public function actionDelete($id)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $subscription = $this->findModel($id);

        if ($subscription->user_id !== Yii::$app->user->id) {
            return ['success' => false, 'error' => 'У вас нет прав для удаления этой подписки'];
        }

        $subscription->is_active = false;
        if ($subscription->save()) {
            return ['success' => true, 'message' => 'Подписка удалена'];
        }

        return ['success' => false, 'error' => 'Ошибка при удалении подписки'];
    }

    protected function findModel($id)
    {
        $model = SearchSubscription::findOne($id);
        if (!$model) {
            throw new NotFoundHttpException('Подписка не найдена');
        }
        return $model;
    }
}