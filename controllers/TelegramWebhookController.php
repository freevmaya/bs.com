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
        // Устанавливаем формат ответа как RAW
        Yii::$app->response->format = Response::FORMAT_RAW;
        
        // Получаем входящие данные
        $input = file_get_contents('php://input');
        
        // Логируем входящий запрос для отладки
        if (!empty($input)) {
            Yii::info('Telegram webhook received', 'telegram');
        }
        
        if (empty($input)) {
            Yii::warning('Empty input received in webhook', 'telegram');
            return 'OK';
        }
        
        // Декодируем JSON
        $data = json_decode($input, true);
        if ($data === null) {
            Yii::error('Invalid JSON received: ' . substr($input, 0, 200), 'telegram');
            return 'OK';
        }
        
        // Логируем полученные данные (без sensitive данных)
        if (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'] ?? 'unknown';
            $username = $data['message']['chat']['username'] ?? 'no_username';
            Yii::info("Processing message from chat_id: {$chatId}, username: @{$username}", 'telegram');
        }
        
        // Обрабатываем сообщение
        if (isset($data['message'])) {
            try {
                $this->handleMessage($data['message']);
                Yii::info('Message handled successfully', 'telegram');
            } catch (\Exception $e) {
                Yii::error('Error handling message: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'telegram');
            }
        }
        
        // Обрабатываем callback query (если есть кнопки)
        if (isset($data['callback_query'])) {
            try {
                $this->handleCallbackQuery($data['callback_query']);
            } catch (\Exception $e) {
                Yii::error('Error handling callback query: ' . $e->getMessage(), 'telegram');
            }
        }
        
        return 'OK';
    }
    
    /**
     * Обработка входящего сообщения
     * 
     * @param array $message
     */
    private function handleMessage($message)
    {
        $chatId = $message['chat']['id'];
        $username = $message['chat']['username'] ?? null;
        $firstName = $message['chat']['first_name'] ?? '';
        $lastName = $message['chat']['last_name'] ?? '';
        $text = $message['text'] ?? '';
        $userId = $message['from']['id'] ?? null;
        
        // 1. Логируем полученный chat_id в файл
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
        
        // 2. Сохраняем chat_id в БД
        $userSaved = false;
        $user = null;
        
        // 2.1 Ищем пользователя по username
        if ($username) {
            $user = User::find()->where(['telegram' => $username])->one();
            if ($user) {
                $user->telegram_chat_id = (string)$chatId;
                if ($user->save()) {
                    $userSaved = true;
                    Yii::info("Saved chat_id {$chatId} for user @{$username} (ID: {$user->id})", 'telegram');
                } else {
                    Yii::error("Failed to save chat_id for user @{$username}: " . json_encode($user->errors), 'telegram');
                }
            }
        }
        
        // 2.2 Если пользователь не найден по username, ищем по ID телеграм-пользователя
        if (!$userSaved && $userId) {
            // Ищем пользователя по telegram_id (если у вас есть такое поле)
            // или создаем временную запись для последующей привязки
            Yii::info("User @{$username} not found in database, will try to find by Telegram ID", 'telegram');
            
            // Если у вас есть поле telegram_id в таблице users, добавьте его
            // $user = User::find()->where(['telegram_id' => $userId])->one();
            
            // Если пользователь все еще не найден, создаем запись в отдельной таблице
            // для последующей ручной привязки
            $this->saveUnregisteredUser($chatId, $username, $firstName, $lastName, $userId);
        }
        
        // 2.3 Если пользователь найден и сохранен, обновляем его контактные данные
        if ($userSaved && $user) {
            // Обновляем имя пользователя, если оно изменилось
            $needUpdate = false;
            if ($firstName && $user->username !== $username) {
                // Можно обновить поле username в БД, если оно отличается
                // $user->username = $username;
                // $needUpdate = true;
            }
            if ($needUpdate) {
                $user->save();
            }
        }
        
        // 3. Отвечаем пользователю
        $this->sendResponse($chatId, $username, $firstName, $userSaved);
        
        // 4. Если это команда /start - отправляем приветственное сообщение
        if (strpos($text, '/start') === 0) {
            $this->sendWelcomeMessage($chatId, $username);
        }
    }
    
    /**
     * Сохранение незарегистрированного пользователя
     * 
     * @param int $chatId
     * @param string|null $username
     * @param string $firstName
     * @param string $lastName
     * @param int $userId
     */
    private function saveUnregisteredUser($chatId, $username, $firstName, $lastName, $userId)
    {
        // Создаем запись в отдельной таблице unregistered_telegram_users
        // или сохраняем в лог для ручной обработки
        
        $logData = [
            'chat_id' => $chatId,
            'username' => $username,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'telegram_user_id' => $userId,
            'date' => date('Y-m-d H:i:s'),
        ];
        
        $logFile = Yii::getAlias('@runtime') . '/telegram_unregistered_users.log';
        file_put_contents($logFile, json_encode($logData) . "\n", FILE_APPEND);
        
        Yii::info("Unregistered user: @{$username} (chat_id: {$chatId})", 'telegram');
    }
    
    /**
     * Отправка ответа пользователю
     * 
     * @param int $chatId
     * @param string|null $username
     * @param string $firstName
     * @param bool $userSaved
     */
    private function sendResponse($chatId, $username, $firstName, $userSaved)
    {
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        if (!$botToken) {
            Yii::error('Bot token not configured', 'telegram');
            return;
        }
        
        $responseText = "✅ Бот активен!\n\n";
        $responseText .= "Ваш Chat ID: <code>{$chatId}</code>\n\n";
        
        if ($userSaved) {
            $responseText .= "✅ Ваш аккаунт привязан к сайту!\n";
            if ($username) {
                $responseText .= "Username: @{$username}\n";
            }
        } else {
            $responseText .= "⚠️ Ваш аккаунт НЕ привязан к сайту.\n\n";
            $responseText .= "Чтобы получать уведомления:\n";
            $responseText .= "1. Зарегистрируйтесь на сайте \n";
            $responseText .= "2. В профиле укажите Telegram: @{$username}\n";
            $responseText .= "3. После этого уведомления будут приходить сюда\n";
        }
        
        $responseText .= "\nИспользуйте этот ID для получения уведомлений.";
        if ($username) {
            $responseText .= "\nВаш username: @{$username}";
        }
        
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->post($apiUrl, [
                'chat_id' => $chatId,
                'text' => $responseText,
                'parse_mode' => 'HTML',
            ])->send();
            
            if ($response->isOk) {
                Yii::info("Response sent to chat_id: {$chatId}", 'telegram');
            } else {
                Yii::error("Failed to send response: " . $response->content, 'telegram');
            }
        } catch (\Exception $e) {
            Yii::error('Exception sending response: ' . $e->getMessage(), 'telegram');
        }
    }
    
    /**
     * Отправка приветственного сообщения
     * 
     * @param int $chatId
     * @param string|null $username
     */
    private function sendWelcomeMessage($chatId, $username)
    {
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        if (!$botToken) {
            return;
        }
        
        $responseText = "👋 Привет!\n\n";
        $responseText .= "Я бот сайта " . Yii::$app->name . "\n\n";
        $responseText .= "Я буду присылать тебе уведомления:\n";
        $responseText .= "🔔 О новых объявлениях по твоей подписке\n";
        $responseText .= "💬 О новых сообщениях в диалогах\n";
        $responseText .= "📢 О новых объявлениях на сайте\n\n";
        
        if ($username) {
            $user = User::find()->where(['telegram' => $username])->one();
            if ($user) {
                $responseText .= "✅ Твой аккаунт уже привязан!\n";
                $responseText .= "Ты будешь получать уведомления автоматически.\n";
            } else {
                $responseText .= "⚠️ Чтобы получать уведомления:\n";
                $responseText .= "1. Зарегистрируйся на сайте\n";
                $responseText .= "2. В профиле укажи Telegram: @{$username}\n";
                $responseText .= "3. После этого уведомления будут приходить сюда\n";
            }
        }
        
        $apiUrl = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $client->post($apiUrl, [
                'chat_id' => $chatId,
                'text' => $responseText,
                'parse_mode' => 'HTML',
            ])->send();
        } catch (\Exception $e) {
            Yii::error('Failed to send welcome message: ' . $e->getMessage(), 'telegram');
        }
    }
    
    /**
     * Обработка callback query (нажатие на кнопку)
     * 
     * @param array $callbackQuery
     */
    private function handleCallbackQuery($callbackQuery)
    {
        $chatId = $callbackQuery['message']['chat']['id'] ?? null;
        $data = $callbackQuery['data'] ?? '';
        
        if ($chatId && $data) {
            Yii::info("Callback query: {$data} from chat_id: {$chatId}", 'telegram');
            
            // Здесь можно обрабатывать нажатия на кнопки
            // Например, подписка/отписка от уведомлений
            
            // Отвечаем на callback
            $this->answerCallbackQuery($callbackQuery['id']);
        }
    }
    
    /**
     * Ответ на callback query
     * 
     * @param string $callbackId
     */
    private function answerCallbackQuery($callbackId)
    {
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        if (!$botToken) {
            return;
        }
        
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $client->post(
                'https://api.telegram.org/bot' . $botToken . '/answerCallbackQuery',
                ['callback_query_id' => $callbackId]
            )->send();
        } catch (\Exception $e) {
            Yii::error('Failed to answer callback query: ' . $e->getMessage(), 'telegram');
        }
    }
}