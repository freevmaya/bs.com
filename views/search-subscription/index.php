<?php
// FILE: .\views\search-subscription\index.php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;

$this->registerJsFile('@web/js/search-subscription.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

$this->title = 'Мои подписки на поиск';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="search-subscription-index">
    <h1><?= Html::encode($this->title) ?></h1>

    <?php if (empty($subscriptions)): ?>
        <div class="alert alert-info">
            <p>У вас нет активных подписок на поиск.</p>
            <p>Вы можете создать подписку, заполнив параметры поиска и нажав кнопку "Подписаться".</p>
        </div>
    <?php else: ?>
        <div class="row">
            <?php foreach ($subscriptions as $subscription): 
                // Получаем очищенные параметры из подписки
                $searchParams = $subscription->getParamsArray();
                
                // Строим параметры для URL в формате AdvertisementSearch[param]
                $queryParams = [];
                
                // Добавляем раздел
                if ($subscription->section) {
                    $queryParams['AdvertisementSearch[section]'] = $subscription->section;
                }
                
                // Проходим по всем параметрам
                foreach ($searchParams as $key => $value) {
                    // Пропускаем пустые значения
                    if ($value === '' || $value === null) {
                        continue;
                    }
                    
                    // Если это массив (например, producer_ids, certification_ids, sizes)
                    if (is_array($value)) {
                        $filteredValues = array_filter($value, function($item) {
                            return $item !== '' && $item !== null && $item !== '0';
                        });
                        
                        if (!empty($filteredValues)) {
                            foreach ($filteredValues as $item) {
                                // Правильный формат: AdvertisementSearch[glider_producer_ids][]
                                $queryParams['AdvertisementSearch[' . $key . '][]'] = $item;
                            }
                        }
                    } else {
                        // Для простых значений: AdvertisementSearch[glider_weight]=70
                        $queryParams['AdvertisementSearch[' . $key . ']'] = $value;
                    }
                }
                
                // Формируем URL
                $searchUrl = Url::toRoute(array_merge(
                    ["advertisements/" . $subscription->section], 
                    $queryParams
                ));
            ?>
                <div class="col-md-6 col-lg-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <span class="label label-<?= $subscription->section === 'sell' ? 'danger' : 'info' ?>">
                                    <?= $subscription->section === 'sell' ? 'Продам' : 'Куплю' ?>
                                </span>
                            </h5>
                            <p class="card-text">
                                <strong>Параметры:</strong><br>
                                <?= Html::encode($subscription->getDescription()) ?>
                            </p>
                            <p class="card-text">
                                <small class="text-muted">
                                    Создана: <?= Yii::$app->formatter->asDate($subscription->created_at) ?>
                                </small>
                            </p>
                            <div class="btn-group" role="group">
                                <?= Html::a(
                                    '<span class="glyphicon glyphicon-search"></span> Искать',
                                    $searchUrl,
                                    ['class' => 'btn btn-primary btn-sm']
                                ) ?>
                                <button class="btn btn-danger btn-sm delete-subscription" 
                                        data-id="<?= $subscription->id ?>">
                                    <span class="glyphicon glyphicon-trash"></span> Отписаться
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p>
        <?= Html::a('← Назад к объявлениям', ['advertisements/index'], ['class' => 'btn btn-default']) ?>
    </p>
</div>