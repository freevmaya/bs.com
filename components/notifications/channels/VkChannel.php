<?php

namespace app\components\notifications\channels;

use Yii;

class VkChannel implements NotificationChannelInterface
{
    private $accessToken;
    private $apiVersion = '5.131';
    
    public function __construct($accessToken = null)
    {
        $this->accessToken = $accessToken ?: Yii::$app->params['vk_access_token'] ?? null;
    }
    
    public function send($to, $subject, $message, $options = [])
    {
        try {
            // В VK сообщения отправляются через сообщения сообщества или личные сообщения
            // to - это user_id в VK
            // subject - не используется в VK
            
            $client = new \yii\httpclient\Client();
            $response = $client->post('https://api.vk.com/method/messages.send', [
                'user_id' => $to,
                'message' => $message,
                'random_id' => rand(1, 1000000),
                'v' => $this->apiVersion,
                'access_token' => $this->accessToken,
            ])->send();
            
            if ($response->isOk) {
                $data = $response->data;
                if (isset($data['response'])) {
                    return true;
                }
                if (isset($data['error'])) {
                    Yii::error('VK API error: ' . json_encode($data['error']), 'notification');
                }
            }
            
            return false;
            
        } catch (\Exception $e) {
            Yii::error('VK send failed: ' . $e->getMessage(), 'notification');
            return false;
        }
    }
    
    public function getName()
    {
        return 'vk';
    }
    
    public function getDescription()
    {
        return 'VK уведомления';
    }
    
    public function isAvailable()
    {
        return !empty($this->accessToken);
    }
}