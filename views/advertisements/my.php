<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\Url;
use app\models\Advertisement;

$this->title = 'Мои объявления';
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем JS для переключения статуса
$this->registerJs("
    $(document).on('change', '.status-toggle', function() {
        var checkbox = $(this);
        var id = checkbox.data('id');
        var status = checkbox.prop('checked') ? 'active' : 'closed';
        var row = checkbox.closest('tr');
        var statusLabel = row.find('.status-label');
        
        // Отключаем чекбокс на время запроса
        checkbox.prop('disabled', true);
        
        $.ajax({
            url: '" . Url::to(['advertisements/toggle-status']) . "',
            type: 'POST',
            data: {
                id: id,
                status: status,
                _csrf: '" . Yii::$app->request->csrfToken . "'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Обновляем метку статуса
                    if (status === 'active') {
                        statusLabel.html('<span class=\"label label-success\">Активно</span>');
                        checkbox.prop('checked', true);
                    } else {
                        statusLabel.html('<span class=\"label label-default\">Неактивно</span>');
                        checkbox.prop('checked', false);
                    }
                    // Показываем уведомление
                    if (typeof window.showNotification === 'function') {
                        window.showNotification(response.message, 'success');
                    }
                } else {
                    alert(response.error || 'Ошибка при изменении статуса');
                    // Возвращаем предыдущее состояние
                    checkbox.prop('checked', !checkbox.prop('checked'));
                }
            },
            error: function() {
                alert('Ошибка соединения с сервером');
                checkbox.prop('checked', !checkbox.prop('checked'));
            },
            complete: function() {
                checkbox.prop('disabled', false);
            }
        });
    });
", \yii\web\View::POS_READY);
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
                'format' => 'raw',
                'value' => function ($model) {
                    $isActive = ($model->status === Advertisement::STATUS_ACTIVE);
                    $label = $isActive ? 'Активно' : 'Неактивно';
                    $class = $isActive ? 'label-success' : 'label-default';
                    return '<span class="status-label label ' . $class . '">' . $label . '</span>';
                },
            ],
            [
                'label' => 'Активно',
                'format' => 'raw',
                'value' => function ($model) {
                    $isActive = ($model->status === Advertisement::STATUS_ACTIVE);
                    return Html::checkbox('status', $isActive, [
                        'class' => 'status-toggle',
                        'data-id' => $model->id,
                        'title' => $isActive ? 'Отключить объявление' : 'Включить объявление',
                    ]);
                },
                'contentOptions' => ['style' => 'text-align: center; vertical-align: middle; width: 80px;'],
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