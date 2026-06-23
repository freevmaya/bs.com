<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use app\models\Advertisement;

/**
 * @var \app\models\AdvertisementSearch $searchModel
 * @var string|null $section
 * @var array $action
 */

// Определяем URL для кнопки сброса
$resetUrl = $action;
if ($section) {
    $resetUrl = ['advertisements/' . ($section === Advertisement::SECTION_SELL ? 'sell' : 'buy')];
}

// Собираем активные параметры поиска в виде строки
$paramsString = '';
$paramParts = [];

// Цена от
if ($searchModel->price_min !== null && $searchModel->price_min !== '') {
    $paramParts[] = 'Цена от ' . $searchModel->price_min . ' ₽';
}

// Цена до
if ($searchModel->price_max !== null && $searchModel->price_max !== '') {
    $paramParts[] = 'Цена до ' . $searchModel->price_max . ' ₽';
}

// Город
if (!empty($searchModel->city)) {
    $paramParts[] = 'Город: ' . Html::encode($searchModel->city);
}

// Тип
if (!empty($searchModel->type) && $searchModel->type !== 'normal') {
    $typeList = Advertisement::getTypeList();
    $paramParts[] = 'Тип: ' . ($typeList[$searchModel->type] ?? $searchModel->type);
}

/*
// Раздел (если есть)
if ($section) {
    $sectionLabel = ($section === Advertisement::SECTION_SELL) ? 'Продам' : 'Куплю';
    $paramParts[] = 'Раздел: ' . $sectionLabel;
}*/

// Объединяем параметры через запятую
$paramsString = implode(', ', $paramParts);

// Флаг наличия активных параметров
$hasParams = !empty($paramParts);

// Преобразуем resetUrl в строку для ссылки
$resetUrlString = Url::to($resetUrl);
?>

<div class="search-bar-container">
    <!-- Основная строка поиска на всю ширину -->
    <?php $form = ActiveForm::begin([
        'method' => 'get',
        'action' => $action,
        'options' => ['class' => 'd-flex w-100', 'id' => 'main-search-form'],
    ]); ?>
    
    <div class="input-group">
        <?= Html::textInput('AdvertisementSearch[search_text]', $searchModel->search_text, [
            'class' => 'form-control form-control-lg',
            'placeholder' => 'Поиск по объявлениям...',
        ]) ?>
        <button class="btn btn-primary btn-lg" type="submit" id="search-button">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"></circle>
                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
            </svg>
        </button>
        <button class="btn btn-outline-secondary btn-lg btn-params" type="button" data-bs-toggle="collapse" data-bs-target="#searchParamsCollapse" aria-expanded="false" aria-controls="searchParamsCollapse">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="4" y1="21" x2="4" y2="14"></line>
                <line x1="4" y1="10" x2="4" y2="3"></line>
                <line x1="12" y1="21" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12" y2="3"></line>
                <line x1="20" y1="21" x2="20" y2="16"></line>
                <line x1="20" y1="12" x2="20" y2="3"></line>
                <line x1="1" y1="14" x2="7" y2="14"></line>
                <line x1="9" y1="8" x2="15" y2="8"></line>
                <line x1="17" y1="16" x2="23" y2="16"></line>
            </svg>
            <span>Параметры</span>
        </button>
    </div>
    
    <?php ActiveForm::end(); ?>
    
    <!-- Блок отображения активных параметров поиска (простая строка) -->
    <?php if ($hasParams): ?>
    <div class="search-active-params">
        <span class="search-params-label">Фильтры:</span>
        <span class="search-params-text"><?= $paramsString ?></span>
        <a href="<?= $resetUrlString ?>" class="search-params-clear">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Сбросить
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Всплывающий блок параметров поиска -->
    <div class="collapse mt-3" id="searchParamsCollapse">
        <div class="search-params-panel">
            <?php $form = ActiveForm::begin([
                'method' => 'get',
                'action' => $action,
                'options' => ['class' => 'row g-3', 'id' => 'search-params-form'],
            ]); ?>
            
            <!-- Скрытые поля -->
            <?= Html::hiddenInput('AdvertisementSearch[search_text]', $searchModel->search_text) ?>
            <?php if ($section): ?>
                <?= Html::hiddenInput('AdvertisementSearch[section]', $section) ?>
            <?php endif; ?>
            
            <div class="col-md-3">
                <?= $form->field($searchModel, 'price_min', [
                    'options' => ['class' => 'mb-0'],
                    'labelOptions' => ['class' => 'form-label'],
                ])->textInput(['placeholder' => 'Цена от', 'class' => 'form-control']) ?>
            </div>
            <div class="col-md-3">
                <?= $form->field($searchModel, 'price_max', [
                    'options' => ['class' => 'mb-0'],
                    'labelOptions' => ['class' => 'form-label'],
                ])->textInput(['placeholder' => 'Цена до', 'class' => 'form-control']) ?>
            </div>
            
            <div class="col-md-3">
                <?= $form->field($searchModel, 'city', [
                    'options' => ['class' => 'mb-0'],
                    'labelOptions' => ['class' => 'form-label'],
                ])->textInput(['placeholder' => 'Город', 'class' => 'form-control']) ?>
            </div>
            
            <div class="col-md-3">
                <?= $form->field($searchModel, 'type', [
                    'options' => ['class' => 'mb-0'],
                    'labelOptions' => ['class' => 'form-label'],
                ])->dropDownList(
                    ['' => 'Все типы'] + Advertisement::getTypeList(),
                    ['class' => 'form-select']
                ) ?>
            </div>
            
            <div class="col-12 text-end mt-3">
                <?= Html::submitButton('Применить', ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Сбросить', $resetUrlString, ['class' => 'btn btn-outline-secondary']) ?>
            </div>
            
            <?php ActiveForm::end(); ?>
        </div>
    </div>
</div>

<?php
// JavaScript для синхронизации форм
$script = <<< JS
// При отправке основной формы, копируем текст поиска в скрытое поле параметров
document.getElementById('main-search-form').addEventListener('submit', function(e) {
    var searchText = this.querySelector('input[name="AdvertisementSearch[search_text]"]').value;
    var paramsForm = document.getElementById('search-params-form');
    if (paramsForm) {
        var hiddenInput = paramsForm.querySelector('input[name="AdvertisementSearch[search_text]"]');
        if (hiddenInput) {
            hiddenInput.value = searchText;
        }
        e.preventDefault();
        paramsForm.submit();
    }
});

// При отправке формы параметров, копируем текст поиска в основное поле
document.getElementById('search-params-form').addEventListener('submit', function(e) {
    var searchText = this.querySelector('input[name="AdvertisementSearch[search_text]"]').value;
    var mainForm = document.getElementById('main-search-form');
    if (mainForm) {
        var mainInput = mainForm.querySelector('input[name="AdvertisementSearch[search_text]"]');
        if (mainInput) {
            mainInput.value = searchText;
        }
    }
});
JS;
$this->registerJs($script);
?>