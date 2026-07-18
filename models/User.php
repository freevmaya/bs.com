<?php
// FILE: .\models\User.php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use yii\behaviors\TimestampBehavior;

class User extends ActiveRecord implements IdentityInterface
{
    public $password;
    public $password_repeat;
    
    // Константы для типа пользователя
    const TYPE_USER = 'user';
    const TYPE_ADMIN = 'admin';
    
    public static function tableName()
    {
        return 'users';
    }
    
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }
    
    public function rules()
    {
        return [
            [['username', 'email', 'password'], 'required', 'on' => 'register'],
            [['username', 'email'], 'required', 'on' => 'update'],
            [['username'], 'string', 'min' => 3, 'max' => 100],
            [['username'], 'unique'],
            [['email'], 'email'],
            [['email'], 'unique'],
            [['phone', 'city', 'vk_id', 'vk_profile_url', 'telegram', 'telegram_chat_id', 'whatsapp'], 'string', 'max' => 255],
            // Новые поля для OAuth
            [['google_id', 'facebook_id', 'yandex_id', 'github_id', 'first_name', 'last_name', 'photo'], 'string', 'max' => 255],
            // type - не редактируется с сайта, поэтому не включаем в rules
            [['vk_id'], 'match', 'pattern' => '/^\d+$/', 'message' => 'ID в VK должен содержать только цифры'],
            [['vk_profile_url'], 'validateVkProfileUrl'],
            [['telegram'], 'match', 'pattern' => '/^@?[a-zA-Z0-9_]{5,32}$/', 'message' => 'Введите корректный username Telegram (например: @username или username)'],
            [['whatsapp'], 'match', 'pattern' => '/^[\d\s\+\(\)\-]{5,20}$/', 'message' => 'Введите корректный номер WhatsApp'],
            [['password'], 'string', 'min' => 6, 'on' => 'register'],
            [['password_repeat'], 'compare', 'compareAttribute' => 'password', 'on' => 'register'],
            [['invitation_token'], 'safe'],
        ];
    }
    
    /**
     * Валидация ссылки на профиль VK
     */
    public function validateVkProfileUrl($attribute, $params)
    {
        if (empty($this->$attribute)) {
            return;
        }
        
        // Проверяем, что ссылка ведет на VK
        if (!preg_match('/^https?:\/\/(?:www\.)?vk\.com\/(?:id\d+|[\w\.]+)$/i', $this->$attribute)) {
            $this->addError($attribute, 'Введите корректную ссылку на профиль VK (например: https://vk.com/durov)');
        }
    }
    
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios['register'] = ['username', 'email', 'password', 'password_repeat', 'phone', 'city', 'vk_profile_url', 'telegram', 'whatsapp'];
        $scenarios['update'] = ['username', 'email', 'phone', 'city', 'vk_profile_url', 'telegram', 'whatsapp'];
        return $scenarios;
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type' => 'Тип пользователя',
            'username' => 'Имя пользователя',
            'email' => 'Email',
            'password' => 'Пароль',
            'password_repeat' => 'Повторите пароль',
            'phone' => 'Телефон',
            'city' => 'Город',
            'vk_id' => 'ID в VK',
            'vk_profile_url' => 'Ссылка на профиль VK',
            'telegram' => 'Telegram',
            'telegram_chat_id' => 'Telegram Chat ID',
            'whatsapp' => 'WhatsApp',
            'first_name' => 'Имя',
            'last_name' => 'Фамилия',
            'photo' => 'Фото',
            'created_at' => 'Дата регистрации',
        ];
    }
    
    /**
     * Проверяет, является ли пользователь администратором
     */
    public function isAdmin()
    {
        return $this->type === self::TYPE_ADMIN;
    }
    
    // IdentityInterface methods
    public static function findIdentity($id)
    {
        return static::findOne($id);
    }
    
    public static function findIdentityByAccessToken($token, $type = null)
    {
        return static::findOne(['auth_key' => $token]);
    }
    
    public static function findByUsername($username)
    {
        return static::findOne(['username' => $username]);
    }
    
    public function getId()
    {
        return $this->id;
    }
    
    public function getAuthKey()
    {
        return $this->auth_key;
    }
    
    public function validateAuthKey($authKey)
    {
        return $this->auth_key === $authKey;
    }
    
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }
    
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }
    
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }
}