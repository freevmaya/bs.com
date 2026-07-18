<?php
// FILE: .\views\advertisements\view.php

use yii\helpers\Html;
use yii\helpers\Url;
use app\helpers\SvgHelper;

// ✅ Формируем заголовок для OG на основе краткой информации
$shortInfo = $model->getShortInfoString(' • ');
$ogTitle = !empty($shortInfo) ? $shortInfo : $model->title;

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Объявления', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// ============================================================
// МЕТА-ТЕГИ ДЛЯ СОЦИАЛЬНЫХ СЕТЕЙ (Open Graph + Twitter Cards)
// ============================================================

$baseUrl = Yii::$app->urlManager->createAbsoluteUrl('/');

$images = $model->getImages()->orderBy(['sort_order' => SORT_ASC])->limit(1)->all();
$mainImage = !empty($images) ? $images[0] : null;

$imageUrl = null;
if ($mainImage) {
    $imageUrl = $mainImage->getImageUrl();
    if (strpos($imageUrl, '/') !== 0) {
        $imageUrl = '/' . $imageUrl;
    }
}
$imageFullUrl = $imageUrl ? $baseUrl . ltrim($imageUrl, '/') : null;

if (!$imageFullUrl) {
    $imageFullUrl = $baseUrl . 'images/og-default.jpg';
}

$adUrl = Yii::$app->urlManager->createAbsoluteUrl(['advertisements/view', 'id' => $model->id]);

$shortInfo = $model->getShortInfoString(' • ');
$description = strip_tags($model->description);
$description = mb_substr($description, 0, 200) . (mb_strlen($description) > 200 ? '...' : '');

if (empty($description)) {
    if (!empty($shortInfo)) {
        $description = $shortInfo;
    } else {
        $description = $model->title;
    }
} elseif (!empty($shortInfo)) {
    $description = $shortInfo . ' | ' . $description;
}

$typeLabel = $model->getTypeLabel();
$sectionLabel = $model->getSectionLabel();
$price = $model->price ? number_format($model->price, 0, '.', ' ') . ' ₽' : 'Цена не указана';

// ============================================================
// РЕГИСТРИРУЕМ МЕТА-ТЕГИ
// ============================================================

// ✅ Используем getShortInfoString() для og:title
$this->registerMetaTag(['property' => 'og:title', 'content' => $ogTitle]);
$this->registerMetaTag(['property' => 'og:description', 'content' => $description]);
$this->registerMetaTag(['property' => 'og:image', 'content' => $imageFullUrl]);
$this->registerMetaTag(['property' => 'og:image:width', 'content' => '1200']);
$this->registerMetaTag(['property' => 'og:image:height', 'content' => '630']);
$this->registerMetaTag(['property' => 'og:url', 'content' => $adUrl]);
$this->registerMetaTag(['property' => 'og:type', 'content' => 'product']);
$this->registerMetaTag(['property' => 'og:site_name', 'content' => Yii::$app->name]);
$this->registerMetaTag(['property' => 'og:locale', 'content' => 'ru_RU']);
$this->registerMetaTag(['property' => 'product:price:amount', 'content' => $model->price ? (string)$model->price : '0']);
$this->registerMetaTag(['property' => 'product:price:currency', 'content' => 'RUB']);
$this->registerMetaTag(['property' => 'product:availability', 'content' => $model->status === 'active' ? 'in stock' : 'out of stock']);
$this->registerMetaTag(['property' => 'product:category', 'content' => $typeLabel]);

// Twitter Cards
// ✅ Используем getShortInfoString() для twitter:title
$this->registerMetaTag(['name' => 'twitter:card', 'content' => 'summary_large_image']);
$this->registerMetaTag(['name' => 'twitter:title', 'content' => $ogTitle]);
$this->registerMetaTag(['name' => 'twitter:description', 'content' => $description]);
$this->registerMetaTag(['name' => 'twitter:image', 'content' => $imageFullUrl]);

$this->registerMetaTag(['name' => 'description', 'content' => $description]);
$this->registerLinkTag(['rel' => 'canonical', 'href' => $adUrl]);

// ============================================================
// СТРУКТУРИРОВАННЫЕ ДАННЫЕ (JSON-LD)
// ============================================================

$jsonLd = [
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $ogTitle, // ✅ Используем краткую информацию
    'description' => $description,
    'image' => $imageFullUrl,
    'url' => $adUrl,
    'offers' => [
        '@type' => 'Offer',
        'price' => $model->price ? (string)$model->price : '0',
        'priceCurrency' => 'RUB',
        'availability' => $model->status === 'active' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'url' => $adUrl,
    ],
    'category' => $typeLabel,
];

if ($model->user) {
    $jsonLd['brand'] = [
        '@type' => 'Brand',
        'name' => $model->user->username,
    ];
}

if ($model->city) {
    $jsonLd['offers']['seller'] = [
        '@type' => 'Person',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $model->city,
        ],
    ];
}

$jsonLdString = json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$this->registerJs(
    'var script = document.createElement("script");
    script.type = "application/ld+json";
    script.text = ' . json_encode($jsonLdString) . ';
    document.head.appendChild(script);',
    \yii\web\View::POS_HEAD
);

// ============================================================
// РЕГИСТРИРУЕМ CSS И JS
// ============================================================

$this->registerCssFile('@web/css/advertisement-form.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/carousel.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

$isAdmin = !Yii::$app->user->isGuest && Yii::$app->user->identity->isAdmin();

// Дублируем мета-теги для отладки
$this->registerMetaTag(['property' => 'og:title', 'content' => $ogTitle], null, 'og_title');
$this->registerMetaTag(['property' => 'og:description', 'content' => $description], null, 'og_description');
$this->registerMetaTag(['property' => 'og:image', 'content' => $imageFullUrl], null, 'og_image');
$this->registerMetaTag(['property' => 'og:image:width', 'content' => '1200'], null, 'og_image_width');
$this->registerMetaTag(['property' => 'og:image:height', 'content' => '630'], null, 'og_image_height');
$this->registerMetaTag(['property' => 'og:url', 'content' => $adUrl], null, 'og_url');
$this->registerMetaTag(['property' => 'og:type', 'content' => 'product'], null, 'og_type');
$this->registerMetaTag(['property' => 'og:site_name', 'content' => Yii::$app->name], null, 'og_site_name');
$this->registerMetaTag(['property' => 'og:locale', 'content' => 'ru_RU'], null, 'og_locale');

if (YII_DEBUG) {
    echo "<!-- OG TITLE: {$ogTitle} -->\n";
    echo "<!-- IMAGE URL: {$imageFullUrl} -->\n";
    echo "<!-- AD URL: {$adUrl} -->\n";
    echo "<!-- DESCRIPTION: {$description} -->\n";
}
?>

<!-- ============================================================ -->
<!-- ОСНОВНОЙ HTML КОД СТРАНИЦЫ -->
<!-- ============================================================ -->

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

                    <!-- Дополнительные поля в зависимости от типа -->
                    <?php if ($model->type !== 'normal' && $model->getTypeObject()): ?>
                        <div class="panel panel-default" style="margin-top: 20px;">
                            <div class="panel-body">
                                <?php if ($model->type === 'glider' && $model->glider): ?>
                                    <table class="table">
                                        <tr><th>Тип</th><td><?= $model->getTypeLabel() ?></td></tr>
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
                                        <tr><th>Тип</th><td><?= $model->getTypeLabel() ?></td></tr>
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
                                        <tr><th>Тип</th><td><?= $model->getTypeLabel() ?></td></tr>
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
                    
                    <div class="well">
                        <?= nl2br(Html::encode($model->description)) ?>
                    </div>
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
                    
                    <?php if (!empty($model->source_url)): ?>
                        <p>
                            <strong>
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 4px;">
                                    <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                    <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                                </svg>
                                Источник:
                            </strong>
                            <a href="<?= Html::encode($model->source_url) ?>" target="_blank" rel="noopener noreferrer" style="word-break: break-all;">
                                <?= Html::encode($model->source_url) ?>
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
                                <?= Html::a(
                                    SvgHelper::render('envelope', ['width' => 18, 'height' => 18, 'class' => 'svg-icon', 'style' => 'margin-right: 6px;']) . ' Написать автору',
                                    ['/messages/start', 'advertisementId' => $model->id],
                                    ['class' => 'btn btn-primary btn-block']
                                ) ?>
                                
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
                            <button type="button" class="btn btn-success btn-block bump-button" data-id="<?= $model->id ?>">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 6px;">
                                    <polyline points="18 15 12 9 6 15"/>
                                </svg>
                                Поднять
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// Регистрируем JS для кнопки "Поднять"
$this->registerJs("
    $(document).on('click', '.bump-button', function() {
        var button = $(this);
        var id = button.data('id');
        var originalText = button.html();
        
        button.prop('disabled', true).html('<span class=\"spinner-border spinner-border-sm\" role=\"status\"></span> Поднятие...');
        
        $.ajax({
            url: '" . \yii\helpers\Url::to(['advertisements/bump']) . "',
            type: 'POST',
            data: {
                id: id,
                _csrf: '" . Yii::$app->request->csrfToken . "'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    if (typeof window.showNotification === 'function') {
                        window.showNotification(response.message, 'success');
                    } else {
                        alert(response.message);
                    }
                    if (response.updated_at) {
                        $('.advertisement-meta-info .label:contains(просмотров)').before(
                            '<span class=\"label label-default\"><span class=\"glyphicon glyphicon-time\"></span> ' + response.updated_at + '</span> '
                        );
                    }
                } else {
                    if (typeof window.showNotification === 'function') {
                        window.showNotification(response.error || 'Ошибка при поднятии', 'danger');
                    } else {
                        alert(response.error || 'Ошибка при поднятии');
                    }
                }
            },
            error: function() {
                if (typeof window.showNotification === 'function') {
                    window.showNotification('Ошибка соединения с сервером', 'danger');
                } else {
                    alert('Ошибка соединения с сервером');
                }
            },
            complete: function() {
                button.prop('disabled', false).html(originalText);
            }
        });
    });
", \yii\web\View::POS_READY);