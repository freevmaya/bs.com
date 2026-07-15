<?php
// commands/TelegramController.php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

class TelegramController extends Controller
{
    private $botToken;
    
    public function init()
    {
        parent::init();
        $this->botToken = Yii::$app->params['telegram_bot_token'] ?? null;
    }
    
    /**
     * Установка вебхука
     * 
     * @param string $url URL для вебхука (например: https://bs.com/telegram-webhook)
     */
    public function actionSetWebhook($url)
    {
        $this->stdout("=== УСТАНОВКА ВЕБХУКА ===\n", Console::FG_YELLOW);
        $this->stdout("URL: {$url}\n\n");
        
        if (!$this->botToken) {
            $this->stderr("❌ Telegram bot token не настроен в config/params.php\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->post(
                'https://api.telegram.org/bot' . $this->botToken . '/setWebhook',
                ['url' => $url]
            )->send();
            
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $this->stdout("✅ Вебхук успешно установлен!\n", Console::FG_GREEN);
                $this->stdout("   URL: {$url}\n");
                $this->stdout("   Описание: " . ($response->data['description'] ?? 'OK') . "\n");
                
                // Проверяем установку
                $this->actionGetWebhookInfo();
            } else {
                $error = $response->data['description'] ?? 'Unknown error';
                $this->stderr("❌ Ошибка установки вебхука: {$error}\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        return ExitCode::OK;
    }
    
    /**
     * Получение информации о вебхуке
     */
    public function actionGetWebhookInfo()
    {
        $this->stdout("\n=== ИНФОРМАЦИЯ О ВЕБХУКЕ ===\n", Console::FG_YELLOW);
        
        if (!$this->botToken) {
            $this->stderr("❌ Telegram bot token не настроен\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                'https://api.telegram.org/bot' . $this->botToken . '/getWebhookInfo'
            )->send();
            
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $info = $response->data['result'];
                $this->stdout("URL: " . ($info['url'] ?: 'не установлен') . "\n");
                $this->stdout("Ожидание: " . ($info['pending_update_count'] ?? 0) . " обновлений\n");
                $this->stdout("Последняя ошибка: " . ($info['last_error_message'] ?? 'нет') . "\n");
            } else {
                $this->stderr("❌ Ошибка: " . ($response->data['description'] ?? 'Unknown') . "\n", Console::FG_RED);
            }
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
        }
        
        return ExitCode::OK;
    }
    
    /**
     * Удаление вебхука
     */
    public function actionDeleteWebhook()
    {
        $this->stdout("=== УДАЛЕНИЕ ВЕБХУКА ===\n", Console::FG_YELLOW);
        
        if (!$this->botToken) {
            $this->stderr("❌ Telegram bot token не настроен\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->post(
                'https://api.telegram.org/bot' . $this->botToken . '/deleteWebhook'
            )->send();
            
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $this->stdout("✅ Вебхук удален\n", Console::FG_GREEN);
            } else {
                $this->stderr("❌ Ошибка: " . ($response->data['description'] ?? 'Unknown') . "\n", Console::FG_RED);
            }
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
        }
        
        return ExitCode::OK;
    }
}