<?php
// components/notifications/channels/TelegramChannel.php

namespace app\components\notifications\channels;

use Yii;

class TelegramChannel implements NotificationChannelInterface
{
    private $botToken;
    private $apiUrl = 'https://api.telegram.org/bot';
    private $defaultChatId;
    
    public function __construct($botToken = null, $defaultChatId = null)
    {
        $this->botToken = $botToken ?: Yii::$app->params['telegram_bot_token'] ?? null;
        $this->defaultChatId = $defaultChatId ?: Yii::$app->params['telegram_default_chat_id'] ?? null;
    }
    
    public function send($to, $subject, $message, $options = [])
    {
        try {
            // Проверяем, что получатель указан
            if (empty($to) && empty($this->defaultChatId)) {
                Yii::error('Telegram recipient is empty and no default chat id', 'notification');
                return false;
            }
            
            // Проверяем, что токен настроен
            if (empty($this->botToken)) {
                Yii::error('Telegram bot token is not configured', 'notification');
                return false;
            }
            
            // Определяем chat_id
            $chatId = $this->resolveChatId($to);
            
            if (!$chatId) {
                Yii::error("Unable to resolve chat_id for: {$to}", 'notification');
                return false;
            }
            
            // Формируем текст сообщения
            $text = $this->formatMessage($subject, $message, $options);
            
            // Отправляем сообщение
            $result = $this->sendMessage($chatId, $text, $options);
            
            if ($result) {
                Yii::info("Telegram message sent to chat_id: {$chatId}", 'notification');
                return true;
            } else {
                Yii::error("Telegram send failed to chat_id: {$chatId}", 'notification');
                return false;
            }
            
        } catch (\Exception $e) {
            Yii::error('Telegram send failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'notification');
            return false;
        }
    }
    
    /**
     * Определяет chat_id из различных форматов
     * 
     * @param string|int $to Username, Chat ID или ID пользователя
     * @return string|int|null
     */
    private function resolveChatId($to)
    {
        // Если это уже число - это chat_id
        if (is_numeric($to)) {
            return (int)$to;
        }
        
        // Если это строка, начинающаяся с @ - это username
        $username = ltrim($to, '@');
        
        // Проверяем, есть ли пользователь с таким username в БД и есть ли у него chat_id
        if (!empty($username)) {
            $user = \app\models\User::find()
                ->where(['telegram' => $username])
                ->one();
            
            if ($user && !empty($user->telegram_chat_id)) {
                return $user->telegram_chat_id;
            }
        }
        
        // Если ничего не найдено, пробуем использовать как есть
        return $to;
    }
    
    /**
     * Форматирует сообщение для Telegram
     */
    private function formatMessage($subject, $message, $options = [])
    {
        $parts = [];
        
        // Заголовок жирным
        if (!empty($subject)) {
            $parts[] = "*" . $this->escapeMarkdown($subject) . "*";
            $parts[] = "";
        }
        
        // Основное сообщение
        if (!empty($message)) {
            $parts[] = $this->escapeMarkdown($message);
        }
        
        // Дополнительные опции
        if (isset($options['footer'])) {
            $parts[] = "";
            $parts[] = $this->escapeMarkdown($options['footer']);
        }
        
        // Подпись
        if (isset($options['signature']) && $options['signature'] !== false) {
            $parts[] = "";
            $parts[] = "_" . $this->escapeMarkdown(Yii::$app->name . " " . date('Y')) . "_";
        }
        
        return implode("\n", $parts);
    }
    
    /**
     * Экранирует специальные символы Markdown
     */
    private function escapeMarkdown($text)
    {
        $specialChars = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
        foreach ($specialChars as $char) {
            $text = str_replace($char, '\\' . $char, $text);
        }
        return $text;
    }
    
    /**
     * Отправляет сообщение через Telegram API
     */
    private function sendMessage($chatId, $text, $options = [])
    {
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            
            $params = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'MarkdownV2',
                'disable_web_page_preview' => true,
            ];
            
            // Добавляем клавиатуру если указана
            if (isset($options['keyboard'])) {
                $params['reply_markup'] = json_encode([
                    'inline_keyboard' => $options['keyboard'],
                ]);
            }
            
            // Добавляем кнопки если указаны
            if (isset($options['buttons'])) {
                $params['reply_markup'] = json_encode([
                    'inline_keyboard' => $options['buttons'],
                ]);
            }
            
            $response = $client->post(
                $this->apiUrl . $this->botToken . '/sendMessage',
                $params
            )->send();
            
            if ($response->isOk) {
                $data = $response->data;
                if (isset($data['ok']) && $data['ok'] === true) {
                    return true;
                }
                if (isset($data['description'])) {
                    $errorMsg = $data['description'];
                    Yii::error('Telegram API error: ' . $errorMsg, 'notification');
                    
                    // Проверяем конкретные ошибки
                    if (strpos($errorMsg, 'chat not found') !== false) {
                        Yii::warning("Chat {$chatId} not found. User may not have started the bot or username is incorrect.", 'notification');
                    } elseif (strpos($errorMsg, 'bot was blocked') !== false) {
                        Yii::warning("User {$chatId} blocked the bot.", 'notification');
                    } elseif (strpos($errorMsg, 'user is deactivated') !== false) {
                        Yii::warning("User {$chatId} is deactivated.", 'notification');
                    }
                }
            } else {
                Yii::error('Telegram HTTP error: ' . $response->statusCode, 'notification');
            }
            
            return false;
            
        } catch (\Exception $e) {
            Yii::error('Telegram sendMessage exception: ' . $e->getMessage(), 'notification');
            return false;
        }
    }
    
    /**
     * Проверяет, активен ли чат (отправляет тестовое сообщение)
     * 
     * @param string|null $chatId ID чата или username
     * @return array Всегда возвращает массив с ключами success, chat_id, message (и error при ошибке)
     */
    public function testConnection($chatId = null)
    {
        $testChatId = $chatId ?: $this->defaultChatId;
        
        if (!$testChatId) {
            return [
                'success' => false,
                'error' => 'No chat ID provided',
                'message' => 'Не указан ID чата',
            ];
        }
        
        try {
            // Проверяем, что токен есть
            if (empty($this->botToken)) {
                return [
                    'success' => false,
                    'error' => 'Bot token not configured',
                    'message' => 'Токен бота не настроен',
                ];
            }
            
            // Очищаем chat_id от @
            $chatId = ltrim($testChatId, '@');
            
            // Сначала пробуем получить информацию о чате
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                $this->apiUrl . $this->botToken . '/getChat',
                ['chat_id' => $chatId]
            )->send();
            
            if (!$response->isOk || !isset($response->data['ok']) || !$response->data['ok']) {
                $errorMsg = isset($response->data['description']) ? $response->data['description'] : 'Chat not found';
                return [
                    'success' => false,
                    'chat_id' => $chatId,
                    'error' => $errorMsg,
                    'message' => 'Чат не найден: ' . $errorMsg,
                    'bot_username' => $this->getBotUsername(),
                ];
            }
            
            // Чат найден, пробуем отправить тестовое сообщение
            $result = $this->sendMessage(
                $chatId,
                "✅ *Бот работает!*\n\nТестовое сообщение отправлено в " . date('Y-m-d H:i:s'),
                ['signature' => false]
            );
            
            if ($result) {
                return [
                    'success' => true,
                    'chat_id' => $chatId,
                    'message' => 'Connection successful',
                ];
            } else {
                return [
                    'success' => false,
                    'chat_id' => $chatId,
                    'error' => 'User has not started a chat with the bot',
                    'message' => 'Пользователь не начал диалог с ботом. Перейдите по ссылке: https://t.me/' . $this->getBotUsername(),
                    'bot_username' => $this->getBotUsername(),
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'chat_id' => $testChatId,
                'error' => $e->getMessage(),
                'message' => 'Исключение: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Проверяет, подписан ли пользователь на бота
     * 
     * @param string $chatId ID чата или username пользователя
     * @return array ['success' => bool, 'is_subscribed' => bool, 'message' => string, 'error' => string|null]
     */
    public function checkSubscription($chatId)
    {
        try {
            if (empty($this->botToken)) {
                return [
                    'success' => false,
                    'is_subscribed' => false,
                    'error' => 'Bot token not configured',
                    'message' => 'Токен бота не настроен',
                ];
            }
            
            // Очищаем chat_id от @
            $chatId = ltrim($chatId, '@');
            
            // Получаем информацию о боте
            $botInfo = $this->getBotInfo();
            if ($botInfo && isset($botInfo['username'])) {
                // Если пытаемся проверить подписку самого бота - пропускаем
                if ($chatId === $botInfo['username']) {
                    return [
                        'success' => true,
                        'is_subscribed' => true,
                        'message' => 'Это сам бот, проверка пропущена',
                        'chat_id' => $chatId,
                        'bot_username' => $botInfo['username'],
                        'is_bot_self' => true,
                    ];
                }
            }
            
            // Сначала пробуем получить информацию о чате
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                $this->apiUrl . $this->botToken . '/getChat',
                ['chat_id' => $chatId]
            )->send();
            
            // Если чат не найден
            if (!$response->isOk || !isset($response->data['ok']) || !$response->data['ok']) {
                $errorMsg = isset($response->data['description']) ? $response->data['description'] : 'Unknown error';
                
                // Проверяем, может это просто пользователь не начал диалог
                if (strpos($errorMsg, 'chat not found') !== false) {
                    return [
                        'success' => true,
                        'is_subscribed' => false,
                        'message' => 'Пользователь не найден. Возможно, он не начал диалог с ботом или указан неверный username.',
                        'error' => null,
                        'chat_id' => $chatId,
                        'bot_username' => $botInfo['username'] ?? null,
                    ];
                }
                
                return [
                    'success' => false,
                    'is_subscribed' => false,
                    'error' => $errorMsg,
                    'message' => 'Ошибка получения информации о чате: ' . $errorMsg,
                ];
            }
            
            // Чат найден, теперь пробуем отправить тестовое сообщение
            $testResult = $this->sendMessage(
                $chatId,
                "🔍 Проверка подписки...\n\nЕсли вы видите это сообщение, значит вы подписаны на бота! ✅",
                ['signature' => false]
            );
            
            if ($testResult) {
                return [
                    'success' => true,
                    'is_subscribed' => true,
                    'message' => 'Пользователь подписан на бота',
                    'chat_id' => $chatId,
                    'bot_username' => $botInfo['username'] ?? null,
                ];
            } else {
                // Не удалось отправить сообщение - возможно, пользователь не начал диалог
                return [
                    'success' => true,
                    'is_subscribed' => false,
                    'message' => 'Пользователь не начал диалог с ботом или заблокировал бота',
                    'chat_id' => $chatId,
                    'bot_username' => $botInfo['username'] ?? null,
                ];
            }
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'is_subscribed' => false,
                'error' => $e->getMessage(),
                'message' => 'Ошибка проверки: ' . $e->getMessage(),
            ];
        }
    }
    
    /**
     * Получает информацию о боте
     * 
     * @return array|null
     */
    public function getBotInfo()
    {
        try {
            if (empty($this->botToken)) {
                return null;
            }
            
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                $this->apiUrl . $this->botToken . '/getMe'
            )->send();
            
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                return $response->data['result'];
            }
            
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Получает username бота
     * 
     * @return string|null
     */
    public function getBotUsername()
    {
        $info = $this->getBotInfo();
        return $info['username'] ?? null;
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