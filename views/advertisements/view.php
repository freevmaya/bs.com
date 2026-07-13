<?php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Объявления', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем CSS и JS для карусели
$this->registerCssFile('@web/css/advertisement-form.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/carousel.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);
?>

<div class="advertisements-view">
    <div class="row">
        <div class="col-md-8">
            <?php if ($model->section === 'sell' && $model->getImageCount() > 0): ?>
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="gallery-container">
                            <div class="gallery-grid" id="gallery-<?= $model->id ?>">
                                <?php foreach ($model->getImages()->all() as $index => $image): ?>
                                    <?php 
                                    $isVideo = $image->isVideo();
                                    $fullUrl = $image->getImageUrl();
                                    $thumbUrl = $image->getThumbnailUrl();
                                    ?>
                                    <div class="gallery-item <?= $isVideo ? 'video-item' : '' ?>">
                                        <img src="<?= $thumbUrl ?>" 
                                             alt="Фото <?= $index + 1 ?>" 
                                             class="gallery-thumb grid-preview-item"
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
                    </div>
                </div>
            <?php else: ?>
                <div class="panel panel-default">
                    <div class="panel-body text-center" style="padding: 60px 20px;">
                        <span class="glyphicon glyphicon-picture" style="font-size: 80px; color: #ccc;"></span>
                        <p class="text-muted" style="margin-top: 15px;">Нет изображений</p>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="panel panel-default">
                <div class="panel-body">
                    <h1><?= Html::encode($model->title) ?></h1>
                    
                    <div class="advertisement-meta-info" style="margin-bottom: 20px;">
                        <span class="label <?= $model->section === 'sell' ? 'label-danger' : 'label-info' ?>" style="font-size: 14px;">
                            <?= $model->getSectionLabel() ?>
                        </span>
                        <span class="label label-default">
                            <span class="glyphicon glyphicon-eye-open"></span> <?= $model->views_count ?> просмотров
                        </span>
                        <span class="label label-default">
                            <span class="glyphicon glyphicon-time"></span> <?= Yii::$app->formatter->asDate($model->created_at) ?>
                        </span>
                    </div>
                    
                    <div class="price-large" style="font-size: 28px; color: #d9534f; margin: 20px 0;">
                        <?php if ($model->price): ?>
                            <?= number_format($model->price, 0, '.', ' ') ?> ₽
                            <?php if ($model->price_negotiable): ?>
                                <small>(цена договорная)</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">Цена не указана</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="well">
                        <?= nl2br(Html::encode($model->description)) ?>
                    </div>

                    <!-- Дополнительные поля в зависимости от типа -->
                    <?php if ($model->type !== 'normal'): ?>
                        <div class="panel panel-default" style="margin-top: 20px;">
                            <div class="panel-body">
                                <?php if ($model->type === 'glider' && $model->glider): ?>
                                    <table class="table">
                                        <tr><th>Модель</th><td><?= Html::encode($model->glider->model) ?></td></tr>
                                        <tr><th>Производитель</th><td><?= Html::encode($model->glider->producer->fullName ?? '') ?></td></tr>
                                        <tr><th>Сертификация</th><td><?= Html::encode($model->glider->certification->name ?? '') ?></td></tr>
                                        <tr><th>Весовая вилка</th><td><?= $model->glider->weight_min ?> - <?= $model->glider->weight_max ?> кг</td></tr>
                                        <tr><th>Год выпуска</th><td><?= Html::encode($model->glider->date_release) ?></td></tr>
                                        <tr><th>Налёт</th><td><?= $model->glider->flight_time ?> ч.</td></tr>
                                        <tr><th>Состояние</th><td><?= Html::encode(\app\models\AdvertisementGlider::getConditionList()[$model->glider->condition] ?? '') ?></td></tr>
                                        <?php if ($model->glider->defects): ?>
                                            <tr><th>Дефекты</th><td><?= nl2br(Html::encode($model->glider->defects)) ?></td></tr>
                                        <?php endif; ?>
                                        <?php if ($model->glider->cause): ?>
                                            <tr><th>Причина продажи</th><td><?= nl2br(Html::encode($model->glider->cause)) ?></td></tr>
                                        <?php endif; ?>
                                    </table>
                                <?php elseif ($model->type === 'harness' && $model->harness): ?>
                                    <table class="table table-bordered">
                                        <tr><th>Модель</th><td><?= Html::encode($model->harness->model) ?></td></tr>
                                        <tr><th>Производитель</th><td><?= Html::encode($model->harness->producer->fullName ?? '') ?></td></tr>
                                        <tr><th>Размер</th><td><?= Html::encode($model->harness->size) ?></td></tr>
                                        <tr><th>Год выпуска</th><td><?= Html::encode($model->harness->date_release) ?></td></tr>
                                        <tr><th>Состояние</th><td><?= Html::encode(\app\models\AdvertisementHarness::getConditionList()[$model->harness->condition] ?? '') ?></td></tr>
                                        <?php if ($model->harness->defects): ?>
                                            <tr><th>Дефекты</th><td><?= nl2br(Html::encode($model->harness->defects)) ?></td></tr>
                                        <?php endif; ?>
                                    </table>
                                <?php elseif ($model->type === 'device' && $model->device): ?>
                                    <table class="table table-bordered">
                                        <tr><th>Модель</th><td><?= Html::encode($model->device->model) ?></td></tr>
                                        <tr><th>Производитель</th><td><?= Html::encode($model->device->producer->fullName ?? '') ?></td></tr>
                                        <tr><th>Состояние</th><td><?= Html::encode(\app\models\AdvertisementDevice::getConditionList()[$model->device->condition] ?? '') ?></td></tr>
                                        <?php if ($model->device->defects): ?>
                                            <tr><th>Дефекты</th><td><?= nl2br(Html::encode($model->device->defects)) ?></td></tr>
                                        <?php endif; ?>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">

            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Контактная информация</h3>
                </div>
                <div class="panel-body">
                    <?php if ($model->city): ?>
                        <p><strong>Город:</strong> <?= Html::encode($model->city) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($model->phone): ?>
                        <p><strong>Телефон:</strong> <?= Html::encode($model->phone) ?></p>
                    <?php endif; ?>
                    
                    <?php if ($model->email): ?>
                        <p><strong>Email:</strong> <?= Html::encode($model->email) ?></p>
                    <?php endif; ?>
                    
                    <!-- Кнопка "Написать автору" -->
                    <?php if (!Yii::$app->user->isGuest && Yii::$app->user->id != $model->user_id): ?>
                        <hr>
                        <?= Html::a(
                            '<span class="glyphicon glyphicon-envelope"></span> Написать автору',
                            ['/messages/start', 'advertisementId' => $model->id],
                            ['class' => 'btn btn-primary btn-block']
                        ) ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if (!Yii::$app->user->isGuest && Yii::$app->user->id == $model->user_id): ?>
                <div class="panel panel-default">
                    <div class="panel-body">
                        <?= Html::a('Редактировать', ['update', 'id' => $model->id], ['class' => 'btn btn-primary btn-block']) ?>
                        <?= Html::a('Удалить', ['delete', 'id' => $model->id], [
                            'class' => 'btn btn-danger btn-block',
                            'data' => [
                                'confirm' => 'Вы уверены, что хотите удалить это объявление?',
                                'method' => 'post',
                            ],
                        ]) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>