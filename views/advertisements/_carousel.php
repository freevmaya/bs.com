<?php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var array $images - массив изображений
 * @var string $id - уникальный ID для галереи
 */

if (empty($images)) {
    return;
}

$id = $id ?? 'gallery-' . uniqid();

// Регистрируем CSS и JS для карусели
$this->registerCssFile('@web/css/advertisement-form.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/carousel.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);
?>

<div class="gallery-container">
    <div class="gallery-grid" id="<?= $id ?>">
        <?php foreach ($images as $index => $image): ?>
            <?php 
            $isVideo = $image->isVideo();
            $fullUrl = $image->getImageUrl();
            $thumbUrl = $image->getThumbnailUrl();
            ?>
            <div class="gallery-item <?= $isVideo ? 'video-item' : '' ?>" 
                 data-index="<?= $index ?>"
                 data-is-video="<?= $isVideo ? 'true' : 'false' ?>"
                 data-full-image="<?= $fullUrl ?>"
                 data-thumbnail="<?= $thumbUrl ?>">
                <img src="<?= $thumbUrl ?>" 
                     alt="Фото <?= $index + 1 ?>" 
                     class="gallery-thumb"
                     data-full-image="<?= $fullUrl ?>"
                     data-is-video="<?= $isVideo ? 'true' : 'false' ?>">
                <div class="gallery-overlay">
                    <?php if ($isVideo): ?>
                        <span class="glyphicon glyphicon-play" style="font-size: 40px; color: #fff; opacity: 0.8; text-shadow: 0 0 30px rgba(0,0,0,0.8);"></span>
                    <?php else: ?>
                        <span class="glyphicon glyphicon-search"></span>
                    <?php endif; ?>
                </div>
                <?php if ($isVideo): ?>
                    <div style="position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.75); color: white; font-size: 11px; padding: 2px 12px; border-radius: 12px; font-weight: 600; pointer-events: none; z-index: 5;">
                        <span class="glyphicon glyphicon-film"></span> Видео
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>