<?php
// FILE: .\components\notifications\channels\WhatsAppChannel.php

namespace app\components\notifications\channels;

use Yii;

class WhatsAppChannel implements NotificationChannelInterface
{
    private $apiKey;
    private $apiUrl;
    private $senderNumber;
    
    public function __construct($apiKey = null, $apiUrl = null, $senderNumber = null)
    {
        $this->apiKey = $apiKey ?: Yii::$app->params['whatsapp_api_key'] ?? null;
        $this->apiUrl = $apiUrl ?: Yii::$app->params['whatsapp_api_url'] ?? null;
        $this->senderNumber = $senderNumber ?: Yii::$app->params['whatsapp_sender_number'] ?? null;
    }
    
    public function send($to, $subject, $message, $options = [])
    {
        try {
            if (empty($to)) {
                Yii::error('WhatsApp recipient is empty', 'notification');
                return false;
            }
            
            if (empty($this->apiKey) || empty($this->apiUrl)) {
                Yii::error('WhatsApp API is not configured', 'notification');
                return false;
            }
            
            // Очищаем номер от лишних символов
            $phoneNumber = preg_replace('/[^0-9]/', '', $to);
            
            // Пример отправки через HTTP API
            $client = new \yii\httpclient\Client();
            $response = $client->post($this->apiUrl, [
                'api_key' => $this->apiKey,
                'phone' => $phoneNumber,
                'sender' => $this->senderNumber,
                'message' => $subject . "\n\n" . $message,
            ])->send();
            
            if ($response->isOk) {
                $data = $response->data;
                if (isset($data['status']) && $data['status'] === 'ok') {
                    return true;
                }
            }
            
            Yii::error('WhatsApp send failed: ' . $response->content, 'notification');
            return false;
            
        } catch (\Exception $e) {
            Yii::error('WhatsApp send failed: ' . $e->getMessage(), 'notification');
            return false;
        }
    }
    
    public function getName()
    {
        return 'whatsapp';
    }
    
    public function getDescription()
    {
        return 'WhatsApp уведомления';
    }
    
    public function isAvailable()
    {
        // Проверяем, что ключи не пустые и не являются значениями по умолчанию
        $apiKey = $this->apiKey ?? Yii::$app->params['whatsapp_api_key'] ?? null;
        $apiUrl = $this->apiUrl ?? Yii::$app->params['whatsapp_api_url'] ?? null;
        
        return !empty($apiKey) && !empty($apiUrl) && 
               $apiKey !== 'ВАШ_WHATSAAP_API_KEY' && 
               $apiUrl !== 'https://whatsapp-api.example.com/send';
    }
}