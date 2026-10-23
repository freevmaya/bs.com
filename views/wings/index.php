<?php
// FILE: views/wings/index.php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 * @var array $byManufacturer
 * @var app\models\Wing[] $noManufacturer
 */

$this->title = 'Каталог крыльев';
$this->params['breadcrumbs'][] = $this->title;

$this->registerCssFile('@web/css/wings-index.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
?>

<div class="wings-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <div class="wings-index-filter">
        <input type="text"
               id="wings-filter"
               class="form-control"
               placeholder="Фильтр по названию…"
               autocomplete="off">
    </div>

    <?php if (empty($byManufacturer) && empty($noManufacturer)): ?>
        <div class="alert alert-info">
            В каталоге пока нет крыльев.
        </div>
    <?php endif; ?>

    <?php foreach ($byManufacturer as $manufacturerId => $m): ?>
        <?php $manufacturer = $m['manufacturer']; ?>
        <div class="box21"
             data-filter-item="manufacturer"
             data-filter-text="<?= Html::encode(mb_strtolower($manufacturer->name)) ?>">
            <h3><?= Html::encode($manufacturer->name) ?></h3>

            <?php if (!empty($manufacturer->website)): ?>
                <p class="description">
                    <a href="<?= Html::encode($manufacturer->website) ?>" target="_blank" rel="noopener noreferrer">
                        <?= Html::encode($manufacturer->website) ?>
                    </a>
                </p>
            <?php endif; ?>

            <div class="models">
                <?php foreach ($m['models'] as $modelKey => $model): ?>
                    <?php
                    $modelTitle = $model['model'];
                    if (!empty($model['year'])) {
                        $modelTitle .= ' (' . $model['year'] . ')';
                    }
                    $filterText = mb_strtolower($manufacturer->name . ' ' . $modelTitle);
                    ?>
                    <div class="section"
                         data-filter-item="model"
                         data-filter-text="<?= Html::encode($filterText) ?>">
                        <h5><?= Html::encode($modelTitle) ?></h5>

                        <div class="tiles">
                            <?php foreach ($model['wings'] as $wing): ?>
                                <a class="tile"
                                   href="<?= Url::to(['wings/view', 'source_id' => $wing->source_id]) ?>"
                                   title="<?= Html::encode($wing->full_name) ?>">
                                    <?= Html::encode($wing->size) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (!empty($noManufacturer)): ?>
        <div class="box21"
             data-filter-item="manufacturer"
             data-filter-text="без производителя">
            <h3>Без производителя</h3>

            <?php
            $models = [];
            foreach ($noManufacturer as $wing) {
                $key = $wing->model . '|' . ($wing->model_year ?? '');
                if (!isset($models[$key])) {
                    $models[$key] = [
                        'model' => $wing->model,
                        'year'  => $wing->model_year,
                        'wings' => [],
                    ];
                }
                $models[$key]['wings'][] = $wing;
            }
            ?>

            <div class="models">
                <?php foreach ($models as $model): ?>
                    <?php
                    $modelTitle = $model['model'];
                    if (!empty($model['year'])) {
                        $modelTitle .= ' (' . $model['year'] . ')';
                    }
                    ?>
                    <div class="section"
                         data-filter-item="model"
                         data-filter-text="<?= Html::encode($filterText) ?>">
                        <h5><?= Html::encode($modelTitle) ?></h5>

                        <div class="tiles">
                            <?php foreach ($model['wings'] as $wing): ?>
                                <a class="tile"
                                   href="<?= Url::to(['wings/view', 'source_id' => $wing->source_id]) ?>"
                                   title="<?= Html::encode($wing->full_name) ?>">
                                    <?= Html::encode($wing->size) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php
$this->registerJs(<<<JS
(function () {
    var input = document.getElementById('wings-filter');
    if (!input) {
        return;
    }

    var items = document.querySelectorAll('[data-filter-item]');

    function applyFilter() {
        var query = (input.value || '').trim().toLowerCase();

        if (query === '') {
            items.forEach(function (el) {
                el.style.display = '';
            });
            return;
        }

        items.forEach(function (el) {
            el.style.display = 'none';
        });

        document.querySelectorAll('[data-filter-item="model"]').forEach(function (modelEl) {
            var text = modelEl.getAttribute('data-filter-text') || '';
            if (text.indexOf(query) !== -1) {
                modelEl.style.display = '';
                var parent = modelEl.closest('[data-filter-item="manufacturer"]');
                if (parent) {
                    parent.style.display = '';
                }
            }
        });

        document.querySelectorAll('[data-filter-item="manufacturer"]').forEach(function (manEl) {
            var text = manEl.getAttribute('data-filter-text') || '';
            if (text.indexOf(query) !== -1) {
                manEl.style.display = '';
                manEl.querySelectorAll('[data-filter-item="model"]').forEach(function (modelEl) {
                    modelEl.style.display = '';
                });
            }
        });
    }

    input.addEventListener('input', applyFilter);
})();
JS, \yii\web\View::POS_READY);
?>