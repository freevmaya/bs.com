<?php
// FILE: .\views\advertisements\_json_import.php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var yii\web\View $this
 */

// Получаем списки производителей и сертификаций для маппинга
$producers = \app\models\Producer::find()->all();
$certifications = \app\models\Certification::find()->all();

$producerMap = [];
foreach ($producers as $p) {
    $producerMap[strtolower($p->name)] = $p->id;
    if ($p->short) {
        $producerMap[strtolower($p->short)] = $p->id;
    }
}

$certMap = [];
foreach ($certifications as $c) {
    $certMap[strtolower($c->name)] = $c->id;
}

// Конфиг для JS
$jsonImportConfig = [
    'producerMap' => $producerMap,
    'certMap' => $certMap,
    'resetRatingUrl' => Url::to(['advertisements/reset-rating']),
    'importImageUrl' => Url::to(['advertisements/import-image']),
    'maxImagesPerImport' => 20,
    'csrfToken' => Yii::$app->request->csrfToken,
    'tempId' => Yii::$app->session->get('temp_ad_id'),
];

// Передаём конфиг до подключения скрипта
$this->registerJs(
    'window.jsonImportConfig = ' . json_encode($jsonImportConfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';',
    \yii\web\View::POS_BEGIN
);

// Подключаем внешний JS-файл
$this->registerJsFile('@web/js/json-import.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END,
]);
?>

<div class="panel panel-default" style="margin-top: 20px;">
    <div class="panel-body">
        <div class="alert alert-info">
            <span class="glyphicon glyphicon-info-sign"></span>
            Вставьте JSON, сгенерированный AI-моделью, для автоматического заполнения всех полей объявления.
            <strong>При импорте оценка AI будет сброшена</strong>, чтобы не показывать устаревшие данные.
            Если в JSON есть поле <code>images</code> (массив URL-ов) — изображения будут скачаны автоматически.
        </div>

        <div class="form-group">
            <label for="json-import">JSON данные объявления</label>
            <textarea id="json-import" class="form-control" rows="8" style="font-family: monospace; font-size: 13px;" placeholder='{
    "type": "harness",
    "section": "sell",
    "description": "Продаю подвеску...",
    "price": 45000,
    "currency": "RUB",
    "price_negotiable": false,
    "city": "Москва",
    "phone": "79687927864",
    "source_url": "https://example.com/product",
    "item_info_link": "https://example.com/info",
    "images": [
        "https://example.com/image1.jpg",
        "https://example.com/image2.jpg"
    ],
    "harness": {
        "model": "Genie Light",
        "producer_name": "Gin",
        "size": "M",
        "date_release": "2023",
        "condition": "excellent",
        "defects": ""
    }
}'></textarea>
        </div>

        <div class="form-group">
            <button type="button" class="btn btn-primary" id="json-import-btn">
                <span class="glyphicon glyphicon-play"></span> Заполнить из JSON
            </button>
            <button type="button" class="btn btn-default" id="json-import-clear">
                <span class="glyphicon glyphicon-erase"></span> Очистить
            </button>
            <span id="json-import-status" style="margin-left: 15px;"></span>
        </div>

        <div id="json-import-errors" class="alert alert-danger" style="display: none; margin-top: 10px;"></div>
    </div>
</div>