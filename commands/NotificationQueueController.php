<?php
// commands/NotificationQueueController.php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use app\models\NotificationLog;
use app\models\User;

class NotificationQueueController extends Controller
{
    /**
     * Обработка очереди уведомлений (запускать по крону)
     * 
     * @param int $limit Максимальное количество уведомлений за один запуск
     * @param bool $dryRun Режим сухого запуска (только показать, что будет отправлено)
     */
    public function actionProcess($limit = 100, $dryRun = 0)
    {
        $startTime = microtime(true);
        $this->stdout("=== ОБРАБОТКА ОЧЕРЕДИ УВЕДОМЛЕНИЙ ===\n", Console::FG_YELLOW);
        $this->stdout("Дата: " . date('Y-m-d H:i:s') . "\n");
        $this->stdout("Лимит: {$limit}\n");
        $this->stdout("Dry-run: " . ($dryRun ? 'ДА' : 'НЕТ') . "\n\n");
        
        // Получаем уведомления из очереди
        $notifications = NotificationLog::getPendingNotifications($limit);
        
        if (empty($notifications)) {
            $this->stdout("Нет уведомлений в очереди\n", Console::FG_GREEN);
            return ExitCode::OK;
        }
        
        $this->stdout("Найдено уведомлений в очереди: " . count($notifications) . "\n\n", Console::FG_CYAN);
        
        $processed = 0;
        $sent = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($notifications as $notification) {
            $processed++;
            $this->stdout("[{$processed}/" . count($notifications) . "] ");
            $this->stdout("Уведомление #{$notification->id} ", Console::FG_CYAN);
            $this->stdout("(канал: {$notification->channel}, ");
            $this->stdout("пользователь: {$notification->user_id}) - ");
            
            if ($dryRun) {
                $this->stdout("[DRY-RUN] Пропускаем\n", Console::FG_YELLOW);
                continue;
            }
            
            // Увеличиваем счетчик попыток
            $notification->incrementRetry();
            
            // Получаем пользователя
            $user = User::findOne($notification->user_id);
            if (!$user) {
                $notification->markAsFailed('User not found');
                $this->stdout("❌ Пользователь не найден\n", Console::FG_RED);
                $failed++;
                continue;
            }
            
            // Получаем канал
            $channel = Yii::$app->notificationManager->getChannel($notification->channel);
            if (!$channel) {
                $notification->markAsFailed("Channel '{$notification->channel}' not found");
                $this->stdout("❌ Канал не найден\n", Console::FG_RED);
                $failed++;
                continue;
            }
            
            if (!$channel->isAvailable()) {
                $notification->markAsFailed("Channel '{$notification->channel}' is not available");
                $this->stdout("❌ Канал недоступен\n", Console::FG_RED);
                $failed++;
                continue;
            }
            
            // Получаем получателя
            $to = $this->getRecipient($user, $notification->channel);
            if (!$to) {
                $notification->markAsFailed("No recipient found for channel '{$notification->channel}'");
                $this->stdout("❌ Получатель не найден\n", Console::FG_RED);
                $failed++;
                continue;
            }
            
            // Отправляем
            try {
                $subject = $notification->subject;
                $message = $notification->getTextMessage();
                $htmlBody = $notification->getHtmlBody();
                
                $options = [];
                if ($htmlBody) {
                    $options['html_body'] = $htmlBody;
                }
                
                $result = $channel->send($to, $subject, $message, $options);
                
                if ($result) {
                    $notification->markAsSent();
                    $this->stdout("✅ Отправлено\n", Console::FG_GREEN);
                    $sent++;
                } else {
                    $notification->markAsFailed('Send failed');
                    $this->stdout("❌ Ошибка отправки\n", Console::FG_RED);
                    $failed++;
                    $errors[] = $notification->id;
                }
                
            } catch (\Exception $e) {
                $notification->markAsFailed($e->getMessage());
                $this->stdout("❌ Исключение: " . $e->getMessage() . "\n", Console::FG_RED);
                $failed++;
                $errors[] = $notification->id;
            }
        }
        
        // Итоги
        $elapsed = round(microtime(true) - $startTime, 2);
        $this->stdout("\n=== ИТОГИ ===\n", Console::FG_YELLOW);
        $this->stdout("Обработано: {$processed}\n");
        $this->stdout("Успешно отправлено: {$sent}\n");
        $this->stdout("Ошибок: {$failed}\n");
        $this->stdout("Время выполнения: {$elapsed} сек.\n");
        
        if (!empty($errors)) {
            $this->stdout("\n⚠️ Ошибки в уведомлениях: " . implode(', ', $errors) . "\n", Console::FG_YELLOW);
        }
        
        // Показываем остаток очереди
        $pending = NotificationLog::getPendingCount();
        if ($pending > 0) {
            $this->stdout("\nОсталось в очереди: {$pending}\n", Console::FG_CYAN);
        }
        
        return ExitCode::OK;
    }
    
    /**
     * Получить получателя для канала
     */
    private function getRecipient($user, $channelName)
    {
        switch ($channelName) {
            case 'email':
                return $user->email;
            case 'sms':
                return $user->phone;
            case 'vk':
                return $user->vk_id ?? null;
            case 'telegram':
                if (!empty($user->telegram_chat_id)) {
                    return $user->telegram_chat_id;
                }
                return $user->telegram ?? null;
            case 'whatsapp':
                return $user->whatsapp;
            default:
                return null;
        }
    }
    
    /**
     * Очистка старых записей из лога
     * 
     * @param int $days Количество дней, после которых удалять
     */
    public function actionCleanup($days = 30)
    {
        $this->stdout("=== ОЧИСТКА СТАРЫХ ЗАПИСЕЙ ===\n", Console::FG_YELLOW);
        $this->stdout("Удаляем записи старше {$days} дней\n\n");
        
        $timestamp = time() - ($days * 24 * 60 * 60);
        
        $deleted = NotificationLog::deleteAll([
            'and',
            ['status' => [NotificationLog::STATUS_SENT, NotificationLog::STATUS_FAILED]],
            ['<', 'created_at', $timestamp]
        ]);
        
        $this->stdout("Удалено записей: {$deleted}\n", Console::FG_GREEN);
        
        return ExitCode::OK;
    }
    
    /**
     * Статистика очереди
     */
    public function actionStats()
    {
        $this->stdout("=== СТАТИСТИКА ОЧЕРЕДИ УВЕДОМЛЕНИЙ ===\n", Console::FG_YELLOW);
        
        $total = NotificationLog::find()->count();
        $queued = NotificationLog::find()->where(['status' => NotificationLog::STATUS_QUEUED])->count();
        $sent = NotificationLog::find()->where(['status' => NotificationLog::STATUS_SENT])->count();
        $failed = NotificationLog::find()->where(['status' => NotificationLog::STATUS_FAILED])->count();
        $pending = NotificationLog::find()
            ->where(['status' => NotificationLog::STATUS_QUEUED])
            ->andWhere(['<', 'retry_count', 5])
            ->count();
        $failedMaxRetry = NotificationLog::find()
            ->where(['status' => NotificationLog::STATUS_QUEUED])
            ->andWhere(['>=', 'retry_count', 5])
            ->count();
        
        $this->stdout("\nВсего записей: {$total}\n");
        $this->stdout("В очереди (всего): {$queued}\n");
        $this->stdout("В очереди (ожидают отправки): {$pending}\n");
        $this->stdout("В очереди (превышен лимит попыток): {$failedMaxRetry}\n");
        $this->stdout("Успешно отправлено: {$sent}\n");
        $this->stdout("Ошибок: {$failed}\n");
        
        // Статистика по каналам
        $this->stdout("\n=== ПО КАНАЛАМ ===\n", Console::FG_CYAN);
        $channels = ['email', 'telegram', 'vk', 'whatsapp', 'sms'];
        foreach ($channels as $channel) {
            $queuedChannel = NotificationLog::find()
                ->where(['channel' => $channel, 'status' => NotificationLog::STATUS_QUEUED])
                ->count();
            $sentChannel = NotificationLog::find()
                ->where(['channel' => $channel, 'status' => NotificationLog::STATUS_SENT])
                ->count();
            $failedChannel = NotificationLog::find()
                ->where(['channel' => $channel, 'status' => NotificationLog::STATUS_FAILED])
                ->count();
            
            $this->stdout("  {$channel}:\n");
            $this->stdout("    В очереди: {$queuedChannel}\n");
            $this->stdout("    Отправлено: {$sentChannel}\n");
            $this->stdout("    Ошибок: {$failedChannel}\n");
        }
        
        return ExitCode::OK;
    }
}