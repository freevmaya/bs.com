<?php
/**
 * @var yii\web\View $this
 */
?>

<?php
// Регистрируем CSS и JS для уведомлений
$this->registerCssFile('@web/css/notification.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);

$flashes = Yii::$app->session->getAllFlashes();
?>

<div id="notification-container">
    <?php foreach ($flashes as $key => $message): ?>
        <div class="notification notification-<?= $key ?> show" style="margin-bottom: 10px;">
            <div class="notification-content">
                <div class="notification-message"><?= $message ?></div>
                <button class="notification-close">&times;</button>
            </div>
            <div class="notification-progress"></div>
        </div>
    <?php endforeach; ?>
</div>