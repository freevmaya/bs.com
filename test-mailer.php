<?php
// test-mailer.php

$username = 'freevmaya@yandex.ru';
$password = 'bcqvhueipdxzebeb'; // Замените на реальный пароль
$senderName = 'parasell.vmaya.ru';

echo "=== DIRECT SMTP TEST ===\n";

try {
    $transport = (new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
        'smtp.yandex.ru',
        587,
        true
    ))
    ->setUsername($username)
    ->setPassword($password);

    $mailer = new \Symfony\Component\Mailer\Mailer($transport);
    
    $email = (new \Symfony\Component\Mime\Email())
        ->from(new \Symfony\Component\Mime\Address($username, $senderName))
        ->to('fwadim@mail.ru')
        ->subject('Direct SMTP Test - ' . date('Y-m-d H:i:s'))
        ->text('This is a direct SMTP test. Sent at ' . date('Y-m-d H:i:s'));

    $mailer->send($email);
    echo "✅ Email sent successfully!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}