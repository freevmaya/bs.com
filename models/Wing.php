<?php
// FILE: models/Wing.php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Конкретная модель+размер параплана.
 *
 * @property int    $id
 * @property int    $source_id
 * @property int|null $manufacturer_id
 * @property string $model
 * @property int|null $model_year
 * @property string $size
 * @property string $full_name
 * @property string|null $source_url
 * @property int    $catalog_match
 * @property string $state
 * @property string|null $parsed_at
 */
class Wing extends ActiveRecord
{
    const STATE_ACTIVE         = 'active';
    const STATE_REQUIRE_UPDATE = 'require_update';
    const STATE_HIDE           = 'hide';
    const STATE_DELETE         = 'delete';

    public static function tableName()
    {
        return 'wings';
    }

    public static function getStateList()
    {
        return [
            self::STATE_ACTIVE         => 'Активно',
            self::STATE_REQUIRE_UPDATE => 'Требует обновления',
            self::STATE_HIDE           => 'Скрыто',
            self::STATE_DELETE         => 'На удаление',
        ];
    }

    public function getStateLabel()
    {
        return self::getStateList()[$this->state] ?? $this->state;
    }

    public function getStateColorClass()
    {
        $map = [
            self::STATE_ACTIVE         => 'state-active',
            self::STATE_REQUIRE_UPDATE => 'state-require-update',
            self::STATE_HIDE           => 'state-hide',
            self::STATE_DELETE         => 'state-delete',
        ];
        return $map[$this->state] ?? '';
    }

    public function rules()
    {
        return [
            [['source_id', 'model', 'size', 'full_name'], 'required'],
            [['source_id', 'manufacturer_id', 'model_year', 'catalog_match'], 'integer'],
            [['model'], 'string', 'max' => 150],
            [['size'], 'string', 'max' => 30],
            [['full_name'], 'string', 'max' => 255],
            [['source_url'], 'string', 'max' => 500],
            [['parsed_at'], 'safe'],
            [['catalog_match'], 'default', 'value' => 1],
            [['state'], 'default', 'value' => self::STATE_ACTIVE],
            [['state'], 'in', 'range' => [
                self::STATE_ACTIVE,
                self::STATE_REQUIRE_UPDATE,
                self::STATE_HIDE,
                self::STATE_DELETE,
            ]],
            [['source_id'], 'unique'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'              => 'ID',
            'source_id'       => 'ID источника',
            'manufacturer_id' => 'Производитель',
            'model'           => 'Модель',
            'model_year'      => 'Год',
            'size'            => 'Размер',
            'full_name'       => 'Полное название',
            'source_url'      => 'URL источника',
            'catalog_match'   => 'Совпадает с каталогом',
            'state'           => 'Состояние',
            'parsed_at'       => 'Спарсено',
        ];
    }

    public function getManufacturer()
    {
        return $this->hasOne(Manufacturer::class, ['id' => 'manufacturer_id']);
    }

    public function getLowerLines()
    {
        return $this->hasMany(WingLowerLine::class, ['wing_id' => 'id'])
            ->orderBy(['group_index' => SORT_ASC]);
    }

    public function getUpperLines()
    {
        return $this->hasMany(WingUpperLine::class, ['wing_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC]);
    }

    public function getFiles()
    {
        return $this->hasMany(WingFile::class, ['wing_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'id' => SORT_ASC]);
    }
}