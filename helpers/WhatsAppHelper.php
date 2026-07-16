<?php
// helpers/WhatsAppHelper.php

namespace app\helpers;

use Yii;

class WhatsAppHelper
{
    /**
     * Проверяет, настроен ли WhatsApp API
     */
    public static function isConfigured()
    {
        $apiKey = Yii::$app->params['whatsapp_api_key'] ?? null;
        $apiUrl = Yii::$app->params['whatsapp_api_url'] ?? null;
        
        return !empty($apiKey) && !empty($apiUrl) && 
               $apiKey !== 'ВАШ_WHATSAAP_API_KEY' && 
               $apiUrl !== 'https://whatsapp-api.example.com/send';
    }
    
    /**
     * Проверяет, доступен ли WhatsApp для пользователя
     */
    public static function isAvailableForUser($user)
    {
        if (!self::isConfigured()) {
            return false;
        }
        return !empty($user->whatsapp);
    }
}