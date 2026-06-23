<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\LoginForm;
use app\models\User;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;
use yii\web\ErrorAction;
use yii\captcha\CaptchaAction;

class SiteController extends Controller
{
    /**
     * {@inheritdoc}
     */
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function actions(): array
    {
        return [
            'error' => [
                'class' => ErrorAction::class,
            ],
            'captcha' => [
                'class' => CaptchaAction::class,
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     * Убираем тип возвращаемого значения или используем mixed
     */
    public function actionIndex()/*: string|Response*/
    {
        return $this->redirect(['advertisements/index']);
    }

    /**
     * Login action.
     */
    public function actionLogin(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            return $this->goBack();
        }

        $model->password = '';

        return $this->render('login', ['model' => $model]);
    }

    /**
     * Logout action.
     */
    public function actionLogout(): Response
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }

    /**
     * Register action.
     */
    public function actionRegister(): Response|string
    {
        $model = new User();
        $model->scenario = 'register';
        
        if ($model->load(Yii::$app->request->post()) && $model->validate()) {
            $model->setPassword($model->password);
            $model->generateAuthKey();
            $model->save(false);
            
            Yii::$app->session->setFlash('success', 'Регистрация успешно завершена! Теперь вы можете войти.');
            return $this->redirect(['login']);
        }
        
        return $this->render('register', [
            'model' => $model,
        ]);
    }
}