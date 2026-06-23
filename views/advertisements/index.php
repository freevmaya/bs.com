<?php

use yii\helpers\Html;
use yii\widgets\ListView;
use app\widgets\SearchBar;

$this->title = 'Все объявления';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="advertisements-index">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <!-- Блок поиска -->
    <?= SearchBar::widget([
        'searchModel' => $searchModel,
        'action' => ['index'],
    ]) ?>
    
    <!-- Контент -->
    <div class="row">
        <div class="col-md-12">
            <?= ListView::widget([
                'dataProvider' => $dataProvider,
                'itemView' => '_item',
                'layout' => "{items}\n{pager}",
                'emptyText' => 'Объявлений не найдено',
                'itemOptions' => ['class' => 'item'],
            ]); ?>
        </div>
    </div>
</div>