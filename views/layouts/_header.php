<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;
?>

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

            <li class="nav-item">
                <?= Html::button(
                    '🌓',  // Или можно использовать 🌙/☀️
                    [
                        'id' => 'theme-toggle',
                        'class' => 'btn btn-link nav-link fs-5',
                        'aria-label' => 'Переключить тему',
                        'style' => 'color: #fff; padding: 0 15px; font-size: 24px;',
                    ],
                ) ?>
            </li>
    </ul>
</nav>