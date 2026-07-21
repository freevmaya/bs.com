<?php

use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use app\helpers\SvgHelper;
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
    
    <div class="media-body" style="display: flex; flex-direction: column; height: 100%;">
        <a href="<?= $link ?>" class="item-link">
            <div style="flex: 1;">
                <h4 class="media-heading" style="margin-top: 0;">
                    <?= Html::encode($model->title) ?>
                </h4>
                
                <div class="advertisement-meta">
                    <span class="glyphicon glyphicon-tag"></span> <strong><?= $priceText ?></strong>
                    <?php if ($model->city): ?>, <?= Html::encode($model->city) ?>
                    <?php endif; ?>, <?= SvgHelper::render('eye', ['width' => 16, 'height' => 16, 'class' => 'svg-icon']) ?> <?= $model->views_count ?>
                </div>
                
                <?php if (!empty($shortInfoString)): ?>
                    <div class="preview">
                        <?= $shortInfoString ?>
                    </div>
                <?php endif; ?>
                
                <p style="margin-bottom: 8px;"><?= Html::encode(StringHelper::truncate($model->description, 120)) ?></p>
            </div>
            
            <div style="margin-top: auto; padding-top: 8px; border-top: 1px solid rgba(255,255,255,0.08); font-size: 12px; color: #6c757d;">
                <?= Yii::$app->formatter->asDate($model->created_at) ?>
            </div>
        </a>
    </div>
</div>