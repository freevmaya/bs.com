<?php
// test-mailer-direct.php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';
$app = new yii\console\Application($config);

echo "=== DIRECT MAILER TEST ===\n\n";

try {
    $mailer = Yii::$app->mailer;
    $params = Yii::$app->params;
    
    $senderEmail = $params['senderEmail'] ?? 'freevmaya@yandex.ru';
    $senderName = $params['senderName'] ?? 'parasell.vmaya.ru';
    $to = 'fwadim@mail.ru';
    
    echo "1. Создание сообщения через compose()...\n";
    
    // Пробуем разные способы задания получателя
    
    // Способ 1: строка
    echo "\nСпособ 1: setTo как строка\n";
    try {
        $message1 = $mailer->compose()
            ->setFrom([$senderEmail => $senderName])
            ->setTo($to)
            ->setSubject('Test 1 - ' . date('Y-m-d H:i:s'))
            ->setTextBody('Test 1 body');
        
        $to1 = $message1->getTo();
        echo "  To: " . (is_array($to1) ? json_encode($to1) : 'null') . "\n";
        echo "  Result: " . ($message1->send() ? "✅ Sent\n" : "❌ Failed\n");
    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
    // Способ 2: массив с пустым именем
    echo "\nСпособ 2: setTo как массив ['to' => '']\n";
    try {
        $message2 = $mailer->compose()
            ->setFrom([$senderEmail => $senderName])
            ->setTo([$to => ''])
            ->setSubject('Test 2 - ' . date('Y-m-d H:i:s'))
            ->setTextBody('Test 2 body');
        
        $to2 = $message2->getTo();
        echo "  To: " . (is_array($to2) ? json_encode($to2) : 'null') . "\n";
        echo "  Result: " . ($message2->send() ? "✅ Sent\n" : "❌ Failed\n");
    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
    // Способ 3: массив с именем
    echo "\nСпособ 3: setTo как массив ['to' => 'Name']\n";
    try {
        $message3 = $mailer->compose()
            ->setFrom([$senderEmail => $senderName])
            ->setTo([$to => 'User Name'])
            ->setSubject('Test 3 - ' . date('Y-m-d H:i:s'))
            ->setTextBody('Test 3 body');
        
        $to3 = $message3->getTo();
        echo "  To: " . (is_array($to3) ? json_encode($to3) : 'null') . "\n";
        echo "  Result: " . ($message3->send() ? "✅ Sent\n" : "❌ Failed\n");
    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
    // Способ 4: через Address
    echo "\nСпособ 4: setTo через Address объект\n";
    try {
        $address = new \Symfony\Component\Mime\Address($to);
        $message4 = $mailer->compose()
            ->setFrom([$senderEmail => $senderName])
            ->setTo($address)
            ->setSubject('Test 4 - ' . date('Y-m-d H:i:s'))
            ->setTextBody('Test 4 body');
        
        $to4 = $message4->getTo();
        echo "  To: " . (is_array($to4) ? json_encode($to4) : 'null') . "\n";
        echo "  Result: " . ($message4->send() ? "✅ Sent\n" : "❌ Failed\n");
    } catch (Exception $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== КОНЕЦ ТЕСТА ===\n";