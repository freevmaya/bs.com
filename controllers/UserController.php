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
                'only' => ['profile', 'my-ads', 'subscriptions', 'notifications', 'edit', 'get-vk-id', 'change-password'],
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
     * ДОБАВЛЕНА АВТОМАТИЧЕСКАЯ ОБРАБОТКА VK URL
     */
    public function actionEdit()
    {
        $user = Yii::$app->user->identity;
        $user->scenario = 'update';

        if ($user->load(Yii::$app->request->post())) {
            // Если указан VK профиль, пытаемся получить ID
            if (!empty($user->vk_profile_url)) {
                $vkId = $this->extractVkIdFromUrl($user->vk_profile_url);
                if ($vkId) {
                    $user->vk_id = $vkId;
                    Yii::$app->session->setFlash('info', 'VK ID автоматически определен: ' . $vkId);
                } else {
                    // Если не удалось определить ID, оставляем поле пустым
                    $user->vk_id = null;
                    Yii::$app->session->setFlash('warning', 'Не удалось определить VK ID по ссылке. Проверьте правильность ссылки.');
                }
            } else {
                // Если URL пустой, очищаем ID
                $user->vk_id = null;
            }
            
            if ($user->save()) {
                Yii::$app->session->setFlash('success', 'Профиль обновлен');
                return $this->redirect(['profile']);
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка при сохранении профиля');
            }
        }

        return $this->render('edit', [
            'user' => $user,
        ]);
    }

    /**
     * Смена пароля
     */
    public function actionChangePassword()
    {
        $user = Yii::$app->user->identity;
        
        if (Yii::$app->request->isPost) {
            $oldPassword = Yii::$app->request->post('old_password');
            $newPassword = Yii::$app->request->post('new_password');
            $newPasswordRepeat = Yii::$app->request->post('new_password_repeat');
            
            // Проверяем старый пароль
            if (!$user->validatePassword($oldPassword)) {
                Yii::$app->session->setFlash('error', 'Неверный текущий пароль');
                return $this->render('change-password');
            }
            
            // Проверяем длину нового пароля
            if (strlen($newPassword) < 6) {
                Yii::$app->session->setFlash('error', 'Новый пароль должен содержать не менее 6 символов');
                return $this->render('change-password');
            }
            
            // Проверяем совпадение паролей
            if ($newPassword !== $newPasswordRepeat) {
                Yii::$app->session->setFlash('error', 'Пароли не совпадают');
                return $this->render('change-password');
            }
            
            // Устанавливаем новый пароль
            $user->setPassword($newPassword);
            $user->generateAuthKey();
            
            if ($user->save(false)) {
                Yii::$app->session->setFlash('success', 'Пароль успешно изменен');
                return $this->redirect(['profile']);
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка при сохранении пароля');
            }
        }
        
        return $this->render('change-password');
    }

    /**
     * AJAX проверка текущего пароля
     */
    public function actionCheckPassword()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $password = Yii::$app->request->post('password');
        $user = Yii::$app->user->identity;
        
        if (!$password) {
            return ['valid' => false, 'message' => 'Введите пароль'];
        }
        
        if ($user->validatePassword($password)) {
            return ['valid' => true];
        } else {
            return ['valid' => false, 'message' => 'Неверный пароль'];
        }
    }

    /**
     * Получение VK ID по ссылке (AJAX)
     */
    public function actionGetVkId()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $url = Yii::$app->request->post('url');
        if (!$url) {
            return ['success' => false, 'error' => 'URL не указан'];
        }
        
        $vkId = $this->extractVkIdFromUrl($url);
        
        if ($vkId) {
            return [
                'success' => true,
                'user_id' => $vkId,
            ];
        }
        
        return ['success' => false, 'error' => 'Не удалось определить VK ID'];
    }

    /**
     * Извлечение VK ID из URL
     * 
     * @param string $url
     * @return int|null
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

    /**
     * Извлечение screen_name из URL
     */
    private function extractScreenName($url)
    {
        try {
            $parts = parse_url($url);
            if (!isset($parts['path'])) {
                return null;
            }
            
            $hostname = strtolower($parts['host'] ?? '');

            if (strpos($hostname, 'vk.com') === false && strpos($hostname, 'vkontakte.ru') === false) {
                return null;
            }
            
            $path = trim($parts['path'], '/');
            if (!$path) {
                return null;
            }
            
            // Если путь начинается с id, берем все после id
            if (preg_match('/^id\d+$/', $path)) {
                return $path;
            }
            
            // Иначе берем имя пользователя
            $segments = explode('/', $path);
            return $segments[0];
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Получение user_id по screen_name через VK API
     * 
     * @param string $screenName
     * @return int|null
     */
    private function getUserIdByScreenName($screenName)
    {
        try {
            $accessToken = Yii::$app->params['vk_access_token'] ?? null;
        
            $url = 'https://api.vk.com/method/users.get?' . http_build_query([
                'user_ids' => $screenName,
                'v' => '5.131',
                'access_token' => $accessToken, // Добавляем токен!
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if ($httpCode !== 200) {
                Yii::error("VK API httpCode: {$httpCode}", 'notification');
                return null;
            }
            
            $data = json_decode($response, true);

            Yii::info('VK API response: ' . json_encode($data), 'notification');
            
            if (isset($data['response']) && !empty($data['response'])) {
                return (int)$data['response'][0]['id'];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}