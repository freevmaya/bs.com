<?php
// FILE: views/wings/view.php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var app\models\Wing $wing
 * @var array $sections
 */

$this->title = $wing->full_name;
$this->params['breadcrumbs'][] = ['label' => 'Крылья', 'url' => ['/database']];
$this->params['breadcrumbs'][] = $this->title;

$this->registerCssFile('@web/css/wing-view.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$svgPath = Yii::getAlias('@webroot') . '/images/wing.svg';
$svgContent = is_file($svgPath) ? file_get_contents($svgPath) : '';

// Силовые секции (без .brake) и .brake — раздельно
$powerSections = [];
$brakeSections = [];

foreach ($sections['sections'] as $sectionIndex => $section) {
    if ($section['is_brake']) {
        $brakeSections[] = ['section' => $section, 'index' => $sectionIndex];
    } else {
        $powerSections[] = ['section' => $section, 'index' => $sectionIndex];
    }
}

// Левая половина: силовые секции в обратном порядке (AR1/BR1/CR1 у центра)
$leftSections = array_reverse($powerSections);
// Правая половина: силовые секции в прямом порядке (AR1/BR1/CR1 у центра)
$rightSections = $powerSections;
?>

<div class="wing-view">

    <div class="wing-view-back">
        <?= Html::a(
            '<span class="glyphicon glyphicon-arrow-left"></span> Назад в каталог',
            ['wings/index'],
            ['class' => 'btn btn-sm btn-outline-secondary']
        ) ?>
    </div>
    <h2><?= Html::encode($wing->full_name) ?></h2>

    <?php if ($wing->manufacturer): ?>
        <p class="text-muted">
            Производитель: <?= Html::encode($wing->manufacturer->name) ?>
            <?php if ($wing->model_year): ?>
                &middot; Год: <?= Html::encode($wing->model_year) ?>
            <?php endif; ?>
            &middot; Размер: <?= Html::encode($wing->size) ?>
        </p>
    <?php endif; ?>

    <?php
    $wingFiles = $wing->getFiles()->all();
    ?>
    <?php if (!empty($wingFiles)): ?>
        <div class="box wing-files-used">
            <h3>Files used</h3>

            <table class="table table-sm wing-files-table">
                <tbody>
                    <?php foreach ($wingFiles as $file): ?>
                        <tr>
                            <td class="wing-file-date">
                                <?= $file->file_date ? Html::encode($file->file_date) : '' ?>
                            </td>
                            <td class="wing-file-name">
                                <a href="<?= Html::encode($file->url) ?>"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    <?= Html::encode($file->name) ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>    

    <?php if (empty($sections['sections'])): ?>
        <div class="alert alert-info">
            Для этого крыла данные о стропах отсутствуют.
        </div>
    <?php else: ?>

        <div class="box">
            <h3>Data</h3>

            <div id="wing" class="wing detail">

                <?php /* Левая половина */ ?>
                <div class="left" data-side="left">
                    <div class="wing-svg wing-svg-flipped"><?= $svgContent ?></div>

                    <div class="sections">
                        <?php foreach ($leftSections as $item): ?>
                            <?= $this->render('_section', [
                                'section' => $item['section'],
                                'index'   => $item['index'],
                            ]) ?>
                        <?php endforeach; ?>
                    </div>

                    <?php /* .brake — внизу, на всю ширину половины */ ?>
                    <?php foreach ($brakeSections as $item): ?>
                        <div class="brake">
                            <?= $this->render('_section', [
                                'section' => $item['section'],
                                'index'   => 99,
                            ]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php /* Правая половина */ ?>
                <div class="right" data-side="right">
                    <div class="wing-svg"><?= $svgContent ?></div>

                    <div class="sections">
                        <?php foreach ($rightSections as $item): ?>
                            <?= $this->render('_section', [
                                'section' => $item['section'],
                                'index'   => $item['index'],
                            ]) ?>
                        <?php endforeach; ?>
                    </div>

                    <?php /* .brake — внизу, на всю ширину половины */ ?>
                    <?php foreach ($brakeSections as $item): ?>
                        <div class="brake">
                            <?= $this->render('_section', [
                                'section' => $item['section'],
                                'index'   => 99,
                            ]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>

<?php if (!empty($csvTable['prefixes']) && $csvTable['max_rows'] > 0): ?>
    <?php
    // Готовим текст CSV: строки через \n, колонки через \t
    $csvRows = [];
    for ($i = 0; $i < $csvTable['max_rows']; $i++) {
        $cells = [];
        foreach ($csvTable['prefixes'] as $prefix) {
            $cells[] = $csvTable['columns'][$prefix][$i] ?? '';
        }
        $csvRows[] = implode("\t", $cells);
    }
    $csvText = implode("\n", $csvRows);

    // Имя файла: full_name, нормализованный
    $fileBase = preg_replace('~[^\p{L}\p{N}]+~u', '_', $wing->full_name);
    $fileBase = trim($fileBase, '_');
    if ($fileBase === '') {
        $fileBase = 'wing_' . $wing->id;
    }
    $fileName = $fileBase . '.csv';
    ?>
    <div class="box wing-csv-block">
        <h3>Все данные (CSV)</h3>

        <pre class="wing-csv" id="wing-csv-pre"><?= Html::encode($csvText) ?></pre>

        <div class="wing-csv-actions">
            <button type="button"
                    class="btn btn-sm btn-primary"
                    id="wing-csv-copy">
                <span class="glyphicon glyphicon-copy"></span> Копировать
            </button>
            <button type="button"
                    class="btn btn-sm btn-success"
                    id="wing-csv-save"
                    data-filename="<?= Html::encode($fileName) ?>">
                <span class="glyphicon glyphicon-download-alt"></span> Сохранить
            </button>
        </div>
    </div>

    <?php
    $this->registerJs(<<<JS
(function () {
    var pre = document.getElementById('wing-csv-pre');
    var copyBtn = document.getElementById('wing-csv-copy');
    var saveBtn = document.getElementById('wing-csv-save');

    if (!pre || !copyBtn || !saveBtn) {
        return;
    }

    function getCsvText() {
        return pre.textContent || '';
    }

    function notify(message, type) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type || 'info', 2500);
        }
    }

    // --- Копировать ---
    copyBtn.addEventListener('click', function () {
        var text = getCsvText();

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function () {
                notify('Скопировано в буфер обмена', 'success');
            }).catch(function () {
                fallbackCopy(text);
            });
        } else {
            fallbackCopy(text);
        }
    });

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        ta.style.left = '-1000px';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        ta.setSelectionRange(0, text.length);

        var ok = false;
        try {
            ok = document.execCommand('copy');
        } catch (e) {
            ok = false;
        }

        document.body.removeChild(ta);

        if (ok) {
            notify('Скопировано в буфер обмена', 'success');
        } else {
            notify('Не удалось скопировать', 'danger');
        }
    }

    // --- Сохранить ---
    saveBtn.addEventListener('click', function () {
        var text = getCsvText();
        var filename = saveBtn.getAttribute('data-filename') || 'wing.csv';

        // BOM для корректного открытия в Excel с UTF-8
        var blob = new Blob(['\ufeff' + text], { type: 'text/csv;charset=utf-8;' });

        if (window.navigator && window.navigator.msSaveOrOpenBlob) {
            // IE / старый Edge
            window.navigator.msSaveOrOpenBlob(blob, filename);
            notify('Файл сохранён', 'success');
            return;
        }

        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function () { URL.revokeObjectURL(url); }, 1000);

        notify('Файл сохранён', 'success');
    });
})();
JS, \yii\web\View::POS_READY);
    ?>
<?php endif; ?>

<div id="wing-point-popup" class="wing-point-popup" role="tooltip" aria-hidden="true"></div>

<?php
$this->registerJs(<<<JS
(function () {
    var popup = document.getElementById('wing-point-popup');
    if (!popup) {
        return;
    }

    var currentTarget = null;
    var hideTimer = null;

    function positionPopup(el, text) {
        popup.textContent = text;

        var rect = el.getBoundingClientRect();
        var top = rect.top + window.scrollY - 6;   // чуть выше точки
        var left = rect.left + window.scrollX + rect.width / 2;

        popup.style.top = top + 'px';
        popup.style.left = left + 'px';
        popup.classList.add('is-visible');
        popup.setAttribute('aria-hidden', 'false');
    }

    function hidePopup() {
        popup.classList.remove('is-visible');
        popup.setAttribute('aria-hidden', 'true');
        currentTarget = null;
    }

    function showFor(el) {
        if (!el || !el.classList.contains('point')) {
            return;
        }

        var name = el.getAttribute('data-name') || '';
        var length = el.getAttribute('data-length') || '';

        if (!name) {
            return;
        }

        var text = length !== ''
            ? length
            : '—';

        currentTarget = el;
        positionPopup(el, text);
    }

    // Наведение мышью
    document.querySelectorAll('.wing-view .wing .point').forEach(function (el) {
        el.addEventListener('mouseenter', function () {
            clearTimeout(hideTimer);
            showFor(el);
        });

        el.addEventListener('mouseleave', function () {
            hideTimer = setTimeout(hidePopup, 60);
        });
    });

    // Тач / клик
    document.querySelectorAll('.wing-view .wing .point').forEach(function (el) {
        el.addEventListener('touchstart', function (e) {
            e.preventDefault();
            showFor(el);
        }, { passive: false });

        el.addEventListener('click', function (e) {
            e.stopPropagation();
            showFor(el);
        });
    });

    // Скрытие по клику/тапу вне точки
    document.addEventListener('click', function (e) {
        if (!e.target.closest || !e.target.closest('.point')) {
            hidePopup();
        }
    });

    document.addEventListener('touchstart', function (e) {
        if (!e.target.closest || !e.target.closest('.point')) {
            hidePopup();
        }
    }, { passive: true });

    // Скрытие при скролле и ресайзе — иначе popup «зависает» не на месте
    window.addEventListener('scroll', function () {
        if (currentTarget) {
            hidePopup();
        }
    }, { passive: true });

    window.addEventListener('resize', function () {
        if (currentTarget) {
            hidePopup();
        }
    });
})();
JS, \yii\web\View::POS_READY);
?>