<?php
// FILE: models/WingUpperLine.php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Стропа верхнего яруса (A1, a1, SA, DG1…), привязанная к нижней стропе.
 *
 * @property int    $id
 * @property int    $wing_id
 * @property int    $lower_line_id
 * @property string $name
 * @property float  $length_mm
 * @property int    $sort_order
 */
class WingUpperLine extends ActiveRecord
{
    public static function tableName()
    {
        return 'wing_upper_lines';
    }

    public function rules()
    {
        return [
            [['wing_id', 'lower_line_id', 'name', 'length_mm'], 'required'],
            [['wing_id', 'lower_line_id', 'sort_order'], 'integer'],
            [['length_mm'], 'number'],
            [['name'], 'string', 'max' => 50],
            [['sort_order'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'wing_id'       => 'Параплан',
            'lower_line_id' => 'Нижняя стропа',
            'name'          => 'Название',
            'length_mm'     => 'Длина, мм',
            'sort_order'    => 'Порядок',
        ];
    }

    public function getWing()
    {
        return $this->hasOne(Wing::class, ['id' => 'wing_id']);
    }

    public function getLowerLine()
    {
        return $this->hasOne(WingLowerLine::class, ['id' => 'lower_line_id']);
    }
}