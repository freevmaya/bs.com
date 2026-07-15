<?php
// controllers/TelegramWebhookController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class TelegramWebhookController extends Controller
{
    public $enableCsrfValidation = false;
    
    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Логируем входящие запросы для отладки
        Yii::info('Telegram webhook received: ' . $input, 'telegram');
        
        if (!$data) {
            return 'OK';
        }
        
        // Обрабатываем сообщение
        if (isset($data['message'])) {
            $this->handleMessage($data['message']);
        }
        
        return 'OK';
    }
    
    private function handleMessage($message)
    {
        $chatId = $message['chat']['id'];
        $username = $message['chat']['username'] ?? null;
        $text = $message['text'] ?? '';
        
        // Логируем полученный chat_id
        $logMessage = date('Y-m-d H:i:s') . " | Chat ID: {$chatId}";
        if ($username) {
            $logMessage .= " | @{$username}";
        }
        $logMessage .= " | Text: " . substr($text, 0, 50) . "\n";
        file_put_contents(Yii::getAlias('@runtime') . '/telegram_chat_ids.log', $logMessage, FILE_APPEND);
        
        // Ищем пользователя в БД по username и сохраняем chat_id
        if ($username) {
            $user = \app\models\User::find()->where(['telegram' => $username])->one();
            if ($user) {
                // Сохраняем chat_id в БД
                $user->telegram_chat_id = (string)$chatId;
                if ($user->save()) {
                    Yii::info("Saved chat_id {$chatId} for user @{$username}", 'telegram');
                } else {
                    Yii::error("Failed to save chat_id for user @{$username}: " . json_encode($user->errors), 'telegram');
                }
            } else {
                // Если пользователь не найден по username, пробуем найти по email или другому полю
                // или создаем запись для последующей привязки
                Yii::info("User @{$username} not found in database", 'telegram');
            }
        }
        
        // Отвечаем пользователю
        $this->sendResponse($chatId, $username);
    }
    
    private function sendResponse($chatId, $username)
    {
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        if (!$botToken) {
            return;
        }
        
        $responseText = "✅ Бот активен!\n\n";
        $responseText .= "Ваш Chat ID: <code>{$chatId}</code>\n\n";
        $responseText .= "Используйте этот ID для получения уведомлений.\n";
        $responseText .= "Ваш username: @" . ($username ?: 'не указан');
        
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $client->post($apiUrl, [
                'chat_id' => $chatId,
                'text' => $responseText,
                'parse_mode' => 'HTML',
            ])->send();
        } catch (\Exception $e) {
            Yii::error('Failed to send response: ' . $e->getMessage(), 'telegram');
        }
    }
}