<?php
// FILE: .\views\advertisements\_item.php

use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use app\helpers\SvgHelper;
use app\widgets\ImageGridPreview;
use app\components\FavoriteStorage;

$priceText = \app\helpers\CurrencyFormatter::display($model->price, $model->currency);
if ($model->price_negotiable) {
    $priceText .= ' (договорная)';
}

$images = $model->images;
if (count($images) > 5) {
    $images = array_slice($images, 0, 5);
}
$imagesCount = count($images);

$link = Url::toRoute(['advertisements/view', 'id' => $model->id]);

$shortInfoString = $model->getShortInfoString(', ', false);

// Состояние избранного
$favoriteStorage = new FavoriteStorage();
$isFavorite = $favoriteStorage->has($model->id);
$favoriteClass = $isFavorite ? 'is-favorite' : '';
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
            <button type="button"
                    class="favorite-btn <?= $favoriteClass ?>"
                    data-id="<?= (int)$model->id ?>"
                    aria-label="<?= $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' ?>"
                    title="<?= $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' ?>">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="media-body" style="display: flex; flex-direction: column; height: 100%; position: relative;">
        <?php if ($imagesCount === 0): ?>
            <button type="button"
                    class="favorite-btn <?= $favoriteClass ?>"
                    data-id="<?= (int)$model->id ?>"
                    aria-label="<?= $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' ?>"
                    title="<?= $isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' ?>"
                    style="top: 0; right: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </button>
        <?php endif; ?>

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