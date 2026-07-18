<?php
// FILE: .\models\BaseAdvertisementType.php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Абстрактный базовый класс для типов объявлений
 * 
 * @property int $id
 * @property int $advertisement_id
 * @property string $model
 * @property int|null $producer_id
 * @property string $condition
 * @property string|null $defects
 * @property int $created_at
 * @property int $updated_at
 */
abstract class BaseAdvertisementType extends ActiveRecord
{
    /**
     * Возвращает краткую информацию о товаре в виде строки
     * 
     @param string $separator Разделитель между элементами (по умолчанию ' | ')
     * @return string
     */
    abstract public function getShortInfoString($separator = ' | ');
    
    /**
     * Возвращает название типа товара (для использования в заголовках)
     * 
     * @return string
     */
    abstract public function getTypeLabel();
    
    /**
     * Возвращает производителя
     * 
     * @return \yii\db\ActiveQuery
     */
    abstract public function getProducer();
    
    /**
     * Получить название производителя
     * 
     * @return string|null
     */
    public function getProducerName()
    {
        $producer = $this->producer;
        if ($producer) {
            return $producer->short ?? $producer->name;
        }
        return null;
    }
    
    /**
     * Получить состояние в виде строки
     * 
     * @return string
     */
    public function getConditionLabel()
    {
        $labels = $this->getConditionList();
        return $labels[$this->condition] ?? $this->condition;
    }
    
    /**
     * Получить список состояний
     * 
     * @return array
     */
    abstract public static function getConditionList();
}