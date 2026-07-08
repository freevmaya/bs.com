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
     * Исправлено: сохраняем параметры в правильном формате с логированием
     */
    public function actionCreate()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $params = Yii::$app->request->post('params');
        $section = Yii::$app->request->post('section');

        // Логируем полученные данные
        Yii::info('SearchSubscription create request', 'search_subscription');
        Yii::info('Params: ' . json_encode($params), 'search_subscription');
        Yii::info('Section: ' . $section, 'search_subscription');

        if (!$params || !$section) {
            Yii::warning('Missing params or section', 'search_subscription');
            return ['success' => false, 'error' => 'Не указаны параметры поиска'];
        }

        $userId = Yii::$app->user->id;

        // Очищаем параметры от префикса AdvertisementSearch[]
        $cleanedParams = [];
        foreach ($params as $key => $value) {
            // Удаляем префикс AdvertisementSearch[ и ] из ключа, если он есть
            $cleanKey = $key;
            if (strpos($key, 'AdvertisementSearch[') === 0) {
                $cleanKey = str_replace(['AdvertisementSearch[', ']'], '', $key);
            }
            
            // Если значение - массив, очищаем каждый элемент
            if (is_array($value)) {
                $filtered = array_filter($value, function($item) {
                    return $item !== '' && $item !== null && $item !== '0';
                });
                if (!empty($filtered)) {
                    $cleanedParams[$cleanKey] = array_values($filtered);
                }
            } else {
                // Пропускаем пустые значения
                if ($value === '' || $value === null || $value === '0') {
                    continue;
                }
                $cleanedParams[$cleanKey] = $value;
            }
        }

        // Логируем очищенные параметры
        Yii::info('Cleaned params: ' . json_encode($cleanedParams), 'search_subscription');

        // Проверяем, есть ли вообще параметры после очистки
        if (empty($cleanedParams)) {
            Yii::warning('No significant params after cleaning', 'search_subscription');
            return ['success' => false, 'error' => 'Нет значимых параметров для подписки'];
        }

        // Проверяем, существует ли уже такая подписка
        $existing = SearchSubscription::find()
            ->where([
                'user_id' => $userId,
                'section' => $section,
                'is_active' => true,
            ])
            ->andWhere(['params' => json_encode($cleanedParams, JSON_UNESCAPED_UNICODE)])
            ->one();

        if ($existing) {
            return ['success' => false, 'error' => 'Вы уже подписаны на эти параметры поиска'];
        }

        $subscription = new SearchSubscription();
        $subscription->user_id = $userId;
        $subscription->section = $section;
        $subscription->setParamsArray($cleanedParams);
        $subscription->is_active = true;

        if ($subscription->save()) {
            Yii::info('Subscription created: ' . $subscription->id, 'search_subscription');
            return ['success' => true, 'message' => 'Подписка создана'];
        }

        Yii::error('Failed to save subscription: ' . json_encode($subscription->errors), 'search_subscription');
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