<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';
new yii\console\Application($config);

$url = 'https://we-measure.io/user/wing/map/2026';

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; we-measure-parser/1.0)',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);
$html = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP: {$http}\n";
echo "Length: " . strlen((string)$html) . "\n\n";

// 1. h2
preg_match('~<h2>(.*?)</h2>~s', $html, $m);
echo "<h2>: " . (isset($m[1]) ? trim(strip_tags($m[1])) : 'NOT FOUND') . "\n";

// 2. h3 Data
$pos = mb_strpos($html, '<h3>Data</h3>');
echo "<h3>Data</h3> pos: " . ($pos === false ? 'NOT FOUND' : $pos) . "\n";

// 2a. альтернативные варианты
foreach ([
    '<h3>Data</h3>',
    '<h3>Data </h3>',
    '<h3> Data</h3>',
    '<h3 class="title">Data</h3>',
] as $variant) {
    $p = mb_strpos($html, $variant);
    if ($p !== false) {
        echo "variant '{$variant}' found at {$p}\n";
    }
}

// 3. table flex
$tail = $pos !== false ? mb_substr($html, $pos) : $html;
foreach ([
    '<table class="flex">',
    '<table class="flex ',
    '<table class="flex"',
    '<table  class="flex">',
] as $variant) {
    $p = mb_strpos($tail, $variant);
    if ($p !== false) {
        echo "table '{$variant}' found at {$p} (offset from Data)\n";
        break;
    }
}

// 4. группы
preg_match_all('~<tr class="group">~', $tail, $gm);
echo "<tr class=\"group\"> count: " . count($gm[0]) . "\n";

// 5. первые 200 символов после <h3>Data</h3>
if ($pos !== false) {
    echo "\nAfter <h3>Data</h3> (500 chars):\n";
    echo substr($html, $pos, 500) . "\n";
}