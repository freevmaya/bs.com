<?php
// FILE: .\views\advertisements\_reserve_fields.php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use app\models\Producer;
use app\models\AdvertisementReserve;

$producers = Producer::find()->orderBy('name')->all();
$conditionList = AdvertisementReserve::getConditionList();
$canopyTypeList = AdvertisementReserve::getCanopyTypeList();
?>

<div class="reserve-fields">
    <h4>Характеристики запасного парашюта</h4>
    <hr>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($reserveModel, 'model')->textInput([
                'maxlength' => true,
                'placeholder' => 'Например: Yeti rescue, Smart L, SQ26LT',
            ]) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($reserveModel, 'producer_id')->dropDownList(
                ArrayHelper::map($producers, 'id', 'fullName'),
                ['prompt' => 'Выберите производителя (необязательно)']
            ) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($reserveModel, 'canopy_type')->dropDownList(
                $canopyTypeList,
                ['prompt' => 'Выберите тип купола']
            ) ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($reserveModel, 'max_weight')->textInput([
                'type' => 'number',
                'placeholder' => '120',
            ])->hint('Максимальный суммарный вес пилота и снаряжения, кг') ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <?= $form->field($reserveModel, 'area')->textInput([
                'type' => 'number',
                'step' => '0.1',
                'placeholder' => '38.4',
            ]) ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($reserveModel, 'weight')->textInput([
                'type' => 'number',
                'step' => '0.01',
                'placeholder' => '1.70',
            ])->hint('Вес запаски в килограммах (например: 1.70)') ?>
        </div>
        <div class="col-md-4">
            <?= $form->field($reserveModel, 'date_release')->textInput([
                'maxlength' => true,
                'placeholder' => '2017 или "ориентировочно 2019"',
            ]) ?>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <?= $form->field($reserveModel, 'repack_date')->input('date') ?>
        </div>
        <div class="col-md-6">
            <?= $form->field($reserveModel, 'condition')->dropDownList(
                $conditionList,
                ['prompt' => 'Выберите состояние']
            ) ?>
        </div>
    </div>

    <?= $form->field($reserveModel, 'was_used')->checkbox([
        'uncheck' => null,
    ])->hint('Отметьте, если парашют применялся по назначению (в реальном спасении)') ?>

    <?= $form->field($reserveModel, 'defects')->textarea([
        'rows' => 3,
        'placeholder' => 'Описание дефектов, если есть (например: "нет", "не сочилась")',
    ]) ?>

    <?= $form->field($reserveModel, 'equipment')->textarea([
        'rows' => 2,
        'placeholder' => 'Например: "Документы, инструкция в комплекте с карабином"',
    ]) ?>

    <?= $form->field($reserveModel, 'cause')->textarea([
        'rows' => 2,
        'placeholder' => 'Почему продаёте?',
    ]) ?>
</div>