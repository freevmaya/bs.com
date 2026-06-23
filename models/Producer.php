<?php

namespace app\models;

use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Producer extends ActiveRecord
{
    public static function tableName()
    {
        return 'producers';
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
            [['name'], 'required'],
            [['name'], 'string', 'max' => 255],
            [['short'], 'string', 'max' => 100],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'name' => 'Наименование',
            'short' => 'Краткое название',
        ];
    }
    
    public function getFullName()
    {
        return $this->short ? $this->short . ' (' . $this->name . ')' : $this->name;
    }
}