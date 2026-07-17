<?php

use yii\helpers\Html;
use yii\widgets\ListView;
use app\widgets\SearchBar;

$this->title = 'Все объявления';
$this->params['breadcrumbs'][] = $this->title;

// Проверяем, авторизован ли пользователь
$isGuest = Yii::$app->user->isGuest;
?>

<div class="advertisements-index">
    <div class="row">
        <div class="col-md-12">
            <div class="d-flex justify-content-between align-items-center flex-wrap" style="margin-bottom: 15px;">
                <h1 style="margin: 0;"><?= Html::encode($this->title) ?></h1>
            </div>
        </div>
    </div>
    
    <!-- Блок поиска -->
    <?= SearchBar::widget([
        'searchModel' => $searchModel,
        'action' => ['index'],
    ]) ?>
    
    <div class="d-flex justify-content-between align-items-center search-ext-block">
        <div class="sort-options">
            <form method="get" class="form-inline">
                <div class="form-group">
                    <select name="sort" class="form-control" onchange="this.form.submit()">
                        <option value="-updated_at" <?= Yii::$app->request->get('sort') == '-updated_at' ? 'selected' : '' ?>>
                            Обновлённые
                        </option>
                        <option value="-created_at" <?= Yii::$app->request->get('sort') == '-created_at' ? 'selected' : '' ?>>
                            Новые
                        </option>
                        <option value="-price" <?= Yii::$app->request->get('sort') == '-price' ? 'selected' : '' ?>>Дороже</option>
                        <option value="price" <?= Yii::$app->request->get('sort') == 'price' ? 'selected' : '' ?>>Дешевле</option>
                        <option value="-views_count" <?= Yii::$app->request->get('sort') == '-views_count' ? 'selected' : '' ?>>Популярные</option>
                    </select>
                </div>
            </form>
        </div>
        <div style="flex-shrink: 0;">
            <?= Html::a(
                '<span class="glyphicon glyphicon-plus"></span> Разместить объявление',
                $isGuest ? ['/site/login'] : ['create'],
                [
                    'class' => 'btn btn-success',
                    'style' => 'white-space: nowrap;',
                    'data' => $isGuest ? [
                        'method' => 'get',
                        'confirm' => 'Для размещения объявления необходимо авторизоваться. Перейти на страницу входа?',
                    ] : [],
                ]
            ) ?>
        </div>
    </div>
    
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