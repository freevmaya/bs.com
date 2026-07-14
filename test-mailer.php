<?php
// test-mailer.php

$username = 'freevmaya@yandex.ru';
$password = 'bcqvhueipdxzebeb'; // Замените на реальный пароль
$senderName = 'parasell.vmaya.ru';

echo "=== DIRECT SMTP TEST ===\n";
echo "Username: {$username}\n";
echo "Password: " . str_repeat('*', strlen($password)) . "\n";
echo "Sender name: {$senderName}\n\n";

if (empty($password) || $password === 'ПАРОЛЬ_ПРИЛОЖЕНИЯ') {
    echo "❌ Пароль не установлен! Замените 'ПАРОЛЬ_ПРИЛОЖЕНИЯ' на реальный пароль.\n";
    exit(1);
}

try {
    echo "Creating transport...\n";
    
    // Создание транспорта с таймаутом
    $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
        'smtp.yandex.ru',
        587,
        true
    );
    $transport->setUsername($username);
    $transport->setPassword($password);
    $transport->setTimeout(30); // 30 секунд таймаут
    
    echo "Transport created. Testing connection...\n";
    
    // Тестируем соединение
    $transport->ping();
    echo "✅ Connection successful!\n";
    
    $mailer = new \Symfony\Component\Mailer\Mailer($transport);
    
    echo "Creating email message...\n";
    
    $email = (new \Symfony\Component\Mime\Email())
        ->from(new \Symfony\Component\Mime\Address($username, $senderName))
        ->to('fwadim@mail.ru')
        ->subject('Direct SMTP Test - ' . date('Y-m-d H:i:s'))
        ->text('This is a direct SMTP test. Sent at ' . date('Y-m-d H:i:s'));

    echo "Sending email...\n";
    $mailer->send($email);
    echo "✅ Email sent successfully!\n";
    
} catch (\Symfony\Component\Mailer\Exception\TransportException $e) {
    echo "❌ Transport error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
    if (strpos($e->getMessage(), '535') !== false) {
        echo "\n🔍 Ошибка 535: Неверный пароль.\n";
        echo "Проверьте:\n";
        echo "1. Используете ли вы пароль приложения (не основной пароль)\n";
        echo "2. Правильно ли скопирован пароль (без пробелов)\n";
        echo "3. Не истек ли срок действия пароля приложения\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}