<?php
// FILE: models/WingLowerLine.php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Стропа нижнего яруса (riser-группа: AR1, BR1, Au1, SU, KR…).
 *
 * @property int    $id
 * @property int    $wing_id
 * @property int    $group_index
 * @property string $name
 * @property float|null $length_mm
 * @property string|null $riser_note
 * @property string|null $raw_value
 */
class WingLowerLine extends ActiveRecord
{
    public static function tableName()
    {
        return 'wing_lower_lines';
    }

    public function rules()
    {
        return [
            [['wing_id', 'group_index', 'name'], 'required'],
            [['wing_id', 'group_index'], 'integer'],
            [['length_mm'], 'number'],
            [['name'], 'string', 'max' => 50],
            [['riser_note'], 'string', 'max' => 50],
            [['raw_value'], 'string', 'max' => 100],
            [['length_mm', 'riser_note', 'raw_value'], 'default', 'value' => null],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'          => 'ID',
            'wing_id'     => 'Параплан',
            'group_index' => 'Порядок группы',
            'name'        => 'Название',
            'length_mm'   => 'Длина, мм',
            'riser_note'  => 'Примечание',
            'raw_value'   => 'Исходное значение',
        ];
    }

    public function getWing()
    {
        return $this->hasOne(Wing::class, ['id' => 'wing_id']);
    }

    public function getUpperLines()
    {
        return $this->hasMany(WingUpperLine::class, ['lower_line_id' => 'id'])
            ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC]);
    }
}