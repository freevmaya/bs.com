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
            // to может быть ID или ссылкой на профиль
            $userId = $this->resolveUserId($to);
            
            if (!$userId) {
                Yii::error("Unable to resolve VK user ID from: {$to}", 'notification');
                return false;
            }
            
            $client = new \yii\httpclient\Client();
            $response = $client->post('https://api.vk.com/method/messages.send', [
                'user_id' => $userId,
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
    
    /**
     * Получение user_id из ссылки или ID
     */
    private function resolveUserId($input)
    {
        // Если это уже число - возвращаем как есть
        if (is_numeric($input)) {
            return (int)$input;
        }
        
        // Если это ссылка, извлекаем screen_name
        if (filter_var($input, FILTER_VALIDATE_URL)) {
            $screenName = $this->extractScreenName($input);
            if (!$screenName) {
                return null;
            }
            return $this->getUserIdByScreenName($screenName);
        }
        
        // Возможно, это screen_name без ссылки
        return $this->getUserIdByScreenName($input);
    }
    
    /**
     * Извлечение screen_name из URL
     */
    private function extractScreenName($url)
    {
        try {
            $parts = parse_url($url);
            if (!isset($parts['path'])) {
                return null;
            }
            $path = trim($parts['path'], '/');
            if (!$path) {
                return null;
            }
            if (preg_match('/^id(\d+)$/', $path, $matches)) {
                return $matches[1];
            }
            $segments = explode('/', $path);
            return $segments[0];
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Получение user_id по screen_name через VK API
     */
    private function getUserIdByScreenName($screenName)
    {
        try {
            $url = 'https://api.vk.com/method/users.get?' . http_build_query([
                'user_ids' => $screenName,
                'v' => $this->apiVersion,
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['response']) && !empty($data['response'])) {
                return (int)$data['response'][0]['id'];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
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