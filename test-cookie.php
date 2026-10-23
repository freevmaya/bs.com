<?php
require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/config/console.php';
new yii\console\Application($config);

$parser = new app\components\WeMeasureParser();

echo "Cookie path: " . $parser->getCookiePath() . "\n";
echo "Exists: " . (is_file($parser->getCookiePath()) ? 'yes' : 'no') . "\n";
echo "Size: " . (is_file($parser->getCookiePath()) ? filesize($parser->getCookiePath()) : 0) . " bytes\n\n";

$ref = new ReflectionMethod($parser, 'fetch');
// setAccessible() не нужен в PHP 8.1+ и deprecated в 8.5 — убрано

$url = 'https://we-measure.io/user/wing/map/2026';
$html = $ref->invoke($parser, $url);

if ($html === null) {
    echo "fetch() returned NULL\n";
    exit(1);
}

echo "Length: " . strlen($html) . "\n";

$hasH2 = preg_match('~<h2>(.*?)</h2>~s', $html, $m);
echo "Has <h2>: " . ($hasH2 ? 'yes → ' . trim(strip_tags($m[1])) : 'NO') . "\n";

echo "Has <h3>Data</h3>: " . (mb_strpos($html, '<h3>Data</h3>') !== false ? 'yes' : 'NO') . "\n";
echo "Has <table class=\"flex\">: " . (mb_strpos($html, '<table class="flex">') !== false ? 'yes' : 'NO') . "\n";
echo "<tr class=\"group\"> count: " . preg_match_all('~<tr class="group">~', $html) . "\n";

$isLoginRedirect = mb_strpos($html, "url='/login'") !== false
    || mb_strpos($html, 'url="/login"') !== false;
echo "Redirect to /login: " . ($isLoginRedirect ? 'YES' : 'no') . "\n";

echo "\nFirst 500 chars:\n" . substr($html, 0, 500) . "\n";