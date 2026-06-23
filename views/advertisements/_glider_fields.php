<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Producer;
use app\models\Certification;
use app\models\AdvertisementGlider;

$producers = Producer::find()->orderBy('name')->all();
$certifications = Certification::getList(); // Используем новый метод с сортировкой
$conditionList = AdvertisementGlider::getConditionList();
?>

<div class="glider-fields">
    <h4>Характеристики параплана</h4>
    <hr>
    
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($gliderModel, 'model')->textInput(['maxlength' => true, 'placeholder' => 'Mentor 7 Light']) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($gliderModel, 'producer_id')->dropDownList(
                \yii\helpers\ArrayHelper::map($producers, 'id', 'fullName'),
                ['prompt' => 'Выберите производителя']
            ) ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($gliderModel, 'certification_id')->dropDownList(
                $certifications, // Используем отсортированный список
                ['prompt' => 'Выберите сертификацию']
            ) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($gliderModel, 'condition')->dropDownList($conditionList, ['prompt' => 'Выберите состояние']) ?>
        </div>
    </div>
    
    <!-- остальные поля -->
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($gliderModel, 'weight_min')->textInput(['type' => 'number', 'placeholder' => '65']) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($gliderModel, 'weight_max')->textInput(['type' => 'number', 'placeholder' => '85']) ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <?= $form->field($gliderModel, 'date_release')->textInput(['placeholder' => '2023']) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($gliderModel, 'flight_time')->textInput(['type' => 'number', 'placeholder' => '50']) ?>
        </div>
    </div>
    
    <?= $form->field($gliderModel, 'defects')->textarea(['rows' => 3, 'placeholder' => 'Описание дефектов, если есть']) ?>
    
    <?= $form->field($gliderModel, 'cause')->textarea(['rows' => 3, 'placeholder' => 'Почему продаёте?']) ?>
</div>