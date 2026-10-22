<?php
// FILE: .\models\Favorite.php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Избранное объявление (для авторизованных пользователей).
 *
 * @property int $id
 * @property int $user_id
 * @property int $advertisement_id
 * @property int $created_at
 */
class Favorite extends ActiveRecord
{
    const COOKIE_NAME = 'favorites_guest';
    const COOKIE_TTL = 30 * 24 * 60 * 60; // 30 дней

    public static function tableName()
    {
        return 'favorites';
    }

    public function rules()
    {
        return [
            [['user_id', 'advertisement_id', 'created_at'], 'required'],
            [['user_id', 'advertisement_id', 'created_at'], 'integer'],
            [['user_id', 'advertisement_id'], 'unique', 'targetAttribute' => ['user_id', 'advertisement_id']],
            [['advertisement_id'], 'exist', 'targetClass' => Advertisement::class, 'targetAttribute' => 'id'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Пользователь',
            'advertisement_id' => 'Объявление',
            'created_at' => 'Добавлено',
        ];
    }

    public function getAdvertisement()
    {
        return $this->hasOne(Advertisement::class, ['id' => 'advertisement_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert && empty($this->created_at)) {
                $this->created_at = time();
            }
            return true;
        }
        return false;
    }
}