<?php

use yii\helpers\Html;
use yii\helpers\StringHelper;
use yii\helpers\Url;
use app\widgets\ImageGridPreview;

$priceText = $model->price ? number_format($model->price, 0, '.', ' ') . ' ₽' : 'Цена не указана';
if ($model->price_negotiable) {
    $priceText .= ' (договорная)';
}

// ✅ Используем уже загруженные изображения
$images = $model->images;
// Ограничиваем до 5 для отображения
if (count($images) > 5) {
    $images = array_slice($images, 0, 5);
}
$imagesCount = count($images);

$link = Url::toRoute(['advertisements/view', 'id' => $model->id]);

// Собираем краткую информацию в зависимости от типа
$shortInfo = [];
if ($model->type === 'glider' && $model->glider) {
    if (!empty($model->glider->model)) {
        $shortInfo[] = 'Модель: ' . Html::encode($model->glider->model);
    }
    if (!empty($model->glider->producer)) {
        $shortInfo[] = 'Производитель: ' . Html::encode($model->glider->producer->short ?? $model->glider->producer->name);
    }
    if (!empty($model->glider->certification)) {
        $shortInfo[] = 'Сертификация: ' . Html::encode($model->glider->certification->name);
    }
    if (!empty($model->glider->weight_min) || !empty($model->glider->weight_max)) {
        $shortInfo[] = 'Вес: ' . ($model->glider->weight_min ?? '?') . ' - ' . ($model->glider->weight_max ?? '?') . ' кг';
    }
    if (!empty($model->glider->date_release)) {
        $shortInfo[] = 'Год выпуска: ' . Html::encode($model->glider->date_release);
    }
    if (!empty($model->glider->flight_time)) {
        $shortInfo[] = 'Налёт: ' . $model->glider->flight_time . ' ч.';
    }
} elseif ($model->type === 'harness' && $model->harness) {
    if (!empty($model->harness->model)) {
        $shortInfo[] = 'Модель: ' . Html::encode($model->harness->model);
    }
    if (!empty($model->harness->producer)) {
        $shortInfo[] = 'Производитель: ' . Html::encode($model->harness->producer->short ?? $model->harness->producer->name);
    }
    if (!empty($model->harness->size)) {
        $shortInfo[] = 'Размер: ' . Html::encode($model->harness->size);
    }
    if (!empty($model->harness->date_release)) {
        $shortInfo[] = 'Год выпуска: ' . Html::encode($model->harness->date_release);
    }
} elseif ($model->type === 'device' && $model->device) {
    if (!empty($model->device->model)) {
        $shortInfo[] = 'Модель: ' . Html::encode($model->device->model);
    }
    if (!empty($model->device->producer)) {
        $shortInfo[] = 'Производитель: ' . Html::encode($model->device->producer->short ?? $model->device->producer->name);
    }
}
$shortInfoString = implode(' | ', $shortInfo);
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
                <small>
                    <span class="label <?= $model->section === 'sell' ? 'label-danger' : 'label-info' ?>">
                        <?= $model->getSectionLabel() ?>
                    </span>
                    <?php if ($model->type !== 'normal'): ?>
                        <span class="label label-default">
                            <?= $model->getTypeLabel() ?>
                        </span>
                    <?php endif; ?>
                </small>
            </h4>
            
            <p style="margin-bottom: 8px;"><?= Html::encode(StringHelper::truncate($model->description, 120)) ?></p>
            
            <?php if (!empty($shortInfoString)): ?>
                <div class="preview">
                    <?= $shortInfoString ?>
                </div>
            <?php endif; ?>
            
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
</div>