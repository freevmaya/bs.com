<?php
// FILE: models/WingFile.php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Файл, на котором основана карта строп (блок Files used).
 *
 * @property int    $id
 * @property int    $wing_id
 * @property string $name
 * @property string $url
 * @property string|null $file_date
 * @property int    $sort_order
 */
class WingFile extends ActiveRecord
{
    public static function tableName()
    {
        return 'wing_files';
    }

    public function rules()
    {
        return [
            [['wing_id', 'name', 'url'], 'required'],
            [['wing_id', 'sort_order'], 'integer'],
            [['name'], 'string', 'max' => 255],
            [['url'], 'string', 'max' => 500],
            [['file_date'], 'safe'],
            [['sort_order'], 'default', 'value' => 0],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'         => 'ID',
            'wing_id'    => 'Параплан',
            'name'       => 'Имя файла',
            'url'        => 'URL',
            'file_date'  => 'Дата',
            'sort_order' => 'Порядок',
        ];
    }

    public function getWing()
    {
        return $this->hasOne(Wing::class, ['id' => 'wing_id']);
    }
}