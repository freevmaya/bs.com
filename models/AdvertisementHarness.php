<?php
// FILE: .\models\AdvertisementHarness.php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class AdvertisementHarness extends BaseAdvertisementType
{
    const CONDITION_NEW = 'new';
    const CONDITION_EXCELLENT = 'excellent';
    const CONDITION_GOOD = 'good';
    const CONDITION_FAIR = 'fair';
    const CONDITION_BAD = 'bad';
    
    const SIZE_XS = 'XS';
    const SIZE_S = 'S';
    const SIZE_SM = 'SM';
    const SIZE_M = 'M';
    const SIZE_ML = 'ML';
    const SIZE_L = 'L';
    const SIZE_XL = 'XL';
    const SIZE_XXL = 'XXL';
    const SIZE_XXXL = 'XXXL';
    const SIZE_ONESIZE = 'OneSize';
    
    public static function tableName()
    {
        return 'advertisement_harness';
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
            [['advertisement_id', 'model', 'size'], 'required'],
            [['advertisement_id', 'producer_id'], 'integer'],
            [['model', 'date_release'], 'string', 'max' => 255],
            [['defects'], 'string'],
            [['condition'], 'default', 'value' => self::CONDITION_GOOD],
            [['condition'], 'in', 'range' => [
                self::CONDITION_NEW, self::CONDITION_EXCELLENT, 
                self::CONDITION_GOOD, self::CONDITION_FAIR, self::CONDITION_BAD
            ]],
            [['size'], 'in', 'range' => [
                self::SIZE_XS, self::SIZE_S, self::SIZE_SM, self::SIZE_M, self::SIZE_ML,
                self::SIZE_L, self::SIZE_XL, self::SIZE_XXL, self::SIZE_XXXL, self::SIZE_ONESIZE
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
            'size' => 'Размер',
            'date_release' => 'Год выпуска',
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
    
    public static function getSizeList()
    {
        return [
            self::SIZE_XS => 'XS',
            self::SIZE_S => 'S',
            self::SIZE_SM => 'S/M',
            self::SIZE_M => 'M',
            self::SIZE_ML => 'M/L',
            self::SIZE_L => 'L',
            self::SIZE_XL => 'XL',
            self::SIZE_XXL => 'XXL',
            self::SIZE_XXXL => 'XXXL',
            self::SIZE_ONESIZE => 'OneSize',
        ];
    }
    
    /**
     * {@inheritdoc}
     */
    public function getTypeLabel()
    {
        return 'Подвесная система';
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
        
        if (!empty($this->size)) {
            $parts[] = $this->size;
        }
        
        if (!empty($this->date_release)) {
            $parts[] = $this->date_release;
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