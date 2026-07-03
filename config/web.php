<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => 'your-secret-key-here',
        ],
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'user' => [
            'identityClass' => 'app\models\User',
            'enableAutoLogin' => true,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => 'yii\log\FileTarget',
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'enableStrictParsing' => false,
            'rules' => [
                '' => 'advertisements/index',
                'advertisements/sell' => 'advertisements/sell',
                'advertisements/buy' => 'advertisements/buy',
                'advertisements/my' => 'advertisements/my',
                'advertisement/<id:\d+>' => 'advertisements/view',
                
                // Правила для уведомлений
                'notification' => 'notification/index',
                'notification/subscribe' => 'notification/subscribe',
                'notification/unsubscribe' => 'notification/unsubscribe',
        
                // Правила для подписок на поиск
                'search-subscription' => 'search-subscription/index',
                'search-subscription/create' => 'search-subscription/create',
                'search-subscription/delete/<id:\d+>' => 'search-subscription/delete',
            ],
        ],
        'tempAdStorage' => [
            'class' => 'app\components\TempAdStorage',
        ],
        'notificationManager' => [
                'class' => 'app\components\notifications\NotificationManager',
            ]
        ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => 'yii\debug\Module',
    ];
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;