<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Producer;
use app\models\AdvertisementHarness;

$producers = Producer::find()->orderBy('name')->all();
$conditionList = AdvertisementHarness::getConditionList();
$sizeList = AdvertisementHarness::getSizeList();
?>

<div class="harness-fields">
    <h4>Характеристики подвесной системы</h4>
    <hr>
    
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($harnessModel, 'model')->textInput(['maxlength' => true, 'placeholder' => 'X-Alps 3']) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($harnessModel, 'producer_id')->dropDownList(
                \yii\helpers\ArrayHelper::map($producers, 'id', 'fullName'),
                ['prompt' => 'Выберите производителя']
            ) ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($harnessModel, 'size')->dropDownList($sizeList, ['prompt' => 'Выберите размер']) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($harnessModel, 'condition')->dropDownList($conditionList, ['prompt' => 'Выберите состояние']) ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($harnessModel, 'date_release')->textInput(['placeholder' => '2023']) ?>
        </div>
    </div>
    
    <?= $form->field($harnessModel, 'defects')->textarea(['rows' => 3, 'placeholder' => 'Описание дефектов, если есть']) ?>
</div>