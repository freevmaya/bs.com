<?php
// FILE: .\views\advertisements\create.php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Advertisement;

$this->title = 'Добавить объявление';
$this->params['breadcrumbs'][] = ['label' => 'Объявления', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем CSS и JS для формы
$this->registerCssFile('@web/css/advertisement-form.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/advertisement-form.js', [
    'depends' => [\yii\web\JqueryAsset::class, \yii\jui\JuiAsset::class],
    'position' => \yii\web\View::POS_END
]);

// Передаем параметры в JS
$this->registerJs(
    'window.tempId = ' . json_encode($tempId) . ';',
    \yii\web\View::POS_BEGIN
);

$section = Yii::$app->request->get('section');
if ($section) {
    $model->section = $section;
}

// Проверяем, является ли пользователь администратором
$isAdmin = !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();
?>

<div class="advertisements-create">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <div class="row">
        <div class="col-md-6">
            <div class="panel panel-default">
                <div class="panel-body">
                    <?php $form = ActiveForm::begin([
                        'options' => [
                            'enctype' => 'multipart/form-data',
                            'id' => 'advertisement-form'
                        ]
                    ]); ?>
                    
                    <?= $form->field($model, 'section')->dropDownList([
                        '' => 'Выберите раздел',
                        'sell' => 'Продам',
                        'buy' => 'Куплю',
                    ], ['id' => 'section-select']) ?>
                    
                    <?= $form->field($model, 'type')->dropDownList(
                        Advertisement::getTypeList(),
                        ['prompt' => 'Выберите тип снаряжения', 'id' => 'type-select']
                    ) ?>
                    
                    <!-- Поле заголовка - показываем только для normal -->
                    <div id="title-field" style="display: <?= $model->type === 'normal' ? 'block' : 'none' ?>;">
                        <?= $form->field($model, 'title')->textInput(['maxlength' => true, 'placeholder' => 'Введите заголовок объявления'])->hint('Для парапланов, подвесок и приборов заголовок генерируется автоматически') ?>
                    </div>
                    
                    <?= $form->field($model, 'description')->textarea(['rows' => 6]) ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'price')->textInput(['placeholder' => '1000']) ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'price_negotiable')->checkbox() ?>
                        </div>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- ДИНАМИЧЕСКИЕ ПОЛЯ ДЛЯ РАЗНЫХ ТИПОВ (ПЕРЕМЕЩЕНЫ ВЫШЕ) -->
                    <!-- ============================================ -->
                    <div id="glider-fields" style="display: none;">
                        <?= $this->render('_glider_fields', [
                            'form' => $form,
                            'gliderModel' => $gliderModel,
                        ]) ?>
                    </div>
                    
                    <div id="harness-fields" style="display: none;">
                        <?= $this->render('_harness_fields', [
                            'form' => $form,
                            'harnessModel' => $harnessModel,
                        ]) ?>
                    </div>
                    
                    <div id="device-fields" style="display: none;">
                        <?= $this->render('_device_fields', [
                            'form' => $form,
                            'deviceModel' => $deviceModel,
                        ]) ?>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- КОНТАКТНАЯ ИНФОРМАЦИЯ (ПЕРЕМЕЩЕНА НИЖЕ) -->
                    <!-- ============================================ -->
                    <hr>
                    <p class="text-muted"><small>Контактная информация (заполняется из профиля, но можно изменить)</small></p>
                    
                    <?= $form->field($model, 'city')->textInput(['maxlength' => true, 'placeholder' => 'Город']) ?>
                    
                    <?= $form->field($model, 'phone')->textInput(['maxlength' => true, 'placeholder' => '+7 (999) 123-45-67']) ?>
                    
                    <?= $form->field($model, 'email')->textInput(['maxlength' => true, 'placeholder' => 'email@example.com']) ?>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <?= $form->field($model, 'telegram')->textInput([
                                'maxlength' => true,
                                'placeholder' => '@username или username',
                            ])->hint('Введите username в Telegram (без @ или с @)') ?>
                        </div>
                        <div class="col-md-6">
                            <?= $form->field($model, 'whatsapp')->textInput([
                                'maxlength' => true,
                                'placeholder' => '+7 (999) 123-45-67',
                            ])->hint('Введите номер WhatsApp в международном формате') ?>
                        </div>
                    </div>
                    
                    <?= $form->field($model, 'vk_profile_url')->textInput([
                        'maxlength' => true,
                        'placeholder' => 'https://vk.com/durov',
                    ])->hint('Ссылка на профиль VK') ?>
                    
                    <!-- Поле source_url - показываем только администраторам -->
                    <?php if ($isAdmin): ?>
                        <?= $form->field($model, 'source_url')->textInput([
                            'maxlength' => true,
                            'placeholder' => 'https://example.com/original',
                        ])->hint('Ссылка на источник объявления (доступно только администраторам)') ?>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <?= Html::submitButton('Создать объявление', ['class' => 'btn btn-success btn-lg btn-block']) ?>
                    </div>
                    
                    <?php ActiveForm::end(); ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div id="images-block" style="display: none;" data-delete-url="<?= \yii\helpers\Url::to(['advertisements/delete-temp-image-ajax']) ?>">
                <?= $this->render('_images_block', [
                    'images' => $tempImages,
                    'type' => 'create',
                    'id' => $tempId,
                ]) ?>
            </div>
        </div>
    </div>
</div>

<?php
// JavaScript для показа/скрытия поля заголовка в зависимости от типа
$script = <<<JS
document.getElementById('type-select').addEventListener('change', function() {
    var titleField = document.getElementById('title-field');
    var titleInput = document.querySelector('#title-field input');
    if (this.value === 'normal') {
        titleField.style.display = 'block';
    } else {
        titleField.style.display = 'none';
        // ПРИНУДИТЕЛЬНО ОЧИЩАЕМ ПОЛЕ ЗАГОЛОВКА, ЧТОБЫ ОН СГЕНЕРИРОВАЛСЯ АВТОМАТИЧЕСКИ
        if (titleInput) {
            titleInput.value = '';
        }
    }
});
JS;
$this->registerJs($script);
?>