<?php
// test-smtp-full.php

$username = 'freevmaya@yandex.ru';
$password = 'bcqvhueipdxzebeb'; // Замените на реальный пароль

echo "=== FULL SMTP TEST ===\n";
echo "Username: {$username}\n";
echo "Password length: " . strlen($password) . "\n\n";

$smtp_server = 'smtp.yandex.ru';
$smtp_port = 587;

// Функция для чтения ответа
function readResponse($fp) {
    $response = '';
    while ($line = fgets($fp, 1024)) {
        $response .= $line;
        echo "> $line";
        if (substr($line, 3, 1) == ' ') break;
    }
    return $response;
}

// 1. Подключение
echo "1. Connecting to {$smtp_server}:{$smtp_port}...\n";
$fp = @fsockopen($smtp_server, $smtp_port, $errno, $errstr, 30);
if (!$fp) {
    echo "❌ Connection failed: $errstr ($errno)\n";
    exit(1);
}
echo "✅ Connected\n";
readResponse($fp);

// 2. EHLO
echo "\n2. Sending EHLO...\n";
fwrite($fp, "EHLO localhost\r\n");
readResponse($fp);

// 3. STARTTLS
echo "\n3. Starting TLS...\n";
fwrite($fp, "STARTTLS\r\n");
readResponse($fp);

// Включаем TLS
echo "Enabling TLS...\n";
if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
    echo "❌ Failed to enable TLS\n";
    exit(1);
}
echo "✅ TLS enabled\n";

// 4. EHLO после TLS
echo "\n4. EHLO after TLS...\n";
fwrite($fp, "EHLO localhost\r\n");
readResponse($fp);

// 5. AUTH LOGIN
echo "\n5. AUTH LOGIN...\n";
fwrite($fp, "AUTH LOGIN\r\n");
readResponse($fp);

// 6. Отправка username в base64
$username64 = base64_encode($username);
echo "\n6. Sending username (base64): {$username64}\n";
fwrite($fp, $username64 . "\r\n");
readResponse($fp);

// 7. Отправка password в base64
$password64 = base64_encode($password);
echo "\n7. Sending password (base64): " . substr($password64, 0, 4) . "...\n";
fwrite($fp, $password64 . "\r\n");
$response = readResponse($fp);

// Проверяем успешность аутентификации
if (strpos($response, '235') === false) {
    echo "❌ Authentication failed!\n";
    fclose($fp);
    exit(1);
}
echo "✅ Authentication successful!\n";

// 8. MAIL FROM
echo "\n8. Sending MAIL FROM...\n";
fwrite($fp, "MAIL FROM: <{$username}>\r\n");
readResponse($fp);

// 9. RCPT TO
echo "\n9. Sending RCPT TO...\n";
fwrite($fp, "RCPT TO: <fwadim@mail.ru>\r\n");
readResponse($fp);

// 10. DATA
echo "\n10. Sending DATA...\n";
fwrite($fp, "DATA\r\n");
readResponse($fp);

// 11. Отправка письма
$message = "Subject: Test from PHP\r\n";
$message .= "From: {$username}\r\n";
$message .= "To: fwadim@mail.ru\r\n";
$message .= "MIME-Version: 1.0\r\n";
$message .= "Content-Type: text/plain; charset=UTF-8\r\n";
$message .= "Content-Transfer-Encoding: 8bit\r\n";
$message .= "\r\n";
$message .= "This is a test email from PHP.\r\n";
$message .= "Sent at " . date('Y-m-d H:i:s') . "\r\n";
$message .= "\r\n";
$message .= ".\r\n";

echo "\n11. Sending message body...\n";
fwrite($fp, $message);
$response = readResponse($fp);

if (strpos($response, '250') !== false) {
    echo "✅ Email sent successfully!\n";
} else {
    echo "❌ Failed to send email\n";
}

// 12. QUIT
fwrite($fp, "QUIT\r\n");
fclose($fp);

echo "\n✅ Test completed\n";