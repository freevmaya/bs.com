<?php
// FILE: .\controllers\SiteController.php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\LoginForm;
use app\models\User;
use app\models\Advertisement;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\Response;
use yii\web\ErrorAction;
use yii\captcha\CaptchaAction;

class SiteController extends Controller
{
    /**
     * Отключаем CSRF для регистрации (чтобы работало с GET-параметром token)
     */
    public function beforeAction($action)
    {
        if (in_array($action->id, ['register', 'register-invitation'])) {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

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

    public function actionIndex(): Response
    {
        return $this->redirect(['advertisements/index']);
    }

    public function actionLogin(): Response|string
    {
        if (!Yii::$app->user->isGuest) {
            $invitationToken = Yii::$app->session->get('invitation_token');
            if ($invitationToken) {
                $advertisement = Advertisement::find()
                    ->where(['invitation_token' => $invitationToken])
                    ->andWhere(['status' => Advertisement::STATUS_ACTIVE])
                    ->one();
                
                if ($advertisement && $advertisement->isInvitationTokenValid()) {
                    $advertisement->user_id = Yii::$app->user->id;
                    $advertisement->invitation_token = null;
                    $advertisement->invitation_token_created_at = null;
                    $advertisement->save(false);
                    
                    Yii::$app->session->remove('invitation_token');
                    Yii::$app->session->setFlash('success', 'Вы стали владельцем объявления!');
                    return $this->redirect(['advertisements/view', 'id' => $advertisement->id]);
                }
                Yii::$app->session->remove('invitation_token');
            }
            return $this->goHome();
        }

        $model = new LoginForm();

        if ($model->load(Yii::$app->request->post()) && $model->login()) {
            $invitationToken = Yii::$app->session->get('invitation_token');
            if ($invitationToken) {
                $advertisement = Advertisement::find()
                    ->where(['invitation_token' => $invitationToken])
                    ->andWhere(['status' => Advertisement::STATUS_ACTIVE])
                    ->one();
                
                if ($advertisement && $advertisement->isInvitationTokenValid()) {
                    $advertisement->user_id = Yii::$app->user->id;
                    $advertisement->invitation_token = null;
                    $advertisement->invitation_token_created_at = null;
                    $advertisement->save(false);
                    
                    Yii::$app->session->remove('invitation_token');
                    Yii::$app->session->setFlash('success', 'Вы стали владельцем объявления!');
                    return $this->redirect(['advertisements/view', 'id' => $advertisement->id]);
                }
                Yii::$app->session->remove('invitation_token');
            }
            return $this->goBack();
        }

        $model->password = '';
        return $this->render('login', ['model' => $model]);
    }

    public function actionLogout(): Response
    {
        Yii::$app->user->logout();
        return $this->goHome();
    }

    /**
     * Register action.
     * 
     * @param string|null $token Токен приглашения
     */
    public function actionRegister($token = null): Response|string
    {
        Yii::info('=== REGISTER ACTION START ===', 'registration');
        Yii::info('Token from GET: ' . ($token ?? 'null'), 'registration');
        
        $model = new User();
        $model->scenario = 'register';
        
        // Проверяем токен в GET или сессии
        $invitationToken = null;
        
        if ($token) {
            // Проверяем токен из GET
            $advertisement = Advertisement::find()
                ->where(['invitation_token' => $token])
                ->andWhere(['status' => Advertisement::STATUS_ACTIVE])
                ->one();
            
            if ($advertisement && $advertisement->isInvitationTokenValid()) {
                $invitationToken = $token;
                Yii::$app->session->set('invitation_token', $token);
                Yii::info('Token from GET is valid: ' . $token, 'registration');
            } else {
                Yii::warning('Token from GET is invalid or expired: ' . $token, 'registration');
                Yii::$app->session->setFlash('error', 'Ссылка приглашения недействительна или устарела.');
            }
        } else {
            $sessionToken = Yii::$app->session->get('invitation_token');
            if ($sessionToken) {
                $advertisement = Advertisement::find()
                    ->where(['invitation_token' => $sessionToken])
                    ->andWhere(['status' => Advertisement::STATUS_ACTIVE])
                    ->one();
                
                if ($advertisement && $advertisement->isInvitationTokenValid()) {
                    $invitationToken = $sessionToken;
                    Yii::info('Token from session is valid: ' . $sessionToken, 'registration');
                } else {
                    Yii::$app->session->remove('invitation_token');
                    Yii::warning('Token from session is invalid: ' . $sessionToken, 'registration');
                }
            }
        }

        if ($model->load(Yii::$app->request->post())) {
            Yii::info('POST data loaded', 'registration');
            
            if ($model->validate()) {
                Yii::info('Model validation PASSED', 'registration');
                
                if (!empty($model->vk_profile_url)) {
                    $vkId = $this->extractVkIdFromUrl($model->vk_profile_url);
                    if ($vkId) {
                        $model->vk_id = $vkId;
                    }
                }
                
                $model->setPassword($model->password);
                $model->generateAuthKey();
                
                if ($model->save()) {
                    Yii::info('User saved successfully! ID: ' . $model->id, 'registration');
                    
                    $this->subscribeToNotifications($model->id);
                    
                    if ($invitationToken) {
                        Yii::info('Processing invitation token: ' . $invitationToken, 'registration');
                        
                        $advertisement = Advertisement::find()
                            ->where(['invitation_token' => $invitationToken])
                            ->andWhere(['status' => Advertisement::STATUS_ACTIVE])
                            ->one();
                        
                        if ($advertisement && $advertisement->isInvitationTokenValid()) {
                            Yii::info('Found advertisement #' . $advertisement->id, 'registration');
                            
                            $advertisement->user_id = $model->id;
                            $advertisement->invitation_token = null;
                            $advertisement->invitation_token_created_at = null;
                            $advertisement->save(false);
                            
                            Yii::$app->session->remove('invitation_token');
                            Yii::$app->user->login($model, 3600 * 24 * 30);
                            
                            Yii::$app->session->setFlash('success', 'Регистрация успешно завершена! Вы стали владельцем объявления.');
                            return $this->redirect(['advertisements/view', 'id' => $advertisement->id]);
                        } else {
                            Yii::warning('Advertisement not found for token: ' . $invitationToken, 'registration');
                            Yii::$app->session->remove('invitation_token');
                            Yii::$app->session->setFlash('warning', 'Ссылка приглашения устарела, но регистрация успешно завершена.');
                        }
                    }
                    
                    Yii::$app->user->login($model, 3600 * 24 * 30);
                    Yii::$app->session->setFlash('success', 'Регистрация успешно завершена!');
                    return $this->redirect(['/user/profile']);
                } else {
                    Yii::error('Failed to save user: ' . json_encode($model->errors), 'registration');
                    Yii::$app->session->setFlash('error', 'Ошибка при сохранении пользователя: ' . json_encode($model->errors));
                }
            } else {
                Yii::warning('Model validation FAILED: ' . json_encode($model->errors), 'registration');
                
                $errors = [];
                foreach ($model->errors as $field => $fieldErrors) {
                    $errors[] = $field . ': ' . implode(', ', $fieldErrors);
                }
                Yii::$app->session->setFlash('error', 'Пожалуйста, исправьте ошибки: ' . implode('; ', $errors));
            }
        }
        
        return $this->render('register', [
            'model' => $model,
            'invitationToken' => $invitationToken,
        ]);
    }

    /**
     * Регистрация по приглашению
     */
    public function actionRegisterInvitation($token)
    {
        Yii::info('=== REGISTER INVITATION ===', 'registration');
        Yii::info('Token: ' . $token, 'registration');
        
        $advertisement = Advertisement::find()
            ->where(['invitation_token' => $token])
            ->andWhere(['status' => Advertisement::STATUS_ACTIVE])
            ->one();
        
        if (!$advertisement) {
            Yii::warning('Advertisement not found for token: ' . $token, 'registration');
            Yii::$app->session->setFlash('error', 'Ссылка приглашения недействительна.');
            return $this->redirect(['site/login']);
        }
        
        if (!$advertisement->isInvitationTokenValid()) {
            Yii::warning('Token expired for advertisement #' . $advertisement->id, 'registration');
            Yii::$app->session->setFlash('error', 'Срок действия ссылки истек.');
            return $this->redirect(['site/login']);
        }
        
        if (!Yii::$app->user->isGuest) {
            Yii::info('User already logged in, assigning advertisement #' . $advertisement->id, 'registration');
            
            $advertisement->user_id = Yii::$app->user->id;
            $advertisement->invitation_token = null;
            $advertisement->invitation_token_created_at = null;
            $advertisement->save(false);
            
            Yii::$app->session->setFlash('success', 'Вы стали владельцем объявления!');
            return $this->redirect(['advertisements/view', 'id' => $advertisement->id]);
        }
        
        // ✅ Перенаправляем на регистрацию с токеном
        Yii::info('Redirecting to registration with token: ' . $token, 'registration');
        return $this->redirect(['site/register', 'token' => $token]);
    }

    private function extractVkIdFromUrl($url)
    {
        $screenName = $this->extractScreenName($url);
        if (!$screenName) {
            return null;
        }
        
        if (preg_match('/^id(\d+)$/', $screenName, $matches)) {
            return (int)$matches[1];
        }
        
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
            $accessToken = Yii::$app->params['vk_access_token'] ?? null;
            
            $url = 'https://api.vk.com/method/users.get?' . http_build_query([
                'user_ids' => $screenName,
                'v' => '5.131',
                'access_token' => $accessToken,
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

    private function subscribeToNotifications($userId)
    {
        try {
            $events = [
                \app\models\NotificationSubscription::EVENT_SEARCH_SUBSCRIPTION,
                \app\models\NotificationSubscription::EVENT_NEW_ADVERTISEMENT,
                \app\models\NotificationSubscription::EVENT_NEW_MESSAGE,
            ];
            
            foreach ($events as $event) {
                \app\models\NotificationSubscription::subscribe(
                    $userId,
                    $event,
                    \app\models\NotificationSubscription::CHANNEL_EMAIL
                );
            }
            
            Yii::info("User {$userId} subscribed to email notifications", 'auth');
        } catch (\Exception $e) {
            Yii::error("Failed to subscribe user {$userId} to notifications: " . $e->getMessage(), 'auth');
        }
    }
}