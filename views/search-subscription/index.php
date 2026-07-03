<?php
// FILE: .\views\search-subscription\index.php

use yii\helpers\Html;
use yii\helpers\Url;

$this->registerJsFile('@web/js/search-subscription.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

$this->title = 'Мои подписки на поиск';
$this->params['breadcrumbs'][] = $this->title;

$this->registerJsFile('@web/js/search-subscription.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);
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
            <?php foreach ($subscriptions as $subscription): ?>
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
                            <button class="btn btn-danger btn-sm delete-subscription" 
                                    data-id="<?= $subscription->id ?>">
                                <span class="glyphicon glyphicon-trash"></span> Отписаться
                            </button>
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