<?php
// FILE: .\views\layouts\miniapp.php

/** @var yii\web\View $this */
/** @var string $content */

use yii\bootstrap5\Html;
use app\assets\AppAsset;

AppAsset::register($this);

// Минимальный набор CSS для Mini App
$this->registerCssFile('@web/css/notification.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('@web/css/advertisement-form.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerCssFile('@web/css/bottom-nav.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

// JS для уведомлений
$this->registerJsFile('@web/js/notification.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);
$this->registerJsFile('@web/js/bottom-nav.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

// Color mode
$this->registerJsFile('@web/js/color-mode.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

// Bootstrap
$this->registerJsFile('@web/js/bootstrap.bundle.min.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

// ✅ Telegram Web App SDK - ОБЯЗАТЕЛЬНО в HEAD
$this->registerJsFile('https://telegram.org/js/telegram-web-app.js', [
    'position' => \yii\web\View::POS_HEAD
]);

// ✅ Инициализация Telegram Mini App
$jsInit = <<<JS
(function() {
    if (window.Telegram && window.Telegram.WebApp) {
        var tg = window.Telegram.WebApp;
        
        // Уведомляем Telegram, что приложение загружено
        tg.ready();
        
        // Разворачиваем на весь экран
        tg.expand();
        
        // Применяем тему Telegram
        var colorScheme = tg.colorScheme || 'dark';
        document.documentElement.setAttribute('data-bs-theme', colorScheme);
        
        // Отслеживаем изменение темы
        tg.onEvent('themeChanged', function() {
            var newTheme = tg.colorScheme || 'dark';
            document.documentElement.setAttribute('data-bs-theme', newTheme);
            var event = new CustomEvent('themeChanged', { detail: { theme: newTheme } });
            document.dispatchEvent(event);
        });
        
        // Получаем данные пользователя
        if (tg.initDataUnsafe && tg.initDataUnsafe.user) {
            var user = tg.initDataUnsafe.user;
            window.telegramUser = {
                id: user.id,
                username: user.username,
                firstName: user.first_name,
                lastName: user.last_name,
                languageCode: user.language_code,
                isPremium: user.is_premium || false,
                initData: tg.initData,
                initDataUnsafe: tg.initDataUnsafe
            };
            
            // Сохраняем в localStorage
            try {
                localStorage.setItem('telegram_user', JSON.stringify(window.telegramUser));
                localStorage.setItem('telegram_initData', tg.initData);
            } catch(e) {}
        }
        
        // Настройка кнопки "Назад"
        if (tg.BackButton) {
            tg.BackButton.show();
            tg.BackButton.onClick(function() {
                tg.close();
            });
        }
        
        // Логируем
        console.log('Telegram Mini App initialized', {
            platform: tg.platform,
            colorScheme: colorScheme,
            version: tg.version,
            user: window.telegramUser
        });
    } else {
        console.log('App running in browser mode');
        
        // Если запущено вне Telegram - показываем сообщение
        document.addEventListener('DOMContentLoaded', function() {
            var alert = document.createElement('div');
            alert.className = 'alert alert-warning';
            alert.style.cssText = 'position: fixed; top: 10px; left: 50%; transform: translateX(-50%); z-index: 9999; max-width: 90%; text-align: center;';
            alert.innerHTML = '<strong>📱 Откройте это приложение в Telegram</strong><br>Для лучшего опыта используйте Telegram Mini App';
            document.body.prepend(alert);
        });
    }
})();
JS;

$this->registerJs($jsInit, \yii\web\View::POS_END);
?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" class="h-100">
<head>
    <meta charset="<?= Yii::$app->charset ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, user-scalable=no">
    <?php $this->registerCsrfMetaTags() ?>
    <title><?= Html::encode($this->title) ?></title>
    <?php $this->head() ?>
</head>
<body class="d-flex flex-column h-100">
<?php $this->beginBody() ?>

<main role="main" class="flex-shrink-0" style="padding-bottom: 60px;">
    <div class="container" style="padding-top: 10px;">
        <?= $content ?>
    </div>
</main>

<!-- Нижнее меню -->
<?= $this->render('_header') ?>

<?php $this->endBody() ?>
<!-- Уведомления -->
<?= $this->render('_notifications') ?>
</body>
</html>
<?php $this->endPage() ?>