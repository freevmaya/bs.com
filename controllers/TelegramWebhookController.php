<?php
// controllers/TelegramWebhookController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\models\User;

class TelegramWebhookController extends Controller
{
    public $enableCsrfValidation = false;
    
    public function actionIndex()
    {
        Yii::$app->response->format = Response::FORMAT_RAW;
        
        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            Yii::warning('Empty input received in webhook', 'telegram');
            return 'OK';
        }
        
        $data = json_decode($input, true);
        if ($data === null) {
            Yii::error('Invalid JSON received: ' . substr($input, 0, 200), 'telegram');
            return 'OK';
        }
        
        if (isset($data['message'])) {
            try {
                $this->handleMessage($data['message']);
            } catch (\Exception $e) {
                Yii::error('Error handling message: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'telegram');
            }
        }
        
        return 'OK';
    }
    
    private function handleMessage($message)
    {
        $chatId = $message['chat']['id'];
        $username = $message['chat']['username'] ?? null; // Приходит без @
        $firstName = $message['chat']['first_name'] ?? '';
        $lastName = $message['chat']['last_name'] ?? '';
        $text = $message['text'] ?? '';
        
        // Логируем
        $logMessage = date('Y-m-d H:i:s') . " | Chat ID: {$chatId}";
        if ($username) {
            $logMessage .= " | @{$username}";
        }
        if ($firstName) {
            $logMessage .= " | {$firstName}";
        }
        if ($lastName) {
            $logMessage .= " {$lastName}";
        }
        if ($text) {
            $logMessage .= " | Text: " . substr($text, 0, 50);
        }
        $logMessage .= "\n";
        
        $logFile = Yii::getAlias('@runtime') . '/telegram_chat_ids.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // ===== ПОИСК ПОЛЬЗОВАТЕЛЯ С УЧЕТОМ @ =====
        $user = null;
        $foundBy = null;
        
        if ($username) {
            // 1. Ищем по полю telegram (хранится с @ или без)
            // Пробуем найти как есть (без @)
            $user = User::find()->where(['telegram' => $username])->one();
            if ($user) {
                $foundBy = 'telegram_without_at';
                Yii::info("User found by telegram (without @): {$username}", 'telegram');
            }
            
            // 2. Если не нашли, пробуем с @
            if (!$user) {
                $user = User::find()->where(['telegram' => '@' . $username])->one();
                if ($user) {
                    $foundBy = 'telegram_with_at';
                    Yii::info("User found by telegram (with @): @{$username}", 'telegram');
                }
            }
            
            // 3. Если все еще не нашли, пробуем по username в системе
            if (!$user) {
                $user = User::find()->where(['username' => $username])->one();
                if ($user) {
                    $foundBy = 'system_username';
                    Yii::info("User found by system username: {$username}", 'telegram');
                }
            }
            
            // 4. И по username с @
            if (!$user) {
                $user = User::find()->where(['username' => '@' . $username])->one();
                if ($user) {
                    $foundBy = 'system_username_with_at';
                    Yii::info("User found by system username (with @): @{$username}", 'telegram');
                }
            }
        }
        
        // Сохраняем chat_id
        if ($user) {
            $oldChatId = $user->telegram_chat_id;
            
            // Сохраняем chat_id
            $user->telegram_chat_id = (string)$chatId;
            
            // Если поле telegram пустое или не соответствует, нормализуем
            // Рекомендуется хранить без @ для единообразия
            if (empty($user->telegram) || $user->telegram === '@' . $username) {
                $user->telegram = $username; // Сохраняем без @
            }
            
            if ($user->save()) {
                $message = "Saved chat_id {$chatId} for user {$user->username} (ID: {$user->id})";
                $message .= " | Found by: {$foundBy}";
                if ($oldChatId) {
                    $message .= " | Old chat_id: {$oldChatId}";
                }
                Yii::info($message, 'telegram');
                
                // Отправляем успешное сообщение
                $this->sendTelegramMessage($chatId, 
                    "✅ Ваш аккаунт <b>{$user->username}</b> успешно привязан!\n" .
                    "Chat ID: <code>{$chatId}</code>\n" .
                    "Telegram: @{$user->telegram}\n\n" .
                    "Теперь вы будете получать уведомления! 🎉"
                );
                return;
            } else {
                Yii::error("Failed to save chat_id: " . json_encode($user->errors), 'telegram');
                $this->sendTelegramMessage($chatId, "❌ Ошибка сохранения. Пожалуйста, свяжитесь с администратором.");
                return;
            }
        }
        
        // Пользователь не найден
        $this->sendTelegramMessage($chatId, 
            "⚠️ Ваш аккаунт не найден на сайте.\n\n" .
            "Чтобы получать уведомления:\n" .
            "1. Зарегистрируйтесь на сайте " . Yii::$app->name . "\n" .
            "2. В профиле укажите Telegram: @{$username}\n" .
            "3. После этого уведомления будут приходить сюда\n\n" .
            "Ваш Chat ID: <code>{$chatId}</code>\n" .
            "Username: @{$username}"
        );
        
        // Сохраняем в отдельный лог для ручной обработки
        $this->saveUnregisteredUser($chatId, $username, $firstName, $lastName);
    }
    
    private function sendTelegramMessage($chatId, $text)
    {
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        if (!$botToken) {
            return;
        }
        
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->post(
                'https://api.telegram.org/bot' . $botToken . '/sendMessage',
                [
                    'chat_id' => $chatId,
                    'text' => $text,
                    'parse_mode' => 'HTML',
                ]
            )->send();
            
            if ($response->isOk) {
                Yii::info("Response sent to chat_id: {$chatId}", 'telegram');
            } else {
                Yii::error("Failed to send response: " . $response->content, 'telegram');
            }
        } catch (\Exception $e) {
            Yii::error('Exception sending response: ' . $e->getMessage(), 'telegram');
        }
    }
    
    private function saveUnregisteredUser($chatId, $username, $firstName, $lastName)
    {
        $logData = [
            'chat_id' => $chatId,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'date' => date('Y-m-d H:i:s'),
        ];
        
        $logFile = Yii::getAlias('@runtime') . '/telegram_unregistered_users.log';
        file_put_contents($logFile, json_encode($logData, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        
        Yii::info("Unregistered user: @{$username} (chat_id: {$chatId})", 'telegram');
    }
}