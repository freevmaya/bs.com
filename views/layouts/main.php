<?php

/** @var yii\web\View $this */
/** @var string $content */

use yii\bootstrap5\Html;
use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use app\assets\AppAsset;

AppAsset::register($this);
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

<header>
    <?php
    NavBar::begin([
        'brandLabel' => 'КупиПродайка',
        'brandUrl' => Yii::$app->homeUrl,
        'options' => [
            'class' => 'navbar navbar-expand-md navbar-dark bg-dark fixed-top',
        ],
    ]);
    
    $menuItems = [
        ['label' => 'Главная', 'url' => ['/advertisements/index']],
        ['label' => 'Продам', 'url' => ['/advertisements/sell']],
        ['label' => 'Куплю', 'url' => ['/advertisements/buy']],
    ];
    
    if (Yii::$app->user->isGuest) {
        $menuItems[] = ['label' => 'Регистрация', 'url' => ['/site/register']];
        $menuItems[] = ['label' => 'Вход', 'url' => ['/site/login']];
    } else {
        $menuItems[] = ['label' => 'Мои объявления', 'url' => ['/advertisements/my']];
        $menuItems[] = ['label' => 'Добавить', 'url' => ['/advertisements/create']];
        $menuItems[] = '<li class="nav-item">'
            . Html::beginForm(['/site/logout'])
            . Html::submitButton(
                'Выход (' . Yii::$app->user->identity->username . ')',
                ['class' => 'nav-link btn btn-link logout']
            )
            . Html::endForm()
            . '</li>';
    }
    
    echo Nav::widget([
        'options' => ['class' => 'navbar-nav ms-auto mb-2 mb-md-0'],
        'items' => $menuItems,
    ]);
    
    NavBar::end();
    ?>
</header>

<main role="main" class="flex-shrink-0">
    <div class="container">
        <?= $content ?>
    </div>
</main>

<footer class="footer mt-auto py-3 text-muted">
    <div class="container">
        <p class="float-start">&copy; КупиПродайка <?= date('Y') ?></p>
        <p class="float-end"><?= Yii::powered() ?></p>
    </div>
</footer>

<?php $this->endBody() ?>
<?= $this->render('_notifications') ?>
</body>
</html>
<?php $this->endPage() ?>