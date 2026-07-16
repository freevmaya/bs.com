<?php
// widgets/SocialLogin.php

namespace app\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;

class SocialLogin extends Widget
{
    /**
     * @var array Список провайдеров для отображения
     */
    public $providers = ['vkontakte', 'google', 'facebook', 'yandex', 'github'];
    
    /**
     * @var bool Показывать ли разделитель
     */
    public $showDivider = true;
    
    /**
     * @var string Заголовок блока
     */
    public $title = 'Или войти через';
    
    public function run()
    {
        // Проверяем, какие провайдеры доступны
        $availableProviders = $this->getAvailableProviders();
        
        if (empty($availableProviders)) {
            return '';
        }
        
        return $this->render('social-login', [
            'providers' => $availableProviders,
            'showDivider' => $this->showDivider,
            'title' => $this->title,
        ]);
    }
    
    /**
     * Получает доступные провайдеры
     */
    private function getAvailableProviders()
    {
        $available = [];
        
        /** @var \yii\authclient\Collection $collection */
        $collection = Yii::$app->get('authClientCollection');
        
        foreach ($this->providers as $providerName) {
            try {
                $client = $collection->getClient($providerName);
                $clientId = $client->clientId;
                
                // Проверяем, что clientId не пустой
                if (!empty($clientId) && $clientId !== '') {
                    $available[] = $providerName;
                }
            } catch (\Exception $e) {
                // Провайдер не настроен
                continue;
            }
        }
        
        return $available;
    }
}