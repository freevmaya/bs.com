<?php
// FILE: views/wings/_section.php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var array $section
 * @var int   $index
 */
?>

<div class="section" data-index="<?= (int)$index ?>">
    <div class="groups">
        <?php foreach ($section['groups'] as $group): ?>
            <div class="group">
                <h3><?= Html::encode($group['name']) ?></h3>

                <div class="points">
                    <?php foreach ($group['rows'] as $row): ?>
                        <div class="row">
                            <?php foreach ($row['points'] as $point): ?>
                                <div class="point"
                                     style="flex-basis: <?= Html::encode($row['flex_basis']) ?>;"
                                     data-type="reading"
                                     data-name="<?= Html::encode($point['name']) ?>"
                                     data-length="<?= $point['length'] !== null ? (int)round($point['length']) : '' ?>"
                                     title="<?= Html::encode($point['name']) ?><?= $point['length'] !== null ? ' — ' . (int)round($point['length']) . ' мм' : '' ?>"><?= Html::encode($point['name']) ?></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>