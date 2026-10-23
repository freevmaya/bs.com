<?php
// FILE: models/Manufacturer.php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Производитель парапланов.
 *
 * @property int    $id
 * @property string $name
 * @property string|null $website
 * @property string|null $layout_method
 */
class Manufacturer extends ActiveRecord
{
    public static function tableName()
    {
        return 'manufacturers';
    }

    public function rules()
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 150],
            [['website'], 'string', 'max' => 500],
            [['website'], 'url', 'skipOnEmpty' => true],
            [['layout_method'], 'string', 'max' => 100],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'name'          => 'Название',
            'website'       => 'Сайт',
            'layout_method' => 'Метод раскладки',
        ];
    }

    public function getWings()
    {
        return $this->hasMany(Wing::class, ['manufacturer_id' => 'id']);
    }
}