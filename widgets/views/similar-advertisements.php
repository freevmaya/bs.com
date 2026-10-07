<?php
// FILE: .\widgets\views\similar-advertisements.php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var \app\models\Advertisement[] $advertisements
 */
?>

<div class="panel panel-default similar-advertisements">
    <div class="panel-heading">
        <h4 class="panel-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px; vertical-align: middle;">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            Возможно, это вас заинтересует
        </h4>
    </div>
    <div class="panel-body similar-advertisements-list">
        <?php foreach ($advertisements as $item): ?>
            <?= $this->render('_similar_item', [
                'ad' => $item['ad'],
                'reason' => $item['reason'],
            ]) ?>
        <?php endforeach; ?>
    </div>
</div>