<?php

/** @var yii\web\View $this */
/** @var string $content */

use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use app\assets\AppAsset;

AppAsset::register($this);

// Регистрируем CSS для уведомлений и форм
$this->registerCssFile('@web/css/notification.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('@web/css/advertisement-form.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('@web/css/bottom-nav.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

// Регистрируем JS для уведомлений и нижнего меню
$this->registerJsFile('@web/js/notification.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);
$this->registerJsFile('@web/js/bottom-nav.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

// Убеждаемся, что Bootstrap JS загружен (для collapse)
$this->registerJsFile('@web/js/bootstrap.bundle.min.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<main role="main" class="flex-shrink-0" style="padding-bottom: 60px;">
    <div class="container">
        <?= $content ?>
    </div>
</main>

<!-- Нижнее меню -->
<nav class="bottom-nav">
    <ul class="navbar-nav">
        <li class="nav-item">
            <?= Html::a(
                '<span class="nav-icon">📤</span><span class="nav-label">Продам</span>',
                ['/advertisements/sell'],
                ['class' => 'nav-link']
            ) ?>
        </li>
        <li class="nav-item">
            <?= Html::a(
                '<span class="nav-icon">🛒</span><span class="nav-label">Куплю</span>',
                ['/advertisements/buy'],
                ['class' => 'nav-link']
            ) ?>
        </li>
        <?php if (Yii::$app->user->isGuest): ?>
            <li class="nav-item">
                <?= Html::a(
                    '<span class="nav-icon">🔑</span><span class="nav-label">Вход</span>',
                    ['/site/login'],
                    ['class' => 'nav-link']
                ) ?>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <?= Html::a(
                    '<span class="nav-icon">👤</span><span class="nav-label">' . Html::encode(Yii::$app->user->identity->username) . '</span>',
                    ['/user/profile'],
                    ['class' => 'nav-link']
                ) ?>
            </li>
        <?php endif; ?>
    </ul>
</nav>

<footer class="footer mt-auto py-3 text-muted" style="display: none;">
    <div class="container">
        <p class="float-start">&copy; КупиПродайка <?= date('Y') ?></p>
        <p class="float-end"><?= Yii::powered() ?></p>
    </div>
</footer>

<?php $this->endBody() ?>
<!-- Подключаем _notifications.php перед endBody() -->
<?= $this->render('_notifications') ?>
</body>
</html>
<?php $this->endPage() ?>