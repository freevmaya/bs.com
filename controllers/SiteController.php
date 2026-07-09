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
            // Если указан VK профиль, пытаемся получить ID
            if (!empty($model->vk_profile_url)) {
                $vkId = $this->extractVkIdFromUrl($model->vk_profile_url);
                if ($vkId) {
                    $model->vk_id = $vkId;
                }
            }
            
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

    /**
     * Извлечение VK ID из URL
     */
    private function extractVkIdFromUrl($url)
    {
        // Извлекаем screen_name из URL
        $screenName = $this->extractScreenName($url);
        if (!$screenName) {
            return null;
        }
        
        // Если это уже ID (начинается с id), возвращаем число
        if (preg_match('/^id(\d+)$/', $screenName, $matches)) {
            return (int)$matches[1];
        }
        
        // Пробуем получить ID через VK API
        return $this->getUserIdByScreenName($screenName);
    }

    private function extractScreenName($url)
    {
        try {
            $parts = parse_url($url);
            if (!isset($parts['path'])) {
                return null;
            }
            $path = trim($parts['path'], '/');
            if (!$path) {
                return null;
            }
            if (preg_match('/^id\d+$/', $path)) {
                return $path;
            }
            $segments = explode('/', $path);
            return $segments[0];
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getUserIdByScreenName($screenName)
    {
        try {
            $url = 'https://api.vk.com/method/users.get?' . http_build_query([
                'user_ids' => $screenName,
                'v' => '5.131',
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            
            if (isset($data['response']) && !empty($data['response'])) {
                return (int)$data['response'][0]['id'];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}