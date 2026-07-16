<?php
// components/AuthHandler.php

namespace app\components;

use Yii;
use yii\authclient\ClientInterface;
use yii\helpers\ArrayHelper;
use app\models\User;
use app\models\NotificationSubscription;

class AuthHandler
{
    /**
     * @var ClientInterface
     */
    private $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * Обработка успешной аутентификации
     * 
     * @return User|null
     * @throws \Exception
     */
    public function handle()
    {
        $attributes = $this->client->getUserAttributes();
        
        // Логируем полученные данные для отладки
        Yii::info('Auth attributes: ' . json_encode($attributes), 'auth');
        
        $provider = $this->client->getName();
        $email = $this->getEmail($attributes);
        $username = $this->getUsername($attributes);
        $firstName = $this->getFirstName($attributes);
        $lastName = $this->getLastName($attributes);
        $photo = $this->getPhoto($attributes);
        $providerId = $this->getProviderId($attributes);
        
        // Если email не получен, пробуем найти пользователя по provider_id
        if (empty($email)) {
            $user = $this->findUserByProviderId($provider, $providerId);
            if ($user) {
                Yii::$app->user->login($user, 3600 * 24 * 30);
                return $user;
            }
            
            Yii::error("Email not found for provider: {$provider}", 'auth');
            throw new \Exception('Не удалось получить email от сервиса авторизации. Пожалуйста, используйте другой способ входа.');
        }
        
        // Ищем пользователя по email
        $user = User::find()->where(['email' => $email])->one();
        
        if ($user) {
            // Пользователь найден - обновляем данные
            $this->updateUser($user, $attributes, $provider, $providerId);
            Yii::$app->user->login($user, 3600 * 24 * 30);
            return $user;
        }
        
        // Проверяем, не занято ли имя пользователя
        $username = $this->generateUniqueUsername($username, $email);
        
        // Создаем нового пользователя
        $user = $this->createUser([
            'username' => $username,
            'email' => $email,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'photo' => $photo,
            'provider' => $provider,
            'provider_id' => $providerId,
        ]);
        
        Yii::$app->user->login($user, 3600 * 24 * 30);
        return $user;
    }

    /**
     * Получение email из атрибутов
     */
    private function getEmail($attributes)
    {
        $fields = ['email', 'email_verified', 'default_email'];
        foreach ($fields as $field) {
            if (!empty($attributes[$field])) {
                return $attributes[$field];
            }
        }
        
        // Для VK
        if (!empty($attributes['email'])) {
            return $attributes['email'];
        }
        
        // Для Google
        if (!empty($attributes['emails']) && is_array($attributes['emails'])) {
            foreach ($attributes['emails'] as $emailData) {
                if (!empty($emailData['value'])) {
                    return $emailData['value'];
                }
            }
        }
        
        // Для Facebook
        if (!empty($attributes['email'])) {
            return $attributes['email'];
        }
        
        return null;
    }

    /**
     * Получение username из атрибутов
     */
    private function getUsername($attributes)
    {
        $fields = ['login', 'screen_name', 'nickname', 'display_name', 'username'];
        foreach ($fields as $field) {
            if (!empty($attributes[$field])) {
                return $attributes[$field];
            }
        }
        
        // Для VK
        if (!empty($attributes['domain'])) {
            return $attributes['domain'];
        }
        
        // Для Facebook
        if (!empty($attributes['name'])) {
            return strtolower(str_replace(' ', '_', $attributes['name']));
        }
        
        // Для Google
        if (!empty($attributes['displayName'])) {
            return strtolower(str_replace(' ', '_', $attributes['displayName']));
        }
        
        return null;
    }

    /**
     * Получение имени
     */
    private function getFirstName($attributes)
    {
        $fields = ['first_name', 'given_name', 'firstname'];
        foreach ($fields as $field) {
            if (!empty($attributes[$field])) {
                return $attributes[$field];
            }
        }
        
        if (!empty($attributes['name'])) {
            $nameParts = explode(' ', $attributes['name'], 2);
            return $nameParts[0];
        }
        
        return null;
    }

    /**
     * Получение фамилии
     */
    private function getLastName($attributes)
    {
        $fields = ['last_name', 'family_name', 'lastname'];
        foreach ($fields as $field) {
            if (!empty($attributes[$field])) {
                return $attributes[$field];
            }
        }
        
        if (!empty($attributes['name'])) {
            $nameParts = explode(' ', $attributes['name'], 2);
            return isset($nameParts[1]) ? $nameParts[1] : '';
        }
        
        return null;
    }

    /**
     * Получение фото
     */
    private function getPhoto($attributes)
    {
        $fields = ['photo', 'photo_max', 'photo_200', 'avatar', 'picture', 'avatar_url'];
        foreach ($fields as $field) {
            if (!empty($attributes[$field])) {
                return $attributes[$field];
            }
        }
        
        return null;
    }

    /**
     * Получение ID провайдера
     */
    private function getProviderId($attributes)
    {
        $fields = ['id', 'uid', 'user_id'];
        foreach ($fields as $field) {
            if (!empty($attributes[$field])) {
                return (string)$attributes[$field];
            }
        }
        
        return null;
    }

    /**
     * Поиск пользователя по ID провайдера
     */
    private function findUserByProviderId($provider, $providerId)
    {
        if (empty($providerId)) {
            return null;
        }
        
        $field = $this->getProviderField($provider);
        if (!$field) {
            return null;
        }
        
        return User::find()->where([$field => $providerId])->one();
    }

    /**
     * Получение поля для провайдера
     */
    private function getProviderField($provider)
    {
        $map = [
            'vkontakte' => 'vk_id',
            'google' => 'google_id',
            'facebook' => 'facebook_id',
            'yandex' => 'yandex_id',
            'github' => 'github_id',
        ];
        
        return $map[$provider] ?? null;
    }

    /**
     * Обновление данных пользователя
     */
    private function updateUser($user, $attributes, $provider, $providerId)
    {
        $field = $this->getProviderField($provider);
        
        if ($field && $providerId && empty($user->$field)) {
            $user->$field = $providerId;
        }
        
        // Обновляем фото если его нет
        if (empty($user->photo) && !empty($attributes['photo'])) {
            $user->photo = $attributes['photo'];
        }
        
        if ($user->isAttributeChanged('updated_at')) {
            $user->save(false);
        }
    }

    /**
     * Создание нового пользователя
     */
    private function createUser($data)
    {
        $user = new User();
        $user->scenario = 'register';
        
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->first_name = $data['first_name'] ?? '';
        $user->last_name = $data['last_name'] ?? '';
        $user->photo = $data['photo'] ?? null;
        
        // Сохраняем ID провайдера
        $field = $this->getProviderField($data['provider']);
        if ($field) {
            $user->$field = $data['provider_id'];
        }
        
        // Генерируем случайный пароль
        $randomPassword = Yii::$app->security->generateRandomString(12);
        $user->setPassword($randomPassword);
        $user->generateAuthKey();
        
        if (!$user->save()) {
            Yii::error('User creation failed: ' . json_encode($user->errors), 'auth');
            throw new \Exception('Ошибка при создании пользователя');
        }
        
        // Автоматически подписываем пользователя на уведомления по email
        $this->subscribeToNotifications($user->id);
        
        Yii::info("User created via {$data['provider']}: {$user->email}", 'auth');
        
        return $user;
    }

    /**
     * Генерация уникального имени пользователя
     */
    private function generateUniqueUsername($username, $email)
    {
        if (empty($username)) {
            $username = explode('@', $email)[0];
            $username = preg_replace('/[^a-zA-Z0-9_]/', '_', $username);
        }
        
        // Проверяем, не занят ли username
        $counter = 1;
        $base = $username;
        
        while (User::find()->where(['username' => $username])->exists()) {
            $username = $base . '_' . $counter;
            $counter++;
        }
        
        return $username;
    }

    /**
     * Подписка пользователя на уведомления
     */
    private function subscribeToNotifications($userId)
    {
        try {
            $events = [
                NotificationSubscription::EVENT_SEARCH_SUBSCRIPTION,
                NotificationSubscription::EVENT_NEW_ADVERTISEMENT,
                NotificationSubscription::EVENT_NEW_MESSAGE,
            ];
            
            foreach ($events as $event) {
                NotificationSubscription::subscribe(
                    $userId,
                    $event,
                    NotificationSubscription::CHANNEL_EMAIL
                );
            }
            
            Yii::info("User {$userId} subscribed to email notifications", 'auth');
        } catch (\Exception $e) {
            Yii::error("Failed to subscribe user {$userId} to notifications: " . $e->getMessage(), 'auth');
        }
    }
}