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
    
    <!-- Сортировка -->
    <div class="sort-options" style="margin-bottom: 15px;">
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