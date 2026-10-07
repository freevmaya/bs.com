<?php
// FILE: .\widgets\views\_similar_item.php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\StringHelper;
use app\models\Advertisement;

/**
 * @var \app\models\Advertisement $ad
 * @var string|null $reason
 */

$url = Url::to(['/advertisements/view', 'id' => $ad->id]);
$image = $ad->mainImage;
$thumbUrl = $image ? $image->getThumbnailUrl() : null;
$isVideo = $image ? $image->isVideo() : false;

// Год выпуска (для glider/harness)
$year = null;
$typeObject = $ad->getTypeObject();
if ($typeObject && $typeObject->hasAttribute('date_release') && !empty($typeObject->date_release)) {
    $year = $typeObject->date_release;
}

// Сертификация (только для парапланов)
$certification = null;
if ($ad->type === Advertisement::TYPE_GLIDER && $ad->glider && $ad->glider->certification) {
    $certification = $ad->glider->certification->name;
}
?>

<a href="<?= $url ?>" class="similar-item">
    <div class="similar-item-thumb">
        <?php if ($thumbUrl): ?>
            <img src="<?= Html::encode($thumbUrl) ?>" alt="<?= Html::encode($ad->title) ?>" loading="lazy">
            <?php if ($isVideo): ?>
                <div class="similar-item-video-icon">
                    <span class="glyphicon glyphicon-play"></span>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="similar-item-placeholder">
                <span class="glyphicon glyphicon-picture"></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($reason)): ?>
            <span class="similar-item-badge"><?= Html::encode($reason) ?></span>
        <?php endif; ?>
    </div>
    <div class="similar-item-body">
        <div class="similar-item-title"><?= Html::encode(StringHelper::truncate($ad->title, 40)) ?></div>
        <div class="similar-item-price">
            <?php if ($ad->price): ?>
                <?= number_format($ad->price, 0, '.', ' ') ?> <?= Advertisement::getCurrencySymbol($ad->currency) ?>
            <?php else: ?>
                <span class="text-muted">Цена не указана</span>
            <?php endif; ?>
        </div>
        <div class="similar-item-meta">
            <?php if ($certification): ?>
                <span>🪂 <?= Html::encode($certification) ?></span>
            <?php endif; ?>
            <?php if ($year): ?>
                <span>📅 <?= Html::encode($year) ?></span>
            <?php endif; ?>
            <?php if ($ad->city): ?>
                <span>📍 <?= Html::encode($ad->city) ?></span>
            <?php endif; ?>
        </div>
    </div>
</a>