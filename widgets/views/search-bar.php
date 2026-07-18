<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use app\models\Advertisement;
use app\models\AdvertisementGlider;
use app\models\AdvertisementHarness;
use app\models\AdvertisementDevice;

/**
 * @var \app\models\AdvertisementSearch $searchModel
 * @var string|null $section
 * @var array $action
 * @var array $producers
 * @var array $certifications
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

// Добавляем параметры для glider
if ($searchModel->type === 'glider') {
    if (!empty($searchModel->glider_model)) {
        $paramParts[] = 'Модель: ' . Html::encode($searchModel->glider_model);
    }
    if (!empty($searchModel->glider_producer_ids)) {
        $producerIds = array_filter($searchModel->glider_producer_ids, function($id) {
            return $id !== '' && $id !== null && $id !== '0' && $id !== 0;
        });
        if (!empty($producerIds)) {
            $producerNames = ArrayHelper::getColumn(
                \app\models\Producer::find()->where(['id' => $producerIds])->all(),
                'name'
            );
            if (!empty($producerNames)) {
                $paramParts[] = 'Производитель: ' . implode(', ', $producerNames);
            }
        }
    }
    if (!empty($searchModel->glider_certification_ids)) {
        $certNames = ArrayHelper::getColumn(
            \app\models\Certification::find()->where(['id' => $searchModel->glider_certification_ids])->all(),
            'name'
        );
        $paramParts[] = 'Сертификация: ' . implode(', ', $certNames);
    }
    if (!empty($searchModel->glider_weight)) {
        $paramParts[] = 'Вес: ' . $searchModel->glider_weight . ' кг';
    }
    if (!empty($searchModel->glider_date_release_min)) {
        $paramParts[] = 'Год выпуска от: ' . $searchModel->glider_date_release_min;
    }
    if (!empty($searchModel->glider_flight_time_max)) {
        $paramParts[] = 'Налёт до: ' . $searchModel->glider_flight_time_max . ' ч';
    }
    if (!empty($searchModel->glider_condition)) {
        $conditionList = AdvertisementGlider::getConditionList();
        $paramParts[] = 'Состояние: ' . ($conditionList[$searchModel->glider_condition] ?? $searchModel->glider_condition);
    }
}

// Добавляем параметры для harness
if ($searchModel->type === 'harness') {
    if (!empty($searchModel->harness_model)) {
        $paramParts[] = 'Модель: ' . Html::encode($searchModel->harness_model);
    }
    if (!empty($searchModel->harness_producer_ids)) {
        $producerNames = ArrayHelper::getColumn(
            \app\models\Producer::find()->where(['id' => $searchModel->harness_producer_ids])->all(),
            'name'
        );
        $paramParts[] = 'Производитель: ' . implode(', ', $producerNames);
    }
    if (!empty($searchModel->harness_sizes)) {
        $paramParts[] = 'Размер: ' . implode(', ', $searchModel->harness_sizes);
    }
    if (!empty($searchModel->harness_date_release_min)) {
        $paramParts[] = 'Год выпуска от: ' . $searchModel->harness_date_release_min;
    }
    if (!empty($searchModel->harness_condition)) {
        $conditionList = AdvertisementHarness::getConditionList();
        $paramParts[] = 'Состояние: ' . ($conditionList[$searchModel->harness_condition] ?? $searchModel->harness_condition);
    }
}

// Добавляем параметры для device
if ($searchModel->type === 'device') {
    if (!empty($searchModel->device_model)) {
        $paramParts[] = 'Модель: ' . Html::encode($searchModel->device_model);
    }
    if (!empty($searchModel->device_producer_ids)) {
        $producerNames = ArrayHelper::getColumn(
            \app\models\Producer::find()->where(['id' => $searchModel->device_producer_ids])->all(),
            'name'
        );
        $paramParts[] = 'Производитель: ' . implode(', ', $producerNames);
    }
    if (!empty($searchModel->device_condition)) {
        $conditionList = AdvertisementDevice::getConditionList();
        $paramParts[] = 'Состояние: ' . ($conditionList[$searchModel->device_condition] ?? $searchModel->device_condition);
    }
}

// Объединяем параметры через запятую
$paramsString = implode(', ', $paramParts);

// Флаг наличия активных параметров
$hasParams = !empty($paramParts);

// Преобразуем resetUrl в строку для ссылки
$resetUrlString = Url::to($resetUrl);

// Списки для выпадающих полей
$producerList = ArrayHelper::map($producers, 'id', 'fullName');
$certificationList = ArrayHelper::map($certifications, 'id', 'name');
$conditionListGlider = AdvertisementGlider::getConditionList();
$conditionListHarness = AdvertisementHarness::getConditionList();
$conditionListDevice = AdvertisementDevice::getConditionList();
$sizeList = AdvertisementHarness::getSizeList();

// Определяем, какой тип выбран для отображения дополнительных полей
$selectedType = $searchModel->type ?? '';
$showGliderFields = ($selectedType === 'glider');
$showHarnessFields = ($selectedType === 'harness');
$showDeviceFields = ($selectedType === 'device');

// Регистрируем JS для кнопки подписки в блоке активных параметров
$this->registerJsFile('@web/js/search-active-subscribe.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);
?>

<div class="search-bar-container" style="position: relative;">
    
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
        <button class="btn btn-outline-secondary btn-lg btn-params" type="button" id="search-params-toggle" aria-expanded="false" aria-label="Параметры поиска">
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
        </button>
    </div>
    
    <?php ActiveForm::end(); ?>
    
    <!-- Блок отображения активных параметров поиска с кнопкой подписки -->
    <?php if ($hasParams): ?>
    <div class="search-active-params">
        <span class="search-params-label" style="cursor: pointer;" title="Кликните для открытия параметров поиска">Фильтры:</span>
        <span class="search-params-text" style="cursor: pointer;" title="Кликните для открытия параметров поиска"><?= $paramsString ?></span>
        
        <!-- Кнопка подписки -->
        <button type="button" class="search-subscribe-btn" data-section="<?= $section ?>" style="transition: all 0.3s ease;">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <span class="btn-text">Подписаться</span>
        </button>
        
        <!-- Кнопка сброса с очисткой сессии -->
        <a href="<?= Url::to(['advertisements/reset-filters', 'section' => $section]) ?>" 
           class="search-params-clear" 
           data-method="post"
           title="Сбросить все фильтры">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="23 4 23 10 17 10"></polyline>
                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
            </svg>
            Сбросить фильтры
        </a>
    </div>
    <?php endif; ?>
    
    <!-- Всплывающий блок параметров поиска (абсолютная позиция) -->
    <div class="search-params-popup" id="searchParamsPopup">
        <div class="search-params-popup-header">
            <span class="search-params-popup-title">Параметры поиска</span>
            <button type="button" class="search-params-popup-close" id="searchParamsClose" aria-label="Закрыть">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="search-params-popup-body">
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
                
                <!-- Основные параметры -->
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
                        ['class' => 'form-select', 'id' => 'search-type-select']
                    ) ?>
                </div>
                
                <!-- Дополнительные параметры для GLIDER -->
                <div class="col-12 extra-fields glider-fields" style="display: <?= $showGliderFields ? 'block' : 'none' ?>; margin-top: 15px;">
                    <h5 style="color: #e0e0e0; border-bottom: 1px solid #555; padding-bottom: 8px;">Параплан</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'glider_model', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->textInput(['placeholder' => 'Модель', 'class' => 'form-control']) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'glider_producer_ids', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->dropDownList(
                                $producerList,
                                [
                                    'class' => 'form-select',
                                    'multiple' => true,
                                    'size' => 4,
                                    'prompt' => 'Все производители'
                                ]
                            ) ?>
                            <small class="text-muted" style="color: #999 !important;">Для выбора нескольких зажмите Ctrl</small>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'glider_certification_ids', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->dropDownList(
                                $certificationList,
                                [
                                    'class' => 'form-select',
                                    'multiple' => true,
                                    'size' => 4,
                                    'prompt' => 'Все сертификации'
                                ]
                            ) ?>
                            <small class="text-muted" style="color: #999 !important;">Для выбора нескольких зажмите Ctrl</small>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'glider_weight', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->textInput(['placeholder' => 'Взлетный вес (кг)', 'class' => 'form-control']) ?>
                            <small class="text-muted" style="color: #999 !important;">Вес пилота + вес всего снаряжения</small>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'glider_date_release_min', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->textInput(['placeholder' => 'Год выпуска от', 'class' => 'form-control']) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'glider_flight_time_max', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->textInput(['placeholder' => 'Налёт до (часов)', 'class' => 'form-control']) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'glider_condition', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->dropDownList(
                                ['' => 'Любое'] + $conditionListGlider,
                                ['class' => 'form-select']
                            ) ?>
                        </div>
                    </div>
                </div>
                
                <!-- Дополнительные параметры для HARNESS -->
                <div class="col-12 extra-fields harness-fields" style="display: <?= $showHarnessFields ? 'block' : 'none' ?>; margin-top: 15px;">
                    <h5 style="color: #e0e0e0; border-bottom: 1px solid #555; padding-bottom: 8px;">Подвесная система</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'harness_model', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->textInput(['placeholder' => 'Модель', 'class' => 'form-control']) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'harness_producer_ids', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->dropDownList(
                                $producerList,
                                [
                                    'class' => 'form-select',
                                    'multiple' => true,
                                    'size' => 4,
                                    'prompt' => 'Все производители'
                                ]
                            ) ?>
                            <small class="text-muted" style="color: #999 !important;">Для выбора нескольких зажмите Ctrl</small>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'harness_sizes', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->dropDownList(
                                $sizeList,
                                [
                                    'class' => 'form-select',
                                    'multiple' => true,
                                    'size' => 4,
                                    'prompt' => 'Все размеры'
                                ]
                            ) ?>
                            <small class="text-muted" style="color: #999 !important;">Для выбора нескольких зажмите Ctrl</small>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'harness_date_release_min', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->textInput(['placeholder' => 'Год выпуска от', 'class' => 'form-control']) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'harness_condition', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->dropDownList(
                                ['' => 'Любое'] + $conditionListHarness,
                                ['class' => 'form-select']
                            ) ?>
                        </div>
                    </div>
                </div>
                
                <!-- Дополнительные параметры для DEVICE -->
                <div class="col-12 extra-fields device-fields" style="display: <?= $showDeviceFields ? 'block' : 'none' ?>; margin-top: 15px;">
                    <h5 style="color: #e0e0e0; border-bottom: 1px solid #555; padding-bottom: 8px;">Прибор</h5>
                    <div class="row g-3">
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'device_model', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->textInput(['placeholder' => 'Модель', 'class' => 'form-control']) ?>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'device_producer_ids', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->dropDownList(
                                $producerList,
                                [
                                    'class' => 'form-select',
                                    'multiple' => true,
                                    'size' => 4,
                                    'prompt' => 'Все производители'
                                ]
                            ) ?>
                            <small class="text-muted" style="color: #999 !important;">Для выбора нескольких зажмите Ctrl</small>
                        </div>
                        <div class="col-md-3">
                            <?= $form->field($searchModel, 'device_condition', [
                                'options' => ['class' => 'mb-0'],
                                'labelOptions' => ['class' => 'form-label'],
                            ])->dropDownList(
                                ['' => 'Любое'] + $conditionListDevice,
                                ['class' => 'form-select']
                            ) ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-12 text-end mt-3">
                    <?= Html::submitButton('Применить', ['class' => 'btn btn-primary']) ?>
                    <!-- Кнопка сброса с очисткой сессии -->
                    <?= Html::a(
                        'Сбросить',
                        ['advertisements/reset-filters', 'section' => $section],
                        [
                            'class' => 'btn btn-outline-secondary',
                            'data-method' => 'post',
                        ]
                    ) ?>
                </div>
                
                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>
</div>

<style>
/* Стили для всплывающего блока параметров */
.search-bar-container {
    position: relative;
}

.search-params-popup {
    position: absolute;
    top: calc(100% + 10px);
    left: 0;
    width: 100%;
    z-index: 100;
    background: #3b3b3b;
    border-radius: 8px;
    box-shadow: 2px 2px 12px rgba(0, 0, 0, 0.4);
    display: none;
    overflow: hidden;
    max-height: 80vh;
    overflow-y: auto;
}

.search-params-popup.open {
    display: block;
    animation: fadeInPopup 0.25s ease;
}

@keyframes fadeInPopup {
    from {
        opacity: 0;
        transform: translateY(-8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.search-params-popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    background: #2a2a2a;
    border-bottom: 1px solid #444;
    flex-shrink: 0;
}

.search-params-popup-title {
    font-size: 16px;
    font-weight: 600;
    color: #e0e0e0;
}

.search-params-popup-close {
    background: none;
    border: none;
    color: #888;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.search-params-popup-close:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.1);
}

.search-params-popup-body {
    padding: 20px;
}

/* Стили для активных параметров - делаем кликабельными */
.search-params-label,
.search-params-text {
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.search-params-label:hover,
.search-params-text:hover {
    opacity: 0.8;
}

/* Для светлой темы */
[data-bs-theme="light"] .search-params-popup {
    background: #f8f9fa;
    box-shadow: 2px 2px 12px rgba(0, 0, 0, 0.2);
}

[data-bs-theme="light"] .search-params-popup-header {
    background: #e9ecef;
    border-bottom: 1px solid #dee2e6;
}

[data-bs-theme="light"] .search-params-popup-title {
    color: #212529;
}

[data-bs-theme="light"] .search-params-popup-close {
    color: #6c757d;
}

[data-bs-theme="light"] .search-params-popup-close:hover {
    color: #212529;
    background: rgba(0, 0, 0, 0.05);
}

/* Адаптивность для мобильных */
@media (max-width: 768px) {
    .search-params-popup {
        top: calc(100% + 6px);
        border-radius: 6px;
    }
    .search-params-popup-header {
        padding: 10px 16px;
    }
    .search-params-popup-title {
        font-size: 14px;
    }
    .search-params-popup-body {
        padding: 12px 16px;
    }
}

@media (max-width: 576px) {
    .search-params-popup {
        top: calc(100% + 4px);
        border-radius: 0 0 8px 8px;
        left: -10px;
        width: calc(100% + 20px);
        max-height: 70vh;
    }
    .search-params-popup-header {
        padding: 8px 14px;
    }
    .search-params-popup-body {
        padding: 10px 14px;
    }
}

/* Стили для scrollbar внутри popup */
.search-params-popup::-webkit-scrollbar {
    width: 6px;
}

.search-params-popup::-webkit-scrollbar-track {
    background: transparent;
}

.search-params-popup::-webkit-scrollbar-thumb {
    background: #555;
    border-radius: 3px;
}

[data-bs-theme="light"] .search-params-popup::-webkit-scrollbar-thumb {
    background: #ccc;
}

.search-params-popup::-webkit-scrollbar-thumb:hover {
    background: #666;
}

[data-bs-theme="light"] .search-params-popup::-webkit-scrollbar-thumb:hover {
    background: #bbb;
}
</style>

<?php
// JavaScript для управления попапом
$script = <<< JS
(function() {
    var toggleBtn = document.getElementById('search-params-toggle');
    var popup = document.getElementById('searchParamsPopup');
    var closeBtn = document.getElementById('searchParamsClose');
    var labelEl = document.querySelector('.search-params-label');
    var textEl = document.querySelector('.search-params-text');
    
    if (!toggleBtn || !popup) return;
    
    function openPopup() {
        popup.classList.add('open');
        toggleBtn.classList.add('active');
        toggleBtn.setAttribute('aria-expanded', 'true');
    }
    
    function closePopup() {
        popup.classList.remove('open');
        toggleBtn.classList.remove('active');
        toggleBtn.setAttribute('aria-expanded', 'false');
    }
    
    function togglePopup() {
        if (popup.classList.contains('open')) {
            closePopup();
        } else {
            openPopup();
        }
    }
    
    // Открытие/закрытие по клику на кнопку
    toggleBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        togglePopup();
    });
    
    // Открытие по клику на label (Фильтры:)
    if (labelEl) {
        labelEl.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!popup.classList.contains('open')) {
                openPopup();
            }
        });
    }
    
    // Открытие по клику на text (список фильтров)
    if (textEl) {
        textEl.addEventListener('click', function(e) {
            e.stopPropagation();
            if (!popup.classList.contains('open')) {
                openPopup();
            }
        });
    }
    
    // Закрытие по клику на крестик
    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closePopup();
        });
    }
    
    // Закрытие по клику вне попапа
    document.addEventListener('click', function(e) {
        if (popup.classList.contains('open')) {
            var container = document.querySelector('.search-bar-container');
            if (container && !container.contains(e.target)) {
                closePopup();
            }
        }
    });
    
    // Закрытие по Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && popup.classList.contains('open')) {
            closePopup();
        }
    });
    
    // Закрытие при отправке формы (чтобы не оставался висеть)
    var form = document.getElementById('search-params-form');
    if (form) {
        form.addEventListener('submit', function() {
            closePopup();
        });
    }
    
    console.log('Search params popup initialized');
})();
JS;
$this->registerJs($script);

// JavaScript для показа/скрытия дополнительных полей
$script2 = <<< JS
// Функция для показа/скрытия дополнительных полей
function toggleExtraFields() {
    var selectedType = document.getElementById('search-type-select').value;
    var gliderFields = document.querySelector('.glider-fields');
    var harnessFields = document.querySelector('.harness-fields');
    var deviceFields = document.querySelector('.device-fields');
    
    // Скрываем все
    if (gliderFields) gliderFields.style.display = 'none';
    if (harnessFields) harnessFields.style.display = 'none';
    if (deviceFields) deviceFields.style.display = 'none';
    
    // Показываем нужные
    if (selectedType === 'glider' && gliderFields) {
        gliderFields.style.display = 'block';
    } else if (selectedType === 'harness' && harnessFields) {
        harnessFields.style.display = 'block';
    } else if (selectedType === 'device' && deviceFields) {
        deviceFields.style.display = 'block';
    }
}

// При изменении типа
document.getElementById('search-type-select').addEventListener('change', toggleExtraFields);

// При загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    toggleExtraFields();
});
JS;
$this->registerJs($script2);

// JavaScript для синхронизации форм
$script3 = <<< JS
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
$this->registerJs($script3);