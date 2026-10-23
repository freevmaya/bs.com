<?php
// FILE: models/ParseMismatch.php

namespace app\models;

use yii\db\ActiveRecord;

/**
 * Лог расхождений между каталогом и страницей карты строп.
 *
 * @property int    $id
 * @property int    $source_id
 * @property string|null $catalog_value
 * @property string|null $page_value
 * @property string $detected_at
 */
class ParseMismatch extends ActiveRecord
{
    public static function tableName()
    {
        return 'parse_mismatches';
    }

    public function rules()
    {
        return [
            [['source_id', 'detected_at'], 'required'],
            [['source_id'], 'integer'],
            [['catalog_value', 'page_value'], 'string', 'max' => 255],
            [['detected_at'], 'safe'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id'            => 'ID',
            'source_id'     => 'ID источника',
            'catalog_value' => 'Значение в каталоге',
            'page_value'    => 'Значение на странице',
            'detected_at'   => 'Обнаружено',
        ];
    }
}