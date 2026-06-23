<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;
use yii\behaviors\TimestampBehavior;

class User extends ActiveRecord implements IdentityInterface
{
    public $password;
    public $password_repeat;
    
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
            [['phone', 'city'], 'string', 'max' => 100],
            [['password'], 'string', 'min' => 6, 'on' => 'register'],
            [['password_repeat'], 'compare', 'compareAttribute' => 'password', 'on' => 'register'],
        ];
    }
    
    public function scenarios()
    {
        $scenarios = parent::scenarios();
        $scenarios['register'] = ['username', 'email', 'password', 'password_repeat', 'phone', 'city'];
        $scenarios['update'] = ['username', 'email', 'phone', 'city'];
        return $scenarios;
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'username' => 'Имя пользователя',
            'email' => 'Email',
            'password' => 'Пароль',
            'password_repeat' => 'Повторите пароль',
            'phone' => 'Телефон',
            'city' => 'Город',
            'created_at' => 'Дата регистрации',
        ];
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