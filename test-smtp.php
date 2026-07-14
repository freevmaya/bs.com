<?php
// test-smtp.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';

// Исправляем конфигурацию mailer перед запуском
$config['components']['mailer'] = [
    'class' => 'yii\symfonymailer\Mailer',
    'useFileTransport' => false,
    'transport' => [
        'dsn' => 'smtp://freevmaya@yandex.ru:bcqvhueipdxzebeb@smtp.yandex.ru:587?encryption=tls',
    ],
    'messageConfig' => [
        'charset' => 'UTF-8',
        'from' => ['freevmaya@yandex.ru' => 'parasell.vmaya.ru'],
    ],
];

$app = new yii\console\Application($config);

$username = 'freevmaya@yandex.ru';
$senderName = 'parasell.vmaya.ru';

echo "=== SMTP TEST ===\n";
echo "Username: {$username}\n";
echo "Sender name: {$senderName}\n\n";

try {
    $mailer = Yii::$app->mailer;
    
    echo "Mailer class: " . get_class($mailer) . "\n";
    
    if (method_exists($mailer, 'getTransport')) {
        $transport = $mailer->getTransport();
        echo "Transport: " . get_class($transport) . "\n";
    }
    
    echo "\nCreating message...\n";
    
    $message = $mailer->compose()
        ->setFrom(['freevmaya@yandex.ru' => 'parasell.vmaya.ru'])
        ->setTo('fwadim@mail.ru')
        ->setSubject('Yii Mailer Test - ' . date('Y-m-d H:i:s'))
        ->setTextBody('This is a test email from Yii mailer. Sent at ' . date('Y-m-d H:i:s'));
    
    echo "Message created. Sending...\n";
    
    $result = $message->send();
    
    if ($result) {
        echo "✅ Email sent successfully!\n";
    } else {
        echo "❌ Email sending failed.\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}