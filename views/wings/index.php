<?php
// FILE: views/wings/index.php

use yii\helpers\Html;
use yii\helpers\Url;
use app\models\Wing;

/**
 * @var yii\web\View $this
 * @var array $byManufacturer
 * @var app\models\Wing[] $noManufacturer
 * @var array $diffStats
 * @var bool $isAdmin
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
                                <?php
                                $isActive = $wing->state === Wing::STATE_ACTIVE;
                                $tileClass = 'tile' . ($isAdmin && !$isActive ? ' tile-' . $wing->getStateColorClass() : '');
                                ?>
                                <span class="tile-wrapper">
                                    <a class="<?= Html::encode($tileClass) ?>"
                                       href="<?= Url::to(['wings/view', 'source_id' => $wing->source_id]) ?>"
                                       title="<?= Html::encode($wing->full_name) ?><?= $isAdmin && !$isActive ? ' — ' . $wing->getStateLabel() : '' ?>">
                                        <?= Html::encode($wing->size) ?>
                                    </a>
                                    <?php if ($isAdmin): ?>
                                        <span class="tile-state-badge <?= Html::encode($wing->getStateColorClass()) ?>"
                                              title="<?= Html::encode($wing->getStateLabel()) ?>"></span>
                                        <button type="button"
                                                class="tile-state-toggle"
                                                data-id="<?= (int)$wing->id ?>"
                                                data-state="<?= Html::encode($wing->state) ?>"
                                                title="Изменить состояние">▾</button>
                                    <?php endif; ?>
                                </span>
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
                         data-filter-text="<?= Html::encode(mb_strtolower('без производителя ' . $modelTitle)) ?>">
                        <h5><?= Html::encode($modelTitle) ?></h5>

                        <div class="tiles">
                            <?php foreach ($model['wings'] as $wing): ?>
                                <?php
                                $isActive = $wing->state === Wing::STATE_ACTIVE;
                                $tileClass = 'tile' . ($isAdmin && !$isActive ? ' tile-' . $wing->getStateColorClass() : '');
                                ?>
                                <span class="tile-wrapper">
                                    <a class="<?= Html::encode($tileClass) ?>"
                                       href="<?= Url::to(['wings/view', 'source_id' => $wing->source_id]) ?>"
                                       title="<?= Html::encode($wing->full_name) ?><?= $isAdmin && !$isActive ? ' — ' . $wing->getStateLabel() : '' ?>">
                                        <?= Html::encode($wing->size) ?>
                                    </a>
                                    <?php if ($isAdmin): ?>
                                        <span class="tile-state-badge <?= Html::encode($wing->getStateColorClass()) ?>"
                                              title="<?= Html::encode($wing->getStateLabel()) ?>"></span>
                                        <button type="button"
                                                class="tile-state-toggle"
                                                data-id="<?= (int)$wing->id ?>"
                                                data-state="<?= Html::encode($wing->state) ?>"
                                                title="Изменить состояние">▾</button>
                                    <?php endif; ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($diffStats)): ?>
        <div class="box wings-diff-stats">
            <h3>Статистика разброса длин строп</h3>

            <table class="table table-sm table-bordered wings-diff-table">
                <thead>
                    <tr>
                        <th>Разброс длин</th>
                        <th class="text-end">Количество крыльев</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($diffStats as $row): ?>
                        <tr>
                            <td><?= Html::encode($row['label']) ?></td>
                            <td class="text-end"><?= (int)$row['count'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th>Всего</th>
                        <th class="text-end"><?= array_sum(array_column($diffStats, 'count')) ?></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
        <div id="wing-state-menu" class="wing-state-menu" style="display:none;">
            <button type="button" data-state="active">Активно</button>
            <button type="button" data-state="require_update">Требует обновления</button>
            <button type="button" data-state="hide">Скрыть</button>
            <button type="button" data-state="delete">На удаление</button>
        </div>
    <?php endif; ?>

</div>

<?php
$this->registerJs(<<<JS
(function () {
    // --- Фильтр ---
    var input = document.getElementById('wings-filter');
    var items = document.querySelectorAll('[data-filter-item]');

    if (input) {
        input.addEventListener('input', function () {
            var query = (input.value || '').trim().toLowerCase();

            if (query === '') {
                items.forEach(function (el) { el.style.display = ''; });
                return;
            }

            items.forEach(function (el) { el.style.display = 'none'; });

            document.querySelectorAll('[data-filter-item="model"]').forEach(function (modelEl) {
                var text = modelEl.getAttribute('data-filter-text') || '';
                if (text.indexOf(query) !== -1) {
                    modelEl.style.display = '';
                    var parent = modelEl.closest('[data-filter-item="manufacturer"]');
                    if (parent) { parent.style.display = ''; }
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
        });
    }

    // --- Меню состояния (только для админа) ---
    var menu = document.getElementById('wing-state-menu');
    if (!menu) { return; }

    var currentBtn = null;

    function hideMenu() {
        menu.style.display = 'none';
        currentBtn = null;
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('.tile-state-toggle');
        if (toggle) {
            e.preventDefault();
            e.stopPropagation();

            if (currentBtn === toggle) {
                hideMenu();
                return;
            }

            currentBtn = toggle;
            var rect = toggle.getBoundingClientRect();
            menu.style.top = (rect.bottom + window.scrollY + 4) + 'px';
            menu.style.left = (rect.left + window.scrollX) + 'px';
            menu.style.display = 'block';

            // Подсветить текущее состояние
            var currentState = toggle.getAttribute('data-state');
            menu.querySelectorAll('button').forEach(function (b) {
                b.classList.toggle('current', b.getAttribute('data-state') === currentState);
            });
            return;
        }

        if (!e.target.closest('#wing-state-menu')) {
            hideMenu();
        }
    });

    menu.addEventListener('click', function (e) {
        var btn = e.target.closest('button[data-state]');
        if (!btn || !currentBtn) { return; }
        e.preventDefault();
        e.stopPropagation();

        var wingId = currentBtn.getAttribute('data-id');
        var newState = btn.getAttribute('data-state');
        var csrf = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrf ? csrf.getAttribute('content') : '';

        var toggleEl = currentBtn;
        toggleEl.disabled = true;

        var form = new URLSearchParams();
        form.append('id', wingId);
        form.append('state', newState);
        form.append('_csrf', csrfToken);

        fetch('/wings/set-state', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
            },
            body: form.toString(),
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data && data.success) {
                toggleEl.setAttribute('data-state', data.state);
                var wrapper = toggleEl.closest('.tile-wrapper');
                if (wrapper) {
                    var tile = wrapper.querySelector('.tile');
                    if (tile) {
                        tile.classList.remove(
                            'tile-state-active',
                            'tile-state-require-update',
                            'tile-state-hide',
                            'tile-state-delete'
                        );
                        if (data.state !== 'active') {
                            tile.classList.add('tile-' + data.state_class);
                        }
                    }
                    var badge = wrapper.querySelector('.tile-state-badge');
                    if (badge) {
                        badge.classList.remove(
                            'state-active',
                            'state-require-update',
                            'state-hide',
                            'state-delete'
                        );
                        badge.classList.add(data.state_class);
                        badge.setAttribute('title', data.state_label);
                    }
                }
                if (typeof window.showNotification === 'function') {
                    window.showNotification('Состояние: ' + data.state_label, 'success', 2000);
                }
            } else {
                if (typeof window.showNotification === 'function') {
                    window.showNotification((data && data.error) || 'Ошибка', 'danger');
                }
            }
        })
        .catch(function () {
            if (typeof window.showNotification === 'function') {
                window.showNotification('Ошибка соединения', 'danger');
            }
        })
        .then(function () {
            toggleEl.disabled = false;
            hideMenu();
        });
    });

    window.addEventListener('scroll', function () {
        if (menu.style.display === 'block') { hideMenu(); }
    }, { passive: true });
})();
JS, \yii\web\View::POS_READY);
?>