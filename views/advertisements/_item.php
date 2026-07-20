<?php

use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use app\widgets\ImageGridPreview;

$priceText = $model->price ? number_format($model->price, 0, '.', ' ') . ' ₽' : 'Цена не указана';
if ($model->price_negotiable) {
    $priceText .= ' (договорная)';
}

$images = $model->images;
if (count($images) > 5) {
    $images = array_slice($images, 0, 5);
}
$imagesCount = count($images);

$link = Url::toRoute(['advertisements/view', 'id' => $model->id]);

// ✅ Используем метод getShortInfoString() который делегирует к типу
$shortInfoString = $model->getShortInfoString(', ', false);
?>

<div class="media advertisement-item" data-advertisement-id="<?= $model->id ?>">
    <?php if ($imagesCount > 0): ?>
    <div class="media-left">
        <div class="grid-preview-wrapper">
            <?= ImageGridPreview::widget([
                'images' => $images,
                'maxImages' => 5,
                'containerClass' => 'image-grid-preview',
            ]) ?>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="media-body">
        <a href="<?= $link ?>" style="text-decoration: none; color: inherit;">
            <h4 class="media-heading" style="margin-top: 0;">
                <?= Html::encode($model->title) ?>
            </h4>
            
            <div class="advertisement-meta">
                <span class="glyphicon glyphicon-tag"></span> <strong><?= $priceText ?></strong>
                <?php if ($model->city): ?>
                    , <span class="glyphicon glyphicon-map-marker"></span> <?= Html::encode($model->city) ?>
                <?php endif; ?>
                , <span class="glyphicon glyphicon-time"></span> <?= Yii::$app->formatter->asDate($model->created_at) ?>
                , <span class="glyphicon glyphicon-eye-open"></span> <?= $model->views_count ?>
                <?php if ($imagesCount > 0): ?>
                    , <span class="glyphicon glyphicon-picture"></span> <?= $imagesCount ?>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($shortInfoString)): ?>
                <div class="preview">
                    <?= $shortInfoString ?>
                </div>
            <?php endif; ?>
            
            <p style="margin-bottom: 8px;"><?= Html::encode(StringHelper::truncate($model->description, 120)) ?></p>
        </a>
    </div>
</div>