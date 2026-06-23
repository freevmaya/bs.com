<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Certification extends ActiveRecord
{
    public static function tableName()
    {
        return 'certification';
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
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['order'], 'integer'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Сертификация',
            'order' => 'Порядок сортировки',
        ];
    }
    
    /**
     * Получение списка сертификаций для выпадающего списка
     */
    public static function getList()
    {
        return self::find()
            ->orderBy(['order' => SORT_ASC, 'name' => SORT_ASC])
            ->select(['name'])
            ->indexBy('id')
            ->column();
    }
    
    /**
     * Получение всех сертификаций с сортировкой
     */
    public static function getAllOrdered()
    {
        return self::find()
            ->orderBy(['order' => SORT_ASC, 'name' => SORT_ASC])
            ->all();
    }
}