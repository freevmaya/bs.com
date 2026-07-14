<?php
// test-yii-mailer.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

// Загружаем конфигурацию
$config = require __DIR__ . '/config/console.php';
$params = require __DIR__ . '/config/params.php';

echo "=== TEST YII MAILER ===\n";
echo "Загружена конфигурация из config/console.php\n\n";

// Получаем параметры SMTP из params
$smtpUsername = $params['smtp_username'] ?? 'freevmaya@yandex.ru';
$smtpPassword = $params['smtp_password'] ?? '';
$smtpHost = $params['smtp_host'] ?? 'smtp.yandex.ru';
$smtpPort = $params['smtp_port'] ?? 587;
$smtpEncryption = $params['smtp_encryption'] ?? 'tls';
$senderEmail = $params['senderEmail'] ?? 'freevmaya@yandex.ru';
$senderName = $params['senderName'] ?? 'parasell.vmaya.ru';

echo "Параметры SMTP:\n";
echo "  Username: {$smtpUsername}\n";
echo "  Password: " . str_repeat('*', strlen($smtpPassword)) . "\n";
echo "  Host: {$smtpHost}\n";
echo "  Port: {$smtpPort}\n";
echo "  Encryption: {$smtpEncryption}\n";
echo "  Sender: {$senderEmail} ({$senderName})\n\n";

// Проверяем наличие пароля
if (empty($smtpPassword) || $smtpPassword === 'ЗАМЕНИТЕ_НА_ПАРОЛЬ_ПРИЛОЖЕНИЯ') {
    echo "❌ Пароль не установлен в config/params.php\n";
    echo "   Добавьте 'smtp_password' => 'ПАРОЛЬ_ПРИЛОЖЕНИЯ' в config/params.php\n";
    exit(1);
}

// Создаем DSN строку из параметров
$encodedPassword = urlencode($smtpPassword);
$encodedUsername = urlencode($smtpUsername);
$dsn = "smtp://{$encodedUsername}:{$encodedPassword}@{$smtpHost}:{$smtpPort}?encryption={$smtpEncryption}";

// Скрываем пароль для вывода
$hiddenDsn = preg_replace('/:([^:@]+)@/', ':****@', $dsn);
echo "DSN: {$hiddenDsn}\n\n";

// Обновляем конфигурацию mailer с правильным DSN
$config['components']['mailer'] = [
    'class' => 'yii\symfonymailer\Mailer',
    'useFileTransport' => false,
    'transport' => [
        'dsn' => $dsn,
    ],
    'messageConfig' => [
        'charset' => 'UTF-8',
        'from' => [$senderEmail => $senderName],
    ],
];

// Создаем приложение
$app = new yii\console\Application($config);

try {
    $mailer = Yii::$app->mailer;
    
    echo "=== MAILER INFO ===\n";
    echo "Class: " . get_class($mailer) . "\n";
    echo "UseFileTransport: " . ($mailer->useFileTransport ? 'true' : 'false') . "\n";
    
    if (method_exists($mailer, 'getTransport')) {
        $transport = $mailer->getTransport();
        echo "Transport: " . get_class($transport) . "\n";
    }
    
    echo "\n=== ОТПРАВКА ПИСЬМА ===\n";
    
    $message = $mailer->compose()
        ->setFrom([$senderEmail => $senderName])
        ->setTo('fwadim@mail.ru')
        ->setSubject('Yii Mailer Test - ' . date('Y-m-d H:i:s'))
        ->setTextBody('This is a test email from Yii mailer.' . "\n")
        ->setTextBody('Sent at ' . date('Y-m-d H:i:s'));
    
    echo "Сообщение создано. Отправка...\n";
    
    $result = $message->send();
    
    if ($result) {
        echo "✅ Email отправлен успешно!\n";
    } else {
        echo "❌ Ошибка отправки email\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\n" . $e->getTraceAsString() . "\n";
}