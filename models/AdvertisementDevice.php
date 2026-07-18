<?php
// FILE: .\models\AdvertisementDevice.php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class AdvertisementDevice extends BaseAdvertisementType
{
    const CONDITION_NEW = 'new';
    const CONDITION_EXCELLENT = 'excellent';
    const CONDITION_GOOD = 'good';
    const CONDITION_FAIR = 'fair';
    const CONDITION_BAD = 'bad';
    
    public static function tableName()
    {
        return 'advertisement_device';
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
            [['advertisement_id', 'model'], 'required'],
            [['advertisement_id', 'producer_id'], 'integer'],
            [['model'], 'string', 'max' => 255],
            [['defects'], 'string'],
            [['condition'], 'default', 'value' => self::CONDITION_GOOD],
            [['condition'], 'in', 'range' => [
                self::CONDITION_NEW, self::CONDITION_EXCELLENT, 
                self::CONDITION_GOOD, self::CONDITION_FAIR, self::CONDITION_BAD
            ]],
            [['producer_id'], 'default', 'value' => null],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'advertisement_id' => 'Объявление',
            'model' => 'Модель',
            'producer_id' => 'Производитель',
            'condition' => 'Состояние',
            'defects' => 'Дефекты',
        ];
    }
    
    public function getAdvertisement()
    {
        return $this->hasOne(Advertisement::class, ['id' => 'advertisement_id']);
    }
    
    public function getProducer()
    {
        return $this->hasOne(Producer::class, ['id' => 'producer_id']);
    }
    
    public static function getConditionList()
    {
        return [
            self::CONDITION_NEW => 'Новый',
            self::CONDITION_EXCELLENT => 'Отличное',
            self::CONDITION_GOOD => 'Хорошее',
            self::CONDITION_FAIR => 'Удовлетворительное',
            self::CONDITION_BAD => 'Плохое',
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTypeLabel()
    {
        return 'Прибор';
    }
    
    /**
     * {@inheritdoc}
     */
    public function getShortInfoString($separator = ' | ')
    {
        $parts = [];
        
        if (!empty($this->model)) {
            $parts[] = $this->model;
        }
        
        $producerName = $this->getProducerName();
        if ($producerName) {
            $parts[] = $producerName;
        }
        
        return implode($separator, $parts);
    }
    
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if (empty($this->condition)) {
                $this->condition = self::CONDITION_GOOD;
            }
            
            if ($this->producer_id === '') {
                $this->producer_id = null;
            }
            
            return true;
        }
        return false;
    }
}