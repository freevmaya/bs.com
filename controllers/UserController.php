<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\web\NotFoundHttpException;
use app\models\User;
use app\models\Advertisement;
use yii\data\ActiveDataProvider;

class UserController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['profile', 'my-ads', 'subscriptions', 'notifications'],
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
     * Профиль пользователя
     */
    public function actionProfile()
    {
        $user = Yii::$app->user->identity;
        
        // Получаем объявления пользователя
        $adsDataProvider = new ActiveDataProvider([
            'query' => Advertisement::find()->where(['user_id' => $user->id]),
            'sort' => ['defaultOrder' => ['created_at' => SORT_DESC]],
            'pagination' => ['pageSize' => 5],
        ]);

        return $this->render('profile', [
            'user' => $user,
            'adsDataProvider' => $adsDataProvider,
        ]);
    }

    /**
     * Мои объявления (перенаправление для совместимости)
     */
    public function actionMyAds()
    {
        return $this->redirect(['profile']);
    }

    /**
     * Подписки пользователя
     */
    public function actionSubscriptions()
    {
        return $this->redirect(['/search-subscription/index']);
    }

    /**
     * Настройки уведомлений
     */
    public function actionNotifications()
    {
        return $this->redirect(['/notification/index']);
    }

    /**
     * Редактирование профиля
     */
    public function actionEdit()
    {
        $user = Yii::$app->user->identity;
        $user->scenario = 'update';

        if ($user->load(Yii::$app->request->post()) && $user->save()) {
            Yii::$app->session->setFlash('success', 'Профиль обновлен');
            return $this->redirect(['profile']);
        }

        return $this->render('edit', [
            'user' => $user,
        ]);
    }
}