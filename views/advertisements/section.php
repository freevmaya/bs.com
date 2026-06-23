<?php

use yii\helpers\Html;
use yii\widgets\ListView;
use app\widgets\SearchBar;
use app\models\Advertisement;

$this->title = $sectionTitle;
$this->params['breadcrumbs'][] = $this->title;

$action = ($section === Advertisement::SECTION_SELL) ? 'sell' : 'buy';
?>

<div class="advertisements-section">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <!-- Блок поиска -->
    <?= SearchBar::widget([
        'searchModel' => $searchModel,
        'section' => $section,
        'action' => ['advertisements/' . $action],
    ]) ?>
    
    <!-- Контент -->
    <div class="row">
        <div class="col-md-12">
            <?= ListView::widget([
                'dataProvider' => $dataProvider,
                'itemView' => '_item',
                'layout' => "{items}\n{pager}",
                'emptyText' => 'Объявлений не найдено',
            ]); ?>
        </div>
    </div>
</div>