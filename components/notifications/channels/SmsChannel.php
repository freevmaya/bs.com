<?php

namespace app\components\notifications\channels;

use Yii;

class SmsChannel implements NotificationChannelInterface
{
    private $apiKey;
    private $apiUrl;
    
    public function __construct($apiKey = null, $apiUrl = null)
    {
        $this->apiKey = $apiKey ?: Yii::$app->params['sms_api_key'] ?? null;
        $this->apiUrl = $apiUrl ?: Yii::$app->params['sms_api_url'] ?? null;
    }
    
    public function send($to, $subject, $message, $options = [])
    {
        try {
            // Здесь нужно реализовать интеграцию с конкретным SMS провайдером
            // Например, через SMS.ru или другие сервисы
            
            Yii::info("SMS to {$to}: {$message}", 'notification');
            
            // Пример отправки через HTTP API
            $client = new \yii\httpclient\Client();
            $response = $client->post($this->apiUrl, [
                'api_key' => $this->apiKey,
                'phone' => $to,
                'message' => $message,
            ])->send();
            
            if ($response->isOk) {
                $data = $response->data;
                if (isset($data['status']) && $data['status'] === 'ok') {
                    return true;
                }
            }
            
            Yii::error('SMS send failed: ' . $response->content, 'notification');
            return false;
            
        } catch (\Exception $e) {
            Yii::error('SMS send failed: ' . $e->getMessage(), 'notification');
            return false;
        }
    }
    
    public function getName()
    {
        return 'sms';
    }
    
    public function getDescription()
    {
        return 'SMS уведомления';
    }
    
    public function isAvailable()
    {
        return !empty($this->apiKey) && !empty($this->apiUrl);
    }
}