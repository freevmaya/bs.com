<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'log' => [
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        // Добавляем notificationManager для консоли
        'notificationManager' => [
            'class' => 'app\components\notifications\NotificationManager',
        ],
        // Добавляем mailer для консоли
        'mailer' => [
            'class' => 'yii\symfonymailer\Mailer',
            'useFileTransport' => true, // Для тестирования сохраняем в файлы
            'fileTransportPath' => '@runtime/mail',
        ],
        // Настройка UrlManager для консоли
        'urlManager' => [
            'baseUrl' => 'https://bs.com',
            'hostInfo' => 'https://bs.com',
            'scriptUrl' => 'https://bs.com/index.php',
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
    ];
    // configuration adjustments for 'dev' environment
    // requires version `2.1.21` of yii2-debug module
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;