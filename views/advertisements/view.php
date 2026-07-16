<?php

use yii\helpers\Html;
use yii\helpers\Url;
use app\helpers\SvgHelper;

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
            <!-- Галерея изображений -->
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
            
            <!-- Основная информация -->
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
                        <?php if ($model->user): ?>
                            <span class="label label-default">
                                <span class="glyphicon glyphicon-user"></span> <?= Html::encode($model->user->username) ?>
                            </span>
                        <?php endif; ?>
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
            <!-- Контактная информация -->
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        <?= SvgHelper::render('email', ['width' => 18, 'height' => 18, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) ?>
                        Контакты
                    </h3>
                </div>
                <div class="panel-body">
                    <?php if ($model->city): ?>
                        <p>
                            <strong>
                                <?= SvgHelper::render('map-pin', ['width' => 14, 'height' => 14, 'class' => 'svg-icon', 'style' => 'margin-right: 4px;']) ?>
                                Город:
                            </strong>
                            <?= Html::encode($model->city) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($model->phone): ?>
                        <p>
                            <strong>
                                <?= SvgHelper::render('phone', ['width' => 14, 'height' => 14, 'class' => 'svg-icon', 'style' => 'margin-right: 4px;']) ?>
                                Телефон:
                            </strong>
                            <a href="tel:<?= preg_replace('/[^0-9+]/', '', $model->phone) ?>" class="phone-link">
                                <?= Html::encode($model->phone) ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($model->email): ?>
                        <p>
                            <strong>
                                <?= SvgHelper::render('email', ['width' => 14, 'height' => 14, 'class' => 'svg-icon', 'style' => 'margin-right: 4px;']) ?>
                                Email:
                            </strong>
                            <a href="mailto:<?= Html::encode($model->email) ?>" style="word-break: break-all;">
                                <?= Html::encode($model->email) ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <!-- Telegram - берем из объявления -->
                    <?php if ($model->telegram): ?>
                        <?php 
                        $telegramUsername = ltrim($model->telegram, '@');
                        $telegramLink = 'https://t.me/' . $telegramUsername;
                        ?>
                        <p>
                            <strong>
                                <?= SvgHelper::render('telegram', ['width' => 14, 'height' => 14, 'class' => 'svg-icon', 'style' => 'margin-right: 4px;']) ?>
                                Telegram:
                            </strong>
                            <a href="<?= $telegramLink ?>" target="_blank" rel="noopener noreferrer">
                                @<?= Html::encode($telegramUsername) ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <!-- VK - берем из объявления -->
                    <?php if ($model->vk_profile_url): ?>
                        <p>
                            <strong>
                                <?= SvgHelper::render('vk', ['width' => 14, 'height' => 14, 'class' => 'svg-icon', 'style' => 'margin-right: 4px;']) ?>
                                VK:
                            </strong>
                            <a href="<?= Html::encode($model->vk_profile_url) ?>" target="_blank" rel="noopener noreferrer">
                                <?php 
                                $vkName = $model->vk_profile_url;
                                if (preg_match('/vk\.com\/([^\?\/]+)/', $vkName, $matches)) {
                                    $vkName = $matches[1];
                                }
                                echo Html::encode($vkName);
                                ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <!-- WhatsApp - берем из объявления -->
                    <?php 
                    $whatsappConfigured = !empty(Yii::$app->params['whatsapp_api_key']) && 
                                          !empty(Yii::$app->params['whatsapp_api_url']) &&
                                          Yii::$app->params['whatsapp_api_key'] !== 'ВАШ_WHATSAAP_API_KEY';
                    if ($whatsappConfigured && $model->whatsapp): 
                    ?>
                        <p>
                            <strong>
                                <?= SvgHelper::render('whatsapp', ['width' => 14, 'height' => 14, 'class' => 'svg-icon', 'style' => 'margin-right: 4px;']) ?>
                                WhatsApp:
                            </strong>
                            <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $model->whatsapp) ?>" target="_blank" rel="noopener noreferrer">
                                <?= Html::encode($model->whatsapp) ?>
                            </a>
                        </p>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <!-- Кнопки действий -->
                    <?php if (!Yii::$app->user->isGuest): ?>
                        <?php if (Yii::$app->user->id != $model->user_id): ?>
                            <?php
                            $telegramDialogUrl = $model->telegram ? 'https://t.me/' . ltrim($model->telegram, '@') : null;
                            $vkDialogUrl = $model->vk_profile_url ? $model->vk_profile_url : null;
                            ?>
                            
                            <div class="btn-group-vertical" style="width: 100%;">
                                <!-- Написать автору (внутренний диалог) -->
                                <?= Html::a(
                                    SvgHelper::render('envelope', ['width' => 18, 'height' => 18, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) . ' Написать автору',
                                    ['/messages/start', 'advertisementId' => $model->id],
                                    ['class' => 'btn btn-primary btn-block']
                                ) ?>
                                
                                <!-- Telegram (если есть в объявлении) -->
                                <?php if ($telegramDialogUrl): ?>
                                    <?= Html::a(
                                        SvgHelper::render('telegram-lg', ['width' => 18, 'height' => 18, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) . ' Написать в Telegram',
                                        $telegramDialogUrl,
                                        [
                                            'class' => 'btn btn-info btn-block',
                                            'target' => '_blank',
                                            'rel' => 'noopener noreferrer',
                                        ]
                                    ) ?>
                                <?php endif; ?>
                                
                                <!-- VK (если есть в объявлении) -->
                                <?php if ($vkDialogUrl): ?>
                                    <?= Html::a(
                                        SvgHelper::render('vk-lg', ['width' => 18, 'height' => 18, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) . ' Написать в VK',
                                        $vkDialogUrl,
                                        [
                                            'class' => 'btn btn-primary btn-block',
                                            'target' => '_blank',
                                            'rel' => 'noopener noreferrer',
                                            'style' => 'background: #4a76a8; border-color: #4a76a8;'
                                        ]
                                    ) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-warning" style="margin-bottom: 0;">
                            <?= SvgHelper::render('alert', ['width' => 16, 'height' => 16, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) ?>
                            <?= Html::a('Войдите', ['/site/login']) ?> или 
                            <?= Html::a('зарегистрируйтесь', ['/site/register']) ?>, 
                            чтобы связаться с автором
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Информация об авторе -->
            <?php if ($model->user): ?>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <?= SvgHelper::render('user', ['width' => 16, 'height' => 16, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) ?>
                            Об авторе
                        </h4>
                    </div>
                    <div class="panel-body">
                        <p>
                            <strong><?= Html::encode($model->user->username) ?></strong>
                            <?php if ($model->user->city): ?>
                                <span class="text-muted">, <?= Html::encode($model->user->city) ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if ($model->user->created_at): ?>
                            <p class="text-muted small">
                                <?= SvgHelper::render('clock', ['width' => 12, 'height' => 12, 'class' => 'svg-icon', 'style' => 'margin-right: 4px;']) ?>
                                На сайте с <?= Yii::$app->formatter->asDate($model->user->created_at) ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php
                        $userAdsCount = \app\models\Advertisement::find()
                            ->where(['user_id' => $model->user_id, 'status' => \app\models\Advertisement::STATUS_ACTIVE])
                            ->count();
                        ?>
                        <p class="text-muted small">
                            <?= SvgHelper::render('list', ['width' => 12, 'height' => 12, 'class' => 'svg-icon', 'style' => 'margin-right: 4px;']) ?>
                            Активных объявлений: <?= $userAdsCount ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Действия для владельца -->
            <?php if (!Yii::$app->user->isGuest && Yii::$app->user->id == $model->user_id): ?>
                <div class="panel panel-default">
                    <div class="panel-body">
                        <div class="btn-group-vertical" style="width: 100%;">
                            <?= Html::a(
                                SvgHelper::render('edit', ['width' => 16, 'height' => 16, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) . ' Редактировать',
                                ['update', 'id' => $model->id],
                                ['class' => 'btn btn-primary btn-block']
                            ) ?>
                            <?= Html::a(
                                SvgHelper::render('trash', ['width' => 16, 'height' => 16, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) . ' Удалить',
                                ['delete', 'id' => $model->id],
                                [
                                    'class' => 'btn btn-danger btn-block',
                                    'data' => [
                                        'confirm' => 'Вы уверены, что хотите удалить это объявление?',
                                        'method' => 'post',
                                    ],
                                ]
                            ) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>