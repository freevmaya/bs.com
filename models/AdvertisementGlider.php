<?php
// FILE: .\models\AdvertisementGlider.php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class AdvertisementGlider extends BaseAdvertisementType
{
    const CONDITION_NEW = 'new';
    const CONDITION_EXCELLENT = 'excellent';
    const CONDITION_GOOD = 'good';
    const CONDITION_FAIR = 'fair';
    const CONDITION_BAD = 'bad';
    
    public static function tableName()
    {
        return 'advertisement_glider';
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
            [['advertisement_id', 'producer_id', 'certification_id', 'weight_min', 'weight_max', 'flight_time'], 'integer'],
            [['model', 'date_release'], 'string', 'max' => 255],
            [['defects', 'cause'], 'string'],
            [['condition'], 'default', 'value' => self::CONDITION_GOOD],
            [['condition'], 'in', 'range' => [
                self::CONDITION_NEW, self::CONDITION_EXCELLENT, 
                self::CONDITION_GOOD, self::CONDITION_FAIR, self::CONDITION_BAD
            ]],
            ['weight_min', 'compare', 
                'compareAttribute' => 'weight_max', 
                'operator' => '<', 
                'type' => 'number', 
                'message' => 'Минимальный вес должен быть меньше максимального',
                'skipOnEmpty' => true,
            ],
            [['certification_id'], 'default', 'value' => null],
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
            'certification_id' => 'Сертификация',
            'weight_min' => 'Весовая вилка (мин), кг',
            'weight_max' => 'Весовая вилка (макс), кг',
            'date_release' => 'Год выпуска',
            'flight_time' => 'Налёт, часов',
            'condition' => 'Состояние',
            'defects' => 'Дефекты',
            'cause' => 'Причина продажи',
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
    
    public function getCertification()
    {
        return $this->hasOne(Certification::class, ['id' => 'certification_id']);
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
        return 'Параплан';
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
        
        if ($this->certification) {
            $parts[] = $this->certification->name;
        }
        
        if (!empty($this->weight_min) || !empty($this->weight_max)) {
            $min = $this->weight_min ?? '?';
            $max = $this->weight_max ?? '?';
            $parts[] = $min . ' - ' . $max . ' кг';
        }
        
        if (!empty($this->date_release)) {
            $parts[] = $this->date_release;
        }
        
        if (!empty($this->flight_time)) {
            $parts[] = $this->flight_time . ' ч.';
        }
        
        return implode($separator, $parts);
    }
    
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if (empty($this->condition)) {
                $this->condition = self::CONDITION_GOOD;
            }
            
            if ($this->certification_id === '') {
                $this->certification_id = null;
            }
            
            if ($this->producer_id === '') {
                $this->producer_id = null;
            }
            
            return true;
        }
        return false;
    }
}