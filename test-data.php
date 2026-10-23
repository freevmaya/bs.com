<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';
new yii\console\Application($config);

$parser = new app\components\WeMeasureParser();

$ref = new ReflectionMethod($parser, 'fetch');
$html = $ref->invoke($parser, 'https://we-measure.io/user/wing/map/2026');

// Сколько раз встречается <h3>Data</h3>
echo "<h3>Data</h3> count: " . mb_substr_count($html, '<h3>Data</h3>') . "\n";
echo "<table class=\"flex\"> count: " . mb_substr_count($html, '<table class="flex">') . "\n";
echo "<tr class=\"group\"> count: " . mb_substr_count($html, '<tr class="group">') . "\n\n";

// Первое вхождение <h3>Data</h3> и что после него
$pos = mb_strpos($html, '<h3>Data</h3>');
echo "<h3>Data</h3> first pos: {$pos}\n";
echo "After <h3>Data</h3> (300 chars):\n";
echo mb_substr($html, $pos, 300) . "\n\n";

// extractDataTable — что реально вернул
$refExtract = new ReflectionMethod($parser, 'extractDataTable');
$tableHtml = $refExtract->invoke($parser, $html);
echo "extractDataTable length: " . ($tableHtml === null ? 'NULL' : mb_strlen($tableHtml)) . "\n";
if ($tableHtml !== null) {
    echo "Table start (500 chars):\n" . mb_substr($tableHtml, 0, 500) . "\n\n";

    // parseGroups
    $refGroups = new ReflectionMethod($parser, 'parseGroups');
    $groups = $refGroups->invoke($parser, $tableHtml);
    echo "parseGroups count: " . count($groups) . "\n";
    if (!empty($groups)) {
        echo "First group:\n";
        print_r($groups[0]);
    }
}