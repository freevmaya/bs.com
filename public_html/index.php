<?php

declare(strict_types=1);

/*

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Принудительно устанавливаем лимиты
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '600');
ini_set('max_input_time', '600');

// Логируем загрузку
error_log("=== UPLOAD ATTEMPT ===");
error_log("POST size: " . $_SERVER['CONTENT_LENGTH'] ?? 'unknown');

// Проверяем размер POST
if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 100 * 1024 * 1024) {
    error_log("Large upload detected: " . $_SERVER['CONTENT_LENGTH'] . " bytes");
}
*/

// comment out the following two lines when deployed to production
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
