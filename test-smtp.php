<?php
// test-smtp.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';
$app = new yii\console\Application($config);

use yii\symfonymailer\Mailer;

$params = Yii::$app->params;
$username = $params['smtp_username'] ?? 'freevmaya@yandex.ru';
$password = $params['smtp_password'] ?? '';
$senderName = $params['senderName'] ?? 'parasell.vmaya.ru';

echo "=== SMTP TEST ===\n";
echo "Username: {$username}\n";
echo "Password: " . str_repeat('*', strlen($password)) . "\n";
echo "Password length: " . strlen($password) . "\n";
echo "Sender name: {$senderName}\n\n";

if (empty($password) || $password === 'ЗАМЕНИТЕ_НА_ПАРОЛЬ_ПРИЛОЖЕНИЯ') {
    echo "❌ Пароль не установлен! Используйте пароль приложения.\n";
    echo "1. Перейдите на https://id.yandex.ru/security\n";
    echo "2. Создайте пароль приложения для 'Почта'\n";
    echo "3. Скопируйте пароль в config/params.php\n";
    exit(1);
}

try {
    $mailer = Yii::$app->mailer;
    
    // Правильное создание сообщения с указанием имени отправителя
    $message = $mailer->compose()
        ->setFrom([$username => $senderName])  // Оба параметра должны быть заполнены
        ->setTo('fwadim@mail.ru')
        ->setSubject('SMTP Test - ' . date('Y-m-d H:i:s'))
        ->setTextBody('This is a test email from SMTP. Sent at ' . date('Y-m-d H:i:s));
    
    $result = $message->send();
    
    if ($result) {
        echo "✅ Email sent successfully!\n";
    } else {
        echo "❌ Email sending failed.\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), '535') !== false) {
        echo "\n🔍 Ошибка 535: Неверный пароль.\n";
        echo "Проверьте:\n";
        echo "1. Используете ли вы пароль приложения (не основной пароль)\n";
        echo "2. Правильно ли скопирован пароль (без пробелов)\n";
        echo "3. Не истек ли срок действия пароля приложения\n";
    }
}