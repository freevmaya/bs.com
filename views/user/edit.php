<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\Url;

$this->title = 'Редактирование профиля';
$this->params['breadcrumbs'][] = ['label' => 'Профиль', 'url' => ['profile']];
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем JS
$this->registerJsFile('@web/js/vk-profile.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);
?>

<div class="user-edit">
    <h1><?= Html::encode($this->title) ?></h1>

    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-body">
                    <?php $form = ActiveForm::begin([
                        'id' => 'profile-form',
                        'options' => [
                            'data-vk-resolve-url' => Url::to(['user/get-vk-id']),
                        ],
                    ]); ?>

                    <?= $form->field($user, 'username')->textInput() ?>
                    
                    <?= $form->field($user, 'email')->textInput() ?>
                    
                    <?= $form->field($user, 'phone')->textInput() ?>
                    
                    <?= $form->field($user, 'city')->textInput() ?>
                    
                    <?= $form->field($user, 'vk_profile_url')->textInput([
                        'placeholder' => 'https://vk.com/durov',
                        'id' => 'vk-profile-url',
                    ])->hint('Введите ссылку на ваш профиль VK. ID будет определен автоматически.') ?>
                    
                    <div id="vk-id-result" style="display: none; margin-bottom: 15px;">
                        <div class="alert alert-info">
                            <strong><span class="glyphicon glyphicon-ok"></span> VK ID определен:</strong>
                            <span id="vk-id-display"></span>
                            <button type="button" class="btn btn-sm btn-default pull-right" id="vk-id-cancel">
                                Отменить
                            </button>
                            <div class="clearfix"></div>
                        </div>
                    </div>

                    <div class="form-group">
                        <?= Html::submitButton('Сохранить', ['class' => 'btn btn-primary']) ?>
                        <?= Html::a('Отмена', ['profile'], ['class' => 'btn btn-default']) ?>
                    </div>

                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
    </div>
    
    <p style="margin-top: 10px;">
        <?= Html::a('← Назад в профиль', ['/user/profile'], ['class' => 'btn btn-default']) ?>
    </p>
</div>

<style>
#vk-profile-url {
    transition: border-color 0.3s ease, box-shadow 0.3s ease;
}
#vk-profile-url.vk-resolving {
    border-color: #f0ad4e;
    box-shadow: 0 0 10px rgba(240, 173, 78, 0.3);
}
#vk-profile-url.vk-success {
    border-color: #5cb85c;
    box-shadow: 0 0 10px rgba(92, 184, 92, 0.3);
}
#vk-profile-url.vk-error {
    border-color: #d9534f;
    box-shadow: 0 0 10px rgba(217, 83, 79, 0.3);
}
</style>