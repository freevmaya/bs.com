<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Producer;
use app\models\AdvertisementDevice;

$producers = Producer::find()->orderBy('name')->all();
$conditionList = AdvertisementDevice::getConditionList();
?>

<div class="device-fields">
    <h4>Характеристики прибора</h4>
    <hr>
    
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($deviceModel, 'model')->textInput(['maxlength' => true, 'placeholder' => 'Flymaster GPS']) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($deviceModel, 'producer_id')->dropDownList(
                \yii\helpers\ArrayHelper::map($producers, 'id', 'fullName'),
                ['prompt' => 'Выберите производителя']
            ) ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($deviceModel, 'condition')->dropDownList($conditionList, ['prompt' => 'Выберите состояние']) ?>
        </div>
    </div>
    
    <?= $form->field($deviceModel, 'defects')->textarea(['rows' => 3, 'placeholder' => 'Описание дефектов, если есть']) ?>
</div>