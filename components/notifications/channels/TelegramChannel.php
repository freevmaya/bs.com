<?php
// FILE: .\components\notifications\channels\TelegramChannel.php

namespace app\components\notifications\channels;

use Yii;

class TelegramChannel implements NotificationChannelInterface
{
    private $botToken;
    private $apiUrl = 'https://api.telegram.org/bot';
    
    public function __construct($botToken = null)
    {
        $this->botToken = $botToken ?: Yii::$app->params['telegram_bot_token'] ?? null;
    }
    
    public function send($to, $subject, $message, $options = [])
    {
        try {
            if (empty($to)) {
                Yii::error('Telegram recipient is empty', 'notification');
                return false;
            }
            
            if (empty($this->botToken)) {
                Yii::error('Telegram bot token is not configured', 'notification');
                return false;
            }
            
            // Очищаем username от @
            $chatId = ltrim($to, '@');
            
            $client = new \yii\httpclient\Client();
            $response = $client->post($this->apiUrl . $this->botToken . '/sendMessage', [
                'chat_id' => $chatId,
                'text' => $subject . "\n\n" . $message,
                'parse_mode' => 'HTML',
            ])->send();
            
            if ($response->isOk) {
                $data = $response->data;
                if (isset($data['ok']) && $data['ok'] === true) {
                    return true;
                }
                if (isset($data['description'])) {
                    Yii::error('Telegram API error: ' . $data['description'], 'notification');
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            Yii::error('Telegram send failed: ' . $e->getMessage(), 'notification');
            return false;
        }
    }
    
    public function getName()
    {
        return 'telegram';
    }
    
    public function getDescription()
    {
        return 'Telegram уведомления';
    }
    
    public function isAvailable()
    {
        return !empty($this->botToken);
    }
}