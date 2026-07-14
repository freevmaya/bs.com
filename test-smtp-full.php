<?php
// test-smtp-full.php

$username = 'freevmaya@yandex.ru';
$password = 'bcqvhueipdxzebeb'; // Замените на реальный пароль

echo "=== FULL SMTP TEST ===\n";
echo "Username: {$username}\n";
echo "Password length: " . strlen($password) . "\n\n";

$smtp_server = 'smtp.yandex.ru';
$smtp_port = 587;

// 1. Подключение
echo "1. Connecting to {$smtp_server}:{$smtp_port}...\n";
$fp = @fsockopen($smtp_server, $smtp_port, $errno, $errstr, 30);
if (!$fp) {
    echo "❌ Connection failed: $errstr ($errno)\n";
    exit(1);
}
echo "✅ Connected\n";

// 2. Чтение приветствия
$response = fgets($fp, 1024);
echo "Server: $response";

// 3. EHLO
echo "\n2. Sending EHLO...\n";
fwrite($fp, "EHLO localhost\r\n");
$response = '';
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    $response .= $line;
    if (substr($line, 3, 1) == ' ') break;
}

// 4. STARTTLS
echo "\n3. Starting TLS...\n";
fwrite($fp, "STARTTLS\r\n");
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

// 5. EHLO после TLS
echo "\n4. EHLO after TLS...\n";
fwrite($fp, "EHLO localhost\r\n");
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

// 6. AUTH LOGIN
echo "\n5. AUTH LOGIN...\n";
fwrite($fp, "AUTH LOGIN\r\n");
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

// 7. Отправка username в base64
$username64 = base64_encode($username);
echo "\n6. Sending username (base64): {$username64}\n";
fwrite($fp, $username64 . "\r\n");
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

// 8. Отправка password в base64
$password64 = base64_encode($password);
echo "\n7. Sending password (base64): " . substr($password64, 0, 4) . "...\n";
fwrite($fp, $password64 . "\r\n");
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

// 9. Отправка тестового письма
echo "\n8. Sending test email...\n";
fwrite($fp, "MAIL FROM: <{$username}>\r\n");
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

fwrite($fp, "RCPT TO: <fwadim@mail.ru>\r\n");
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

fwrite($fp, "DATA\r\n");
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

$message = "Subject: Test from PHP\r\n";
$message .= "From: {$username}\r\n";
$message .= "To: fwadim@mail.ru\r\n";
$message .= "\r\n";
$message .= "This is a test email from PHP.\r\n";
$message .= "Sent at " . date('Y-m-d H:i:s') . "\r\n";
$message .= ".\r\n";

fwrite($fp, $message);
while ($line = fgets($fp, 1024)) {
    echo "> $line";
    if (substr($line, 3, 1) == ' ') break;
}

// 10. QUIT
fwrite($fp, "QUIT\r\n");
fclose($fp);

echo "\n✅ Test completed\n";