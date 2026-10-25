<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;

// Показываем пункт «Избранное» только если есть выбранные объявления
$favoriteStorageForHeader = new \app\components\FavoriteStorage();
$favoritesCount = $favoriteStorageForHeader->count();
$favoritesNavStyle = $favoritesCount > 0 ? '' : ' style="display: none;"';
?>

<nav class="bottom-nav">
    <ul class="navbar-nav">
        <li class="nav-item">
            <?= Html::a(
                '<span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg></span><span class="nav-label">Продам</span>',
                ['/advertisements/sell'],
                ['class' => 'nav-link']
            ) ?>
        </li>
        <li class="nav-item">
            <?= Html::a(
                '<span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg></span><span class="nav-label">Куплю</span>',
                ['/advertisements/buy'],
                ['class' => 'nav-link']
            ) ?>
        </li>

        <li class="nav-item">
            <?= Html::button(
                '<span class="nav-icon" style="font-size: 20px;">
                </span><span class="nav-label" id="theme-label">Тема</span>',
                [
                    'id' => 'theme-toggle',
                    'class' => 'btn btn-link nav-link',
                    'aria-label' => 'Переключить тему',
                ],
            ) ?>
        </li>

        <?php if (!Yii::$app->user->isGuest): ?>
            <li class="nav-item">
                <?= Html::a(
                    '<span class="nav-icon" style="position: relative;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span id="messages-unread-badge" style="display: none; position: absolute; top: -6px; right: -8px; background: #dc3545; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center;"></span>
                    </span>
                    <span class="nav-label">Сообщения</span>',
                    ['/messages/index'],
                    ['class' => 'nav-link']
                ) ?>
            </li>
        <?php endif; ?>

        <li class="nav-item" id="favorites-nav-item"<?= $favoritesNavStyle ?>>
            <?= Html::a(
                '<span class="nav-icon" style="position: relative;">' .
                    '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>' .
                    '<span id="favorites-count-badge" style="display: none; position: absolute; right: -8px; background: #e74c3c; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold; min-width: 18px; text-align: center;"></span>' .
                '</span>' .
                '<span class="nav-label">Избранное</span>',
                ['/favorites/index'],
                ['class' => 'nav-link']
            ) ?>
        </li>

        
        <?php if (Yii::$app->user->isGuest): ?>
            <li class="nav-item">
                <?= Html::a(
                    '<span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg></span><span class="nav-label">Вход</span>',
                    ['/site/login'],
                    ['class' => 'nav-link']
                ) ?>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <?= Html::a(
                    '<span class="nav-icon"><svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span><span class="nav-label">' . Html::encode(Yii::$app->user->identity->username) . '</span>',
                    ['/user/profile'],
                    ['class' => 'nav-link']
                ) ?>
            </li>
        <?php endif; ?>
    </ul>
</nav>