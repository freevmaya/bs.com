<?php

use yii\helpers\Html;
use yii\grid\GridView;

$this->title = 'Мои объявления';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="advertisements-my">
    <h1><?= Html::encode($this->title) ?></h1>
    
    <p>
        <?= Html::a('Добавить объявление', ['create'], ['class' => 'btn btn-success']) ?>
    </p>
    
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            
            [
                'attribute' => 'title',
                'format' => 'raw',
                'value' => function ($model) {
                    return Html::a(Html::encode($model->title), ['view', 'id' => $model->id]);
                },
            ],
            [
                'attribute' => 'section',
                'value' => function ($model) {
                    return $model->getSectionLabel();
                },
            ],
            [
                'attribute' => 'price',
                'value' => function ($model) {
                    if ($model->price) {
                        return number_format($model->price, 0, '.', ' ') . ' ₽';
                    }
                    return 'не указана';
                },
            ],
            [
                'attribute' => 'status',
                'value' => function ($model) {
                    return $model->getStatusLabel();
                },
            ],
            [
                'attribute' => 'created_at',
                'format' => ['date', 'php:d.m.Y H:i'],
            ],
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view} {update} {delete}',
                'buttons' => [
                    'view' => function ($url, $model) {
                        return Html::a('Просмотр', ['view', 'id' => $model->id], ['class' => 'btn btn-sm btn-primary']);
                    },
                    'update' => function ($url, $model) {
                        return Html::a('Редакт.', ['update', 'id' => $model->id], ['class' => 'btn btn-sm btn-warning']);
                    },
                    'delete' => function ($url, $model) {
                        return Html::a('Удалить', ['delete', 'id' => $model->id], [
                            'class' => 'btn btn-sm btn-danger',
                            'data' => [
                                'confirm' => 'Вы уверены?',
                                'method' => 'post',
                            ],
                        ]);
                    },
                ],
            ],
        ],
    ]); ?>
</div>