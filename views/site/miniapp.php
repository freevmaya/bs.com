<?php
// FILE: .\views\site\miniapp.php

use yii\helpers\Html;
use yii\helpers\Url;

$this->title = 'Главная - Telegram Mini App';
?>

<div class="miniapp-index">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <h4>📱 Добро пожаловать в Telegram Mini App!</h4>
                <p>Это ваше приложение, интегрированное в Telegram.</p>
                <?php if (isset($isTelegram) && $isTelegram): ?>
                    <span class="label label-success">✅ Запущено в Telegram</span>
                <?php else: ?>
                    <span class="label label-warning">⚠️ Запущено вне Telegram</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-4 col-6 mb-3">
            <a href="<?= Url::to(['advertisements/sell']) ?>" class="btn btn-primary btn-block" style="padding: 20px;">
                <span style="font-size: 32px; display: block;">🛒</span>
                Продам
            </a>
        </div>
        <div class="col-md-4 col-6 mb-3">
            <a href="<?= Url::to(['advertisements/buy']) ?>" class="btn btn-success btn-block" style="padding: 20px;">
                <span style="font-size: 32px; display: block;">🔍</span>
                Куплю
            </a>
        </div>
        <div class="col-md-4 col-6 mb-3">
            <a href="<?= Url::to(['advertisements/my']) ?>" class="btn btn-info btn-block" style="padding: 20px;">
                <span style="font-size: 32px; display: block;">📋</span>
                Мои объявления
            </a>
        </div>
        <?php if (Yii::$app->user->isGuest): ?>
        <div class="col-md-4 col-6 mb-3">
            <a href="<?= Url::to(['site/login']) ?>" class="btn btn-warning btn-block" style="padding: 20px;">
                <span style="font-size: 32px; display: block;">🔑</span>
                Вход
            </a>
        </div>
        <?php else: ?>
        <div class="col-md-4 col-6 mb-3">
            <a href="<?= Url::to(['user/profile']) ?>" class="btn btn-secondary btn-block" style="padding: 20px;">
                <span style="font-size: 32px; display: block;">👤</span>
                Профиль
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>