<?php
// FILE: .\models\AdvertisementReserve.php

namespace app\models;

/**
 * Модель для типа объявления "Запасной парашют".
 *
 * Не хранит собственные created_at/updated_at — они находятся в родительской
 * таблице advertisements и доступны через связь getAdvertisement().
 *
 * @property int $id
 * @property int $advertisement_id
 * @property string $model
 * @property int|null $producer_id
 * @property string|null $canopy_type
 * @property int|null $max_weight
 * @property float|null $area
 * @property int|null $weight
 * @property string|null $date_release
 * @property string|null $repack_date
 * @property string $condition
 * @property string|null $defects
 * @property bool|null $was_used
 * @property string|null $equipment
 * @property string|null $cause
 */
class AdvertisementReserve extends BaseAdvertisementType
{
    const CONDITION_NEW = 'new';
    const CONDITION_EXCELLENT = 'excellent';
    const CONDITION_GOOD = 'good';
    const CONDITION_FAIR = 'fair';
    const CONDITION_BAD = 'bad';

    const CANOPY_TYPE_ROUND = 'round';
    const CANOPY_TYPE_SQUARE = 'square';
    const CANOPY_TYPE_MULTI_SLOT = 'multi_slot';
    const CANOPY_TYPE_OTHER = 'other';

    public static function tableName()
    {
        return 'advertisement_reserve';
    }

    public function rules()
    {
        return [
            [['advertisement_id', 'model'], 'required'],
            [['advertisement_id', 'producer_id', 'max_weight'], 'integer'],
            [['area', 'weight'], 'number', 'min' => 0],
            [['model', 'date_release'], 'string', 'max' => 255],
            [['defects', 'equipment', 'cause'], 'string'],
            [['was_used'], 'boolean', 'skipOnEmpty' => true],
            [['repack_date'], 'date', 'format' => 'php:Y-m-d', 'skipOnEmpty' => true],
            [['condition'], 'default', 'value' => self::CONDITION_GOOD],
            [['condition'], 'in', 'range' => [
                self::CONDITION_NEW, self::CONDITION_EXCELLENT,
                self::CONDITION_GOOD, self::CONDITION_FAIR, self::CONDITION_BAD
            ]],
            [['canopy_type'], 'in', 'range' => [
                self::CANOPY_TYPE_ROUND,
                self::CANOPY_TYPE_SQUARE,
                self::CANOPY_TYPE_MULTI_SLOT,
                self::CANOPY_TYPE_OTHER,
            ], 'skipOnEmpty' => true],
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
            'canopy_type' => 'Тип купола',
            'max_weight' => 'Макс. спасаемый вес, кг',
            'area' => 'Площадь, м²',
            'weight' => 'Вес запаски, г',
            'date_release' => 'Год выпуска',
            'repack_date' => 'Дата переукладки',
            'condition' => 'Состояние',
            'defects' => 'Дефекты',
            'was_used' => 'Применялась по назначению',
            'equipment' => 'Комплектация',
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

    public static function getCanopyTypeList()
    {
        return [
            self::CANOPY_TYPE_ROUND => 'Круглая',
            self::CANOPY_TYPE_SQUARE => 'Квадратная',
            self::CANOPY_TYPE_MULTI_SLOT => 'Многощелевая',
            self::CANOPY_TYPE_OTHER => 'Другое',
        ];
    }

    public function getCanopyTypeLabel()
    {
        $list = self::getCanopyTypeList();
        return $list[$this->canopy_type] ?? '—';
    }

    public function getConditionLabel()
    {
        $list = self::getConditionList();
        return $list[$this->condition] ?? $this->condition;
    }

    /**
     * {@inheritdoc}
     */
    public function getTypeLabel()
    {
        return 'Запасной парашют';
    }

    /**
     * {@inheritdoc}
     */
    public function getShortInfoString($separator = ' | ', $baseInfo = true)
    {
        $parts = [];

        if (!empty($this->model)) {
            $parts[] = $this->model;
        }

        $producerName = $this->getProducerName();
        if ($producerName) {
            $parts[] = $producerName;
        }

        if (!empty($this->max_weight)) {
            $parts[] = 'до ' . $this->max_weight . ' кг';
        }

        if (!empty($this->canopy_type)) {
            $parts[] = $this->getCanopyTypeLabel();
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
            if ($this->canopy_type === '') {
                $this->canopy_type = null;
            }
            return true;
        }
        return false;
    }
}