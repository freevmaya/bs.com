<?php

use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;

$priceText = $model->price ? number_format($model->price, 0, '.', ' ') . ' ₽' : 'Цена не указана';
if ($model->price_negotiable) {
    $priceText .= ' (договорная)';
}

// Получаем изображения (до 5 штук)
$images = $model->getImages()->limit(5)->all();
$imagesCount = count($images);
$link = Url::toRoute(['advertisements/view', 'id' => $model->id]);

?>

<div class="media" style="margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px;">
    <a class="media-body" href="<?=$link?>">
        <div class="media-left" style="padding-right: 15px;">
            <?php if ($imagesCount > 0): ?>
                <div class="image-preview-grid" style="display: grid; grid-template-columns: repeat(<?= min($imagesCount, 5) ?>, 60px); gap: 5px; width: <?= min($imagesCount, 5) * 65 ?>px;">
                    <?php 
                    $displayCount = 0;
                    foreach ($images as $index => $image): 
                        if ($displayCount >= 5) break;
                        $displayCount++;
                    ?>
                        <div class="image-thumb" style="width: 60px; height: 60px; overflow: hidden; border-radius: 4px; background: #f0f0f0;">
                            <img src="<?= $image->getThumbnailUrl() ?>" alt="Изображение <?= $index + 1 ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="advertisement-placeholder" style="width: 60px; height: 60px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; border-radius: 4px;">
                    <span class="glyphicon glyphicon-picture" style="font-size: 24px; color: #ccc;"></span>
                </div>
            <?php endif; ?>
        </div>
        <h4 class="media-heading" style="margin-top: 0;">
            <?=Html::encode($model->title)?>
            <small>
                <span class="label <?= $model->section === 'sell' ? 'label-danger' : 'label-info' ?>">
                    <?= $model->getSectionLabel() ?>
                </span>
            </small>
        </h4>
        <p style="margin-bottom: 8px;"><?= Html::encode(StringHelper::truncate($model->description, 120)) ?></p>
        <div class="advertisement-meta" style="font-size: 12px; color: #666;">
            <span class="glyphicon glyphicon-tag"></span> <strong><?= $priceText ?></strong>
            <?php if ($model->city): ?>
                | <span class="glyphicon glyphicon-map-marker"></span> <?= Html::encode($model->city) ?>
            <?php endif; ?>
            | <span class="glyphicon glyphicon-time"></span> <?= Yii::$app->formatter->asDate($model->created_at) ?>
            | <span class="glyphicon glyphicon-eye-open"></span> <?= $model->views_count ?>
            <?php if ($imagesCount > 0): ?>
                | <span class="glyphicon glyphicon-picture"></span> <?= $imagesCount ?>
            <?php endif; ?>
        </div>
    </a>
</div>