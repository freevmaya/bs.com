<?php
// test-curl.php

$smtp_server = "smtp.yandex.ru";
$smtp_port = 587;
$username = "freevmaya@yandex.ru";
$password = "bcqvhueipdxzebeb"; // Замените на реальный пароль

echo "=== SMTP TEST VIA CURL ===\n";
echo "Server: {$smtp_server}:{$smtp_port}\n";

// Проверка соединения через netcat
echo "\n1. Проверка соединения с сервером...\n";
$fp = @fsockopen($smtp_server, $smtp_port, $errno, $errstr, 10);
if (!$fp) {
    echo "❌ Не удалось подключиться: $errstr ($errno)\n";
    exit(1);
}
echo "✅ Соединение установлено\n";

// Чтение приветствия
$response = fgets($fp, 1024);
echo "Server: $response";

// STARTTLS
fwrite($fp, "EHLO localhost\r\n");
while ($line = fgets($fp, 1024)) {
    echo $line;
    if (substr($line, 3, 1) == ' ') break;
}

fwrite($fp, "STARTTLS\r\n");
while ($line = fgets($fp, 1024)) {
    echo $line;
    if (substr($line, 3, 1) == ' ') break;
}

fclose($fp);

echo "\n✅ Тест завершен\n";