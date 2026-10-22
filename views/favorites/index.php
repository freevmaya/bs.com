<?php
// FILE: .\views\favorites\index.php

use yii\helpers\Html;
use yii\widgets\ListView;
use yii\widgets\LinkPager;
use app\helpers\TextHelper;

$this->title = 'Избранное';
$this->params['breadcrumbs'][] = $this->title;

$total = $dataProvider->getTotalCount();
$countWord = TextHelper::plural($total, ['объявление', 'объявления', 'объявлений']);
?>

<div class="favorites-index">
    <div class="d-flex justify-content-between align-items-center flex-wrap" style="margin-bottom: 15px;">
        <h1 style="margin: 0;"><?= Html::encode($this->title) ?></h1>
        <span class="text-muted found-count">
            <?= (int)$total ?> <?= Html::encode($countWord) ?>
        </span>
    </div>

    <?php if ($total === 0): ?>
        <div class="alert alert-info">
            <p style="margin: 0 0 8px 0;">В избранном пока ничего нет.</p>
            <p style="margin: 0;">
                <?= Html::a('Смотреть Продам', ['/advertisements/sell']) ?> &middot;
                <?= Html::a('Смотреть Куплю', ['/advertisements/buy']) ?>
            </p>
        </div>
    <?php else: ?>
        <?= ListView::widget([
            'dataProvider' => $dataProvider,
            'itemView' => '@app/views/advertisements/_item',
            'layout' => "{items}",
            'emptyText' => '',
            'itemOptions' => ['class' => 'item'],
        ]); ?>

        <div class="pagination-wrapper">
            <?= LinkPager::widget([
                'pagination' => $dataProvider->getPagination(),
                'options' => ['class' => 'pagination justify-content-center'],
                'linkOptions' => ['class' => 'page-link'],
                'prevPageLabel' => '&laquo;',
                'nextPageLabel' => '&raquo;',
                'firstPageLabel' => 'Первая',
                'lastPageLabel' => 'Последняя',
            ]) ?>
        </div>
    <?php endif; ?>
</div>