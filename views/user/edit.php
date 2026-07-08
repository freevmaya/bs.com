<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

$this->title = 'Редактирование профиля';
$this->params['breadcrumbs'][] = ['label' => 'Профиль', 'url' => ['profile']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="user-edit">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-body">
                    <?php $form = ActiveForm::begin(['id' => 'profile-form']); ?>

                    <?= $form->field($user, 'username')->textInput() ?>
                    
                    <?= $form->field($user, 'email')->textInput() ?>
                    
                    <?= $form->field($user, 'phone')->textInput() ?>
                    
                    <?= $form->field($user, 'city')->textInput() ?>

                    <div class="form-group">
                        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary']) ?>
                        <?= Html::a('Отмена', ['profile'], ['class' => 'btn btn-default']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
</div>