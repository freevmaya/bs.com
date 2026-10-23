<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';
new yii\console\Application($config);

$parser = new app\components\WeMeasureParser();

// 1. Только каталог
try {
    $catalog = $parser->parseCatalog();
    echo "Catalog items: " . count($catalog) . "\n";
    $first = array_key_first($catalog);
    echo "First source_id: {$first}\n";
    print_r($catalog[$first] ?? null);
} catch (\Throwable $e) {
    echo "Catalog error: " . $e->getMessage() . "\n";
}

// 2. Одна страница
try {
    $ok = $parser->parseAndSaveWingPage($first, $catalog[$first]);
    echo "Page parsed: " . ($ok ? 'OK' : 'FAIL') . "\n";
    print_r($parser->stats);
} catch (\Throwable $e) {
    echo "Page error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}