<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';
new yii\console\Application($config);

$url = 'https://we-measure.io/database';

echo "URL: {$url}\n";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; we-measure-parser/1.0)',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$body = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
//curl_close($ch);

echo "HTTP: {$httpCode}\n";
echo "Curl error: " . ($curlError ?: 'none') . "\n";
echo "Body length: " . strlen((string)$body) . "\n";
echo "First 500 chars:\n";
echo substr((string)$body, 0, 500) . "\n";

if ($body) {
    preg_match_all('~<div class="box21"~', $body, $m);
    echo "\nbox21 count: " . count($m[0]) . "\n";

    preg_match_all('~<a class="tile"[^>]*href="(/user/wing/map/(\d+))"~s', $body, $m);
    echo "tile links count: " . count($m[0]) . "\n";
}