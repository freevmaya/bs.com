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



    <div class="sort-options">
        <form method="get" class="form-inline">
            <div class="form-group">
                <select name="sort" class="form-control" onchange="this.form.submit()">
                    <option value="-created_at" <?= Yii::$app->request->get('sort') == '-created_at' ? 'selected' : '' ?>>
                        Новые сверху
                    </option>
                    <option value="created_at" <?= Yii::$app->request->get('sort') == 'created_at' ? 'selected' : '' ?>>
                        Старые сверху
                    </option>
                    <option value="-price" <?= Yii::$app->request->get('sort') == '-price' ? 'selected' : '' ?>>
                        Дорогие сверху
                    </option>
                    <option value="price" <?= Yii::$app->request->get('sort') == 'price' ? 'selected' : '' ?>>
                        Дешевые сверху
                    </option>
                    <option value="-views_count" <?= Yii::$app->request->get('sort') == '-views_count' ? 'selected' : '' ?>>
                        Популярные
                    </option>
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
            ]); ?>
        </div>
    </div>
</div>