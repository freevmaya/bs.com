<?php
// FILE: .\commands\TestNotificationController.php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use app\models\Advertisement;
use app\models\SearchSubscription;
use app\components\notifications\channels\VkChannel;
use app\components\notifications\channels\TelegramChannel;

/**
 * Тестовый контроллер для отладки уведомлений и подписок
 * 
 * Использование:
 *   php yii test-notification/trigger 123
 *   php yii test-notification/trigger 123 1 (dry-run)
 *   php yii test-notification/list-subscribers 123
 *   php yii test-notification/check-subscription 123 1
 *   php yii test-notification/stats
 *   php yii test-notification/clear-logs
 *   php yii test-notification/show-subscription 1
 *   php yii test-notification/check-channels
 *   php yii test-notification/test-vk 123456789
 *   php yii test-notification/test-vk 123456789 "Привет!"
 *   php yii test-notification/check-vk-config
 *   php yii test-notification/test-email test@example.com
 *   php yii test-notification/test-telegram @username "Тестовое сообщение"
 *   php yii test-notification/check-telegram-user-raw FreeVmaya
 *   php yii test-notification/check-telegram-chat-ids
 *   php yii test-notification/diagnose-telegram
 *   php yii test-notification/check-telegram-user-exists FreeVmaya
 */
class TestNotificationController extends Controller
{
    /**
     * Триггерит событие создания нового объявления на основе существующего
     * 
     * @param int $id ID объявления-образца
     * @param int $dryRun 1 - только показать, что будет отправлено, без реальной отправки
     */
    public function actionTrigger($id, $dryRun = 0)
    {
        $advertisement = Advertisement::find()
            ->with(['images', 'glider', 'harness', 'device', 'user'])
            ->where(['id' => $id])
            ->one();

        if (!$advertisement) {
            $this->stderr("Объявление с ID {$id} не найдено\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("=== ТЕСТИРОВАНИЕ УВЕДОМЛЕНИЙ ===\n", Console::FG_YELLOW);
        $this->stdout("Объявление-образец: #{$advertisement->id} - {$advertisement->title}\n", Console::FG_CYAN);
        $this->stdout("Раздел: {$advertisement->getSectionLabel()}\n");
        $this->stdout("Тип: {$advertisement->getTypeLabel()}\n");
        $this->stdout("Цена: " . ($advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана') . "\n");
        $this->stdout("Город: " . ($advertisement->city ?: 'не указан') . "\n\n");

        // Получаем активные подписки для этого раздела
        $subscriptions = SearchSubscription::find()
            ->where([
                'section' => $advertisement->section,
                'is_active' => true,
            ])
            ->with('user')
            ->all();

        if (empty($subscriptions)) {
            $this->stdout("Нет активных подписок для раздела '{$advertisement->getSectionLabel()}'\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $this->stdout("Найдено активных подписок: " . count($subscriptions) . "\n\n", Console::FG_GREEN);

        $matchedCount = 0;
        $notifiedCount = 0;

        foreach ($subscriptions as $subscription) {
            $this->stdout("--- Подписка #{$subscription->id} ---\n", Console::FG_CYAN);
            $this->stdout("Пользователь: " . ($subscription->user ? $subscription->user->username : 'Удален') . "\n");
            $this->stdout("Параметры: " . $subscription->getDescription() . "\n");

            // Проверяем соответствие
            $matches = $subscription->matchesAdvertisement($advertisement);
            
            if ($matches) {
                $this->stdout("✅ ОБЪЯВЛЕНИЕ ПОДХОДИТ! Будет отправлено уведомление.\n", Console::FG_GREEN);
                $matchedCount++;

                // Проверяем, не отправляли ли уже уведомление
                $lastNotified = $subscription->last_notified_at;
                $createdAt = $advertisement->created_at;
                
                if ($lastNotified && $lastNotified > $createdAt - 3600) {
                    $this->stdout("⚠️ Уведомление уже отправлено в течение последнего часа (последняя отправка: " . date('Y-m-d H:i:s', $lastNotified) . ")\n", Console::FG_YELLOW);
                } else {
                    if ($dryRun) {
                        $this->stdout("🔔 [DRY-RUN] Будет отправлено уведомление пользователю {$subscription->user_id}\n", Console::FG_YELLOW);
                    } else {
                        // Отправляем уведомление и получаем результат
                        $success = $this->sendTestNotification($advertisement, $subscription);
                        
                        // Обновляем время последнего уведомления ТОЛЬКО если отправка успешна
                        if ($success) {
                            $notifiedCount++;
                            $subscription->last_notified_at = time();
                            $subscription->save(false);
                            $this->stdout("✅ Время последнего уведомления обновлено\n", Console::FG_GREEN);
                        } else {
                            $this->stdout("❌ Уведомление НЕ отправлено, время последнего уведомления НЕ обновлено\n", Console::FG_RED);
                        }
                    }
                }
            } else {
                $this->stdout("❌ Объявление НЕ подходит по параметрам подписки\n", Console::FG_RED);
            }
            $this->stdout("\n");
        }

        // Итоги
        $this->stdout("=== РЕЗУЛЬТАТЫ ===\n", Console::FG_YELLOW);
        $this->stdout("Всего подписок: " . count($subscriptions) . "\n");
        $this->stdout("Подходящих подписок: {$matchedCount}\n");
        
        if ($dryRun) {
            $this->stdout("Режим DRY-RUN: уведомления НЕ отправлены\n", Console::FG_YELLOW);
        } else {
            $this->stdout("Успешно отправлено уведомлений: {$notifiedCount}\n", Console::FG_GREEN);
        }

        // Показываем детали
        if ($matchedCount > 0 && !$dryRun) {
            $this->stdout("\nПроверьте логи в runtime/logs/app.log для подтверждения отправки.\n", Console::FG_CYAN);
            if (Yii::$app->has('mailer') && Yii::$app->mailer->useFileTransport) {
                $this->stdout("Письма сохранены в: " . Yii::getAlias(Yii::$app->mailer->fileTransportPath) . "\n", Console::FG_CYAN);
            }
        }

        return ExitCode::OK;
    }

    /**
     * Отправка тестового уведомления (использует существующий механизм)
     * @return bool true если уведомление успешно отправлено хотя бы по одному каналу
     */
    private function sendTestNotification($advertisement, $subscription)
    {
        $user = $subscription->user;
        if (!$user) {
            $this->stderr("Пользователь {$subscription->user_id} не найден\n", Console::FG_RED);
            return false;
        }

        $subject = "Тестовое уведомление: {$advertisement->title}";
        $message = $this->buildTestMessage($advertisement, $subscription);
        
        try {
            // Проверяем наличие notificationManager
            if (!Yii::$app->has('notificationManager')) {
                $this->stderr("NotificationManager не зарегистрирован. Добавьте его в config/console.php\n", Console::FG_RED);
                return false;
            }
            
            $manager = Yii::$app->notificationManager;
            
            // Проверяем доступные каналы для пользователя
            $channels = $manager->getChannels();
            $activeChannels = [];
            
            foreach ($channels as $channelKey => $channel) {
                if ($channel->isAvailable()) {
                    $activeChannels[] = $channelKey;
                }
            }
            
            if (empty($activeChannels)) {
                $this->stderr("Нет доступных каналов для отправки уведомлений\n", Console::FG_YELLOW);
                return false;
            }
            
            $this->stdout("Доступные каналы: " . implode(', ', $activeChannels) . "\n", Console::FG_CYAN);
            
            // Пытаемся отправить через все доступные каналы
            $result = $manager->sendToUser(
                $user->id,
                'search_subscription',
                $subject,
                $message,
                ['html_body' => $this->buildTestHtmlMessage($advertisement, $subscription)]
            );
            
            // Проверяем, было ли успешно отправлено хотя бы по одному каналу
            if ($result && is_array($result) && in_array(true, $result)) {
                $this->stdout("✅ Уведомление отправлено пользователю {$user->username} ({$user->email})\n", Console::FG_GREEN);
                Yii::info("Test notification sent to user {$user->id} for advertisement #{$advertisement->id}", 'test_notification');
                return true;
            } else {
                $this->stderr("❌ Не удалось отправить уведомление пользователю {$user->username}\n", Console::FG_RED);
                return false;
            }
        } catch (\Exception $e) {
            $this->stderr("Ошибка при отправке: " . $e->getMessage() . "\n", Console::FG_RED);
            Yii::error("Test notification error: " . $e->getMessage(), 'test_notification');
            return false;
        }
    }

    /**
     * Сборка текстового сообщения для теста (БЕЗ URL)
     */
    private function buildTestMessage($advertisement, $subscription)
    {
        $parts = [
            "🔔 ТЕСТОВОЕ УВЕДОМЛЕНИЕ",
            "",
            "По вашей подписке появилось новое объявление (тестовый триггер)!",
            "",
            "Заголовок: {$advertisement->title}",
            "Раздел: " . $advertisement->getSectionLabel(),
            "Тип: " . $advertisement->getTypeLabel(),
            "Цена: " . ($advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана'),
            "Город: " . ($advertisement->city ?: 'не указан'),
            "",
            "Ваши параметры подписки:",
            $subscription->getDescription(),
            "",
            "ID объявления: #{$advertisement->id}",
            "",
            "---",
            "Это тестовое уведомление. Для отключения подписки перейдите в настройки.",
        ];
        
        return implode("\n", $parts);
    }

    /**
     * Сборка HTML сообщения для теста (БЕЗ URL)
     */
    private function buildTestHtmlMessage($advertisement, $subscription)
    {
        $price = $advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана';
        $description = $subscription->getDescription();
        
        return "
            <html>
            <head>
            <style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ff9800; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .price { font-size: 24px; color: #d9534f; font-weight: bold; }
                .params { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .footer { text-align: center; padding: 15px; color: #6c757d; font-size: 12px; }
                .test-badge { background: #ff9800; color: white; padding: 4px 12px; border-radius: 4px; font-size: 12px; display: inline-block; }
            </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>🔔 Тестовое уведомление</h2>
                        <span class='test-badge'>ТЕСТ</span>
                    </div>
                    <div class='content'>
                        <p>По вашей подписке появилось новое объявление!</p>
                        <h3>{$advertisement->title}</h3>
                        <p><strong>Раздел:</strong> {$advertisement->getSectionLabel()}</p>
                        <p class='price'>{$price}</p>
                        <p><strong>Город:</strong> " . ($advertisement->city ?: 'не указан') . "</p>
                        <p><strong>Описание:</strong></p>
                        <p>" . nl2br($advertisement->description ?: 'не указано') . "</p>
                        <div class='params'>
                            <strong>Ваши параметры подписки:</strong><br>
                            {$description}
                        </div>
                        <p style='margin-top: 20px; font-size: 12px; color: #6c757d;'>
                            ID объявления: #{$advertisement->id}
                        </p>
                        <p style='margin-top: 10px; font-size: 12px; color: #6c757d;'>
                            Это тестовое уведомление.
                        </p>
                    </div>
                    <div class='footer'>
                        &copy; " . Yii::$app->name . " " . date('Y') . "
                    </div>
                </div>
            </body>
            </html>
        ";
    }

    /**
     * Список всех подписчиков для объявления
     * 
     * @param int $id ID объявления
     */
    public function actionListSubscribers($id)
    {
        $id = (int) $id;
        
        $advertisement = Advertisement::findOne($id);
        if (!$advertisement) {
            $this->stderr("Объявление с ID {$id} не найдено\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("=== ПОДПИСЧИКИ ДЛЯ ОБЪЯВЛЕНИЯ #{$id} ===\n", Console::FG_YELLOW);
        $this->stdout("Заголовок: {$advertisement->title}\n");
        $this->stdout("Раздел: {$advertisement->getSectionLabel()}\n\n");

        $subscriptions = SearchSubscription::find()
            ->where([
                'section' => $advertisement->section,
                'is_active' => true,
            ])
            ->with('user')
            ->all();

        if (empty($subscriptions)) {
            $this->stdout("Нет активных подписок для этого раздела\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $tableRows = [];
        foreach ($subscriptions as $subscription) {
            $matches = $subscription->matchesAdvertisement($advertisement);
            $tableRows[] = [
                'id' => $subscription->id,
                'user' => $subscription->user ? $subscription->user->username : 'Удален',
                'params' => $subscription->getDescription(),
                'matches' => $matches ? '✅' : '❌',
                'last_notified' => $subscription->last_notified_at ? date('Y-m-d H:i:s', $subscription->last_notified_at) : 'никогда',
            ];
        }

        // Выводим таблицу
        $this->stdout("┌──────┬──────────────────┬────────────────────────────────────┬─────────┬─────────────────────┐\n");
        $this->stdout("│ ID   │ Пользователь     │ Параметры                          │ Совпад. │ Последнее уведом.   │\n");
        $this->stdout("├──────┼──────────────────┼────────────────────────────────────┼─────────┼─────────────────────┤\n");
        foreach ($tableRows as $row) {
            $params = mb_substr($row['params'], 0, 30) . (mb_strlen($row['params']) > 30 ? '...' : '');
            $this->stdout(sprintf(
                "│ %-4s │ %-16s │ %-30s │ %-7s │ %-19s │\n",
                $row['id'],
                mb_substr($row['user'], 0, 16),
                $params,
                $row['matches'],
                $row['last_notified']
            ));
        }
        $this->stdout("└──────┴──────────────────┴────────────────────────────────────┴─────────┴─────────────────────┘\n");

        // Подсчет итогов
        $matchedCount = count(array_filter($tableRows, function($row) { return $row['matches'] === '✅'; }));
        $this->stdout("\nВсего подписок: " . count($tableRows) . ", подходящих: {$matchedCount}\n", Console::FG_CYAN);

        return ExitCode::OK;
    }

    /**
     * Проверка конкретной подписки
     * 
     * @param int $id ID объявления
     * @param int $subscriptionId ID подписки
     */
    public function actionCheckSubscription($id, $subscriptionId)
    {
        $id = (int) $id;
        $subscriptionId = (int) $subscriptionId;
        
        $advertisement = Advertisement::findOne($id);
        if (!$advertisement) {
            $this->stderr("Объявление с ID {$id} не найдено\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $subscription = SearchSubscription::findOne($subscriptionId);
        if (!$subscription) {
            $this->stderr("Подписка с ID {$subscriptionId} не найдена\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("=== ПРОВЕРКА ПОДПИСКИ ===\n", Console::FG_YELLOW);
        $this->stdout("Объявление: #{$id} - {$advertisement->title}\n");
        $this->stdout("Подписка: #{$subscriptionId}\n");
        $this->stdout("Пользователь: " . ($subscription->user ? $subscription->user->username : 'Удален') . "\n\n");

        $this->stdout("Параметры подписки:\n", Console::FG_CYAN);
        $params = $subscription->getParamsArray();
        foreach ($params as $key => $value) {
            $displayValue = is_array($value) ? implode(', ', $value) : $value;
            $this->stdout("  {$key}: {$displayValue}\n");
        }

        $this->stdout("\nПараметры объявления:\n", Console::FG_CYAN);
        $this->stdout("  section: {$advertisement->section}\n");
        $this->stdout("  type: {$advertisement->type}\n");
        $this->stdout("  price: {$advertisement->price}\n");
        $this->stdout("  city: {$advertisement->city}\n");
        
        if ($advertisement->glider) {
            $this->stdout("  glider:\n");
            $this->stdout("    model: {$advertisement->glider->model}\n");
            $this->stdout("    producer_id: {$advertisement->glider->producer_id}\n");
            $this->stdout("    certification_id: {$advertisement->glider->certification_id}\n");
            $this->stdout("    weight_min: {$advertisement->glider->weight_min}\n");
            $this->stdout("    weight_max: {$advertisement->glider->weight_max}\n");
            $this->stdout("    flight_time: {$advertisement->glider->flight_time}\n");
            $this->stdout("    condition: {$advertisement->glider->condition}\n");
        }

        $matches = $subscription->matchesAdvertisement($advertisement);
        $this->stdout("\nРезультат: " . ($matches ? "✅ СОВПАДАЕТ" : "❌ НЕ СОВПАДАЕТ") . "\n", 
            $matches ? Console::FG_GREEN : Console::FG_RED);

        return ExitCode::OK;
    }

    /**
     * Очистка логов тестовых уведомлений
     */
    public function actionClearLogs()
    {
        $logFile = Yii::getAlias('@runtime/logs/app.log');
        if (!file_exists($logFile)) {
            $this->stdout("Лог-файл не найден\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $content = file_get_contents($logFile);
        $lines = explode("\n", $content);
        $newLines = [];
        $removed = 0;

        foreach ($lines as $line) {
            if (strpos($line, 'test_notification') !== false || 
                strpos($line, 'search_subscription') !== false) {
                $removed++;
                continue;
            }
            $newLines[] = $line;
        }

        file_put_contents($logFile, implode("\n", $newLines));
        $this->stdout("Удалено {$removed} строк из лога\n", Console::FG_GREEN);
        
        return ExitCode::OK;
    }

    /**
     * Показать статистику подписок
     */
    public function actionStats()
    {
        $this->stdout("=== СТАТИСТИКА ПОДПИСОК ===\n", Console::FG_YELLOW);
        
        $total = SearchSubscription::find()->count();
        $active = SearchSubscription::find()->where(['is_active' => true])->count();
        $inactive = SearchSubscription::find()->where(['is_active' => false])->count();
        
        $sell = SearchSubscription::find()->where(['section' => 'sell', 'is_active' => true])->count();
        $buy = SearchSubscription::find()->where(['section' => 'buy', 'is_active' => true])->count();
        
        $this->stdout("Всего подписок: {$total}\n");
        $this->stdout("Активных: {$active}\n");
        $this->stdout("Неактивных: {$inactive}\n\n");
        
        $this->stdout("Активные по разделам:\n", Console::FG_CYAN);
        $this->stdout("  Продам: {$sell}\n");
        $this->stdout("  Куплю: {$buy}\n");
        
        // Распределение по типам
        $this->stdout("\nРаспределение по типам:\n", Console::FG_CYAN);
        $types = ['normal', 'glider', 'harness', 'device'];
        foreach ($types as $type) {
            $count = SearchSubscription::find()
                ->where(['is_active' => true])
                ->andWhere(['like', 'params', '"type":"' . $type . '"'])
                ->count();
            $this->stdout("  {$type}: {$count}\n");
        }
        
        // Последние созданные подписки
        $this->stdout("\nПоследние 5 созданных подписок:\n", Console::FG_CYAN);
        $recent = SearchSubscription::find()
            ->orderBy(['created_at' => SORT_DESC])
            ->limit(5)
            ->all();
        
        foreach ($recent as $sub) {
            $this->stdout(sprintf(
                "  #%d | %s | %s | %s\n",
                $sub->id,
                $sub->user ? $sub->user->username : 'Удален',
                $sub->section === 'sell' ? 'Продам' : 'Куплю',
                $sub->is_active ? 'активна' : 'неактивна'
            ));
        }
        
        return ExitCode::OK;
    }

    /**
     * Просмотр сырых данных подписки
     * 
     * @param int $id ID подписки
     */
    public function actionShowSubscription($id)
    {
        $subscription = SearchSubscription::findOne($id);
        if (!$subscription) {
            $this->stderr("Подписка с ID {$id} не найдена\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("=== ПОДПИСКА #{$id} ===\n", Console::FG_YELLOW);
        $this->stdout("Сырые данные (params):\n", Console::FG_CYAN);
        $this->stdout($subscription->params . "\n\n");
        
        $this->stdout("Декодированные параметры:\n", Console::FG_CYAN);
        $params = json_decode($subscription->params, true);
        if (is_array($params)) {
            foreach ($params as $key => $value) {
                if (is_array($value)) {
                    $this->stdout("  {$key}: " . implode(', ', $value) . "\n");
                } else {
                    $this->stdout("  {$key}: {$value}\n");
                }
            }
        } else {
            $this->stdout("  (не удалось декодировать JSON)\n", Console::FG_RED);
        }
        
        $this->stdout("\nНормализованные параметры:\n", Console::FG_CYAN);
        $normalized = $subscription->getParamsArray();
        foreach ($normalized as $key => $value) {
            if (is_array($value)) {
                $this->stdout("  {$key}: " . implode(', ', $value) . "\n");
            } else {
                $this->stdout("  {$key}: {$value}\n");
            }
        }
        
        $this->stdout("\nОписание: " . $subscription->getDescription() . "\n", Console::FG_GREEN);
        
        return ExitCode::OK;
    }

    /**
     * Проверка доступности каналов уведомлений
     */
    public function actionCheckChannels()
    {
        $this->stdout("=== ПРОВЕРКА КАНАЛОВ УВЕДОМЛЕНИЙ ===\n", Console::FG_YELLOW);
        
        // Проверяем наличие notificationManager
        if (!Yii::$app->has('notificationManager')) {
            $this->stdout("❌ NotificationManager не зарегистрирован\n", Console::FG_RED);
            $this->stdout("Добавьте в config/console.php:\n", Console::FG_YELLOW);
            $this->stdout("  'components' => [\n");
            $this->stdout("      'notificationManager' => [\n");
            $this->stdout("          'class' => 'app\\components\\notifications\\NotificationManager',\n");
            $this->stdout("      ],\n");
            $this->stdout("  ],\n");
            return ExitCode::OK;
        }
        
        $manager = Yii::$app->notificationManager;
        $channels = $manager->getChannels();
        
        foreach ($channels as $key => $channel) {
            $available = $channel->isAvailable();
            $status = $available ? Console::FG_GREEN : Console::FG_RED;
            $statusText = $available ? '✅ Доступен' : '❌ Недоступен';
            
            $this->stdout(sprintf(
                "  %-10s : %s\n",
                $key,
                $statusText
            ), $status);
            
            if (!$available) {
                $this->stdout("    Причина: необходима настройка\n", Console::FG_YELLOW);
            }
        }
        
        // Проверяем пользователей с заполненными контактами
        $this->stdout("\n--- Пользователи с контактами ---\n", Console::FG_CYAN);
        
        $usersWithEmail = \app\models\User::find()->where(['not', ['email' => null]])->andWhere(['!=', 'email', ''])->count();
        $usersWithPhone = \app\models\User::find()->where(['not', ['phone' => null]])->andWhere(['!=', 'phone', ''])->count();
        $usersWithVk = \app\models\User::find()->where(['not', ['vk_profile_url' => null]])->andWhere(['!=', 'vk_profile_url', ''])->count();
        $usersWithTelegram = \app\models\User::find()->where(['not', ['telegram' => null]])->andWhere(['!=', 'telegram', ''])->count();
        $usersWithTelegramChatId = \app\models\User::find()->where(['not', ['telegram_chat_id' => null]])->andWhere(['!=', 'telegram_chat_id', ''])->count();
        
        $this->stdout("Пользователей с email: {$usersWithEmail}\n");
        $this->stdout("Пользователей с телефоном: {$usersWithPhone}\n");
        $this->stdout("Пользователей с VK: {$usersWithVk}\n");
        $this->stdout("Пользователей с Telegram: {$usersWithTelegram}\n");
        $this->stdout("Пользователей с Telegram Chat ID: {$usersWithTelegramChatId}\n");
        
        return ExitCode::OK;
    }

    /**
     * Тестовая отправка email напрямую
     * 
     * @param string $to Email получателя
     */
    public function actionTestEmail($to = 'test@example.com')
    {
        $this->stdout("=== ТЕСТ EMAIL ===\n", Console::FG_YELLOW);
        $this->stdout("Получатель: {$to}\n");
        
        try {
            // Проверяем, что mailer зарегистрирован
            if (!Yii::$app->has('mailer')) {
                $this->stderr("❌ Mailer не зарегистрирован в консольном приложении\n", Console::FG_RED);
                $this->stdout("Добавьте mailer в config/console.php\n", Console::FG_YELLOW);
                return ExitCode::UNSPECIFIED_ERROR;
            }

            $senderEmail = Yii::$app->params['senderEmail'] ?? 'freevmaya@yandex.ru';
            $senderName = Yii::$app->params['senderName'] ?? 'parasell.vmaya.ru';
            
            $this->stdout("Отправитель: {$senderEmail} ({$senderName})\n", Console::FG_CYAN);
            
            // Пробуем отправить через mailer с указанием From
            $message = Yii::$app->mailer->compose()
                ->setFrom([$senderEmail => $senderName])
                ->setTo('fwadim@mail.ru')
                ->setSubject('Yii Mailer Test - ' . date('Y-m-d H:i:s'))
                ->setTextBody('This is a test email from Yii mailer.' . "\n")
                ->setTextBody('Sent at ' . date('Y-m-d H:i:s'));
            
            // Проверяем, что сообщение создано
            if (!$message) {
                $this->stderr("❌ Не удалось создать сообщение\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            
            $result = $message->send();
            
            if ($result) {
                $this->stdout("✅ Email отправлен успешно на {$to}\n", Console::FG_GREEN);
            } else {
                $this->stderr("❌ Не удалось отправить email\n", Console::FG_RED);
            }
            
            // Проверяем fileTransport
            if (Yii::$app->mailer->useFileTransport) {
                $this->stdout("\nFile transport включен. Проверьте runtime/mail/\n", Console::FG_YELLOW);
                $mailPath = Yii::getAlias('@runtime/mail');
                if (is_dir($mailPath)) {
                    $files = glob($mailPath . '/*.eml');
                    $this->stdout("Найдено " . count($files) . " email файлов\n", Console::FG_CYAN);
                    if (!empty($files)) {
                        $lastFile = end($files);
                        $this->stdout("Последний файл: " . basename($lastFile) . "\n", Console::FG_CYAN);
                        $this->stdout("Содержимое:\n", Console::FG_YELLOW);
                        $this->stdout(str_repeat('-', 50) . "\n");
                        $this->stdout(file_get_contents($lastFile) . "\n");
                        $this->stdout(str_repeat('-', 50) . "\n");
                    }
                } else {
                    $this->stdout("Папка runtime/mail не существует\n", Console::FG_YELLOW);
                    mkdir($mailPath, 0777, true);
                    $this->stdout("Папка создана\n", Console::FG_GREEN);
                }
            }
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            $this->stderr($e->getTraceAsString() . "\n", Console::FG_RED);
        }
        
        return ExitCode::OK;
    }

    /**
     * Тестовая отправка сообщения в VK
     * 
     * @param int $userId ID пользователя в VK
     * @param string $message Текст сообщения (опционально)
     */
    public function actionTestVk($userId, $message = 'Тестовое сообщение от сайта BS.com')
    {
        $this->stdout("=== ТЕСТ ОТПРАВКИ В VK ===\n", Console::FG_YELLOW);
        $this->stdout("Получатель VK ID: {$userId}\n");
        $this->stdout("Сообщение: {$message}\n\n");
        
        // Проверяем настройки
        $accessToken = Yii::$app->params['vk_access_token'] ?? null;
        if (!$accessToken || $accessToken === 'ваш_токен_доступа_от_сообщества_или_пользователя') {
            $this->stderr("❌ VK access_token не настроен в config/params.php\n", Console::FG_RED);
            $this->stdout("Добавьте параметр 'vk_access_token' в config/params.php\n");
            $this->stdout("Как получить токен:\n");
            $this->stdout("  1. Перейдите в настройки сообщества\n");
            $this->stdout("  2. Найдите раздел 'Работа с API'\n");
            $this->stdout("  3. Создайте ключ доступа с правом 'Сообщения сообщества'\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("✅ VK access_token найден\n", Console::FG_GREEN);
        
        // Проверяем access_token
        $this->stdout("\nПроверка токена...\n", Console::FG_CYAN);
        $checkResult = $this->checkVkToken($accessToken);
        if (!$checkResult['success']) {
            $this->stderr("❌ Токен недействителен: " . $checkResult['error'] . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        $this->stdout("✅ Токен действителен\n", Console::FG_GREEN);
        
        // Создаем канал VK
        $vkChannel = new VkChannel($accessToken);
        
        // Отправляем сообщение
        $this->stdout("\nОтправка сообщения...\n", Console::FG_CYAN);
        
        try {
            $result = $vkChannel->send(
                $userId,           // Получатель
                'Тест VK',         // Subject (не используется в VK)
                $message,          // Текст сообщения
                []                 // Дополнительные опции
            );
            
            if ($result) {
                $this->stdout("✅ Сообщение успешно отправлено пользователю VK ID: {$userId}\n", Console::FG_GREEN);
            } else {
                $this->stderr("❌ Не удалось отправить сообщение\n", Console::FG_RED);
                $this->stdout("\nВозможные причины:\n");
                $this->stdout("  1. Пользователь не подписан на сообщения сообщества\n");
                $this->stdout("  2. У пользователя закрыт доступ к сообщениям\n");
                $this->stdout("  3. Превышен лимит запросов к VK API\n");
            }
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            $this->stderr($e->getTraceAsString() . "\n", Console::FG_RED);
        }
        
        return ExitCode::OK;
    }

    /**
     * Проверка настроек VK
     */
    public function actionCheckVkConfig()
    {
        $this->stdout("=== ПРОВЕРКА НАСТРОЕК VK ===\n", Console::FG_YELLOW);
        
        $accessToken = Yii::$app->params['vk_access_token'] ?? null;
        $groupId = Yii::$app->params['vk_group_id'] ?? null;
        $confirmToken = Yii::$app->params['vk_confirm_token'] ?? null;
        
        $this->stdout("Access token: " . ($accessToken ? '✅ Установлен' : '❌ Не установлен') . "\n");
        if ($accessToken) {
            $this->stdout("  Токен (первые 10 символов): " . substr($accessToken, 0, 10) . "...\n");
            
            // Проверяем токен
            $this->stdout("\nПроверка токена...\n", Console::FG_CYAN);
            $checkResult = $this->checkVkToken($accessToken);
            if ($checkResult['success']) {
                $this->stdout("✅ Токен действителен\n", Console::FG_GREEN);
                if (isset($checkResult['group_name'])) {
                    $this->stdout("  Группа: {$checkResult['group_name']} (ID: {$checkResult['group_id']})\n", Console::FG_CYAN);
                }
            } else {
                $this->stderr("❌ Токен недействителен: " . $checkResult['error'] . "\n", Console::FG_RED);
            }
        }
        
        $this->stdout("\nGroup ID: " . ($groupId ? '✅ Установлен' : '❌ Не установлен') . "\n");
        $this->stdout("Confirm token: " . ($confirmToken ? '✅ Установлен' : '❌ Не установлен') . "\n");
        
        $this->stdout("\nРекомендации по настройке:\n");
        $this->stdout("  1. Создайте сообщество VK или используйте существующее\n");
        $this->stdout("  2. В настройках сообщества включите 'Сообщения сообщества'\n");
        $this->stdout("  3. В разделе 'Работа с API' создайте ключ доступа с правом 'Сообщения сообщества'\n");
        $this->stdout("  4. Скопируйте ключ в config/params.php как 'vk_access_token'\n");
        $this->stdout("  5. Скопируйте ID группы в config/params.php как 'vk_group_id'\n");
        
        return ExitCode::OK;
    }

    /**
     * Проверка VK токена через API
     * 
     * @param string $accessToken
     * @return array
     */
    private function checkVkToken($accessToken)
    {
        try {
            $client = new \yii\httpclient\Client();
            $response = $client->get('https://api.vk.com/method/groups.getById', [
                'access_token' => $accessToken,
                'v' => '5.131',
            ])->send();
            
            if ($response->isOk) {
                $data = $response->data;
                if (isset($data['response']) && !empty($data['response'])) {
                    $group = $data['response'][0];
                    return [
                        'success' => true,
                        'group_id' => $group['id'],
                        'group_name' => $group['name'],
                    ];
                }
                if (isset($data['error'])) {
                    return [
                        'success' => false,
                        'error' => $data['error']['error_msg'] ?? 'Неизвестная ошибка API',
                    ];
                }
            }
            return ['success' => false, 'error' => 'Ошибка соединения с VK API'];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Тестовая отправка сообщения в VK через прямой API запрос (без NotificationManager)
     * 
     * @param int $userId ID пользователя в VK
     * @param string $message Текст сообщения (опционально)
     */
    public function actionTestVkDirect($userId, $message = 'Тестовое сообщение от сайта BS.com')
    {
        $this->stdout("=== ТЕСТ ОТПРАВКИ В VK (прямой API) ===\n", Console::FG_YELLOW);
        $this->stdout("Получатель VK ID: {$userId}\n");
        $this->stdout("Сообщение: {$message}\n\n");
        
        $accessToken = Yii::$app->params['vk_access_token'] ?? null;
        if (!$accessToken) {
            $this->stderr("❌ VK access_token не настроен\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("Отправка через прямой API...\n", Console::FG_CYAN);
        
        try {
            $client = new \yii\httpclient\Client();
            $response = $client->post('https://api.vk.com/method/messages.send', [
                'user_id' => $userId,
                'message' => $message,
                'random_id' => rand(1, 1000000),
                'v' => '5.131',
                'access_token' => $accessToken,
            ])->send();
            
            if ($response->isOk) {
                $data = $response->data;
                if (isset($data['response'])) {
                    $this->stdout("✅ Сообщение отправлено! ID сообщения: {$data['response']}\n", Console::FG_GREEN);
                } elseif (isset($data['error'])) {
                    $this->stderr("❌ Ошибка VK API: " . ($data['error']['error_msg'] ?? 'Неизвестная ошибка') . "\n", Console::FG_RED);
                    $this->stdout("Код ошибки: " . ($data['error']['error_code'] ?? '') . "\n");
                    
                    // Расшифровка ошибок
                    $errorCode = $data['error']['error_code'] ?? 0;
                    $this->stdout("\nРасшифровка ошибки:\n");
                    switch ($errorCode) {
                        case 7:
                            $this->stdout("  - Нет прав на отправку сообщений этому пользователю\n");
                            break;
                        case 14:
                            $this->stdout("  - Требуется капча\n");
                            break;
                        case 15:
                            $this->stdout("  - Пользователь заблокировал сообщения сообщества\n");
                            break;
                        case 200:
                            $this->stdout("  - Доступ запрещен\n");
                            break;
                        case 901:
                            $this->stdout("  - Пользователь не подписан на сообщения сообщества\n");
                            break;
                        default:
                            $this->stdout("  - См. документацию VK API\n");
                    }
                }
            } else {
                $this->stderr("❌ Ошибка HTTP: " . $response->statusCode . "\n", Console::FG_RED);
            }
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
        }
        
        return ExitCode::OK;
    }

    // ============================================================
    // МЕТОДЫ ДЛЯ TELEGRAM
    // ============================================================

    /**
     * Тестовая отправка в Telegram
     * 
     * @param string $to Username получателя (например: @username или просто username)
     * @param string $message Текст сообщения
     */
    public function actionTestTelegram($to, $message = 'Тестовое сообщение от сайта BS.com')
    {
        $this->stdout("=== ТЕСТ ОТПРАВКИ В TELEGRAM ===\n", Console::FG_YELLOW);
        $this->stdout("Получатель: {$to}\n");
        $this->stdout("Сообщение: {$message}\n\n");
        
        // Проверяем настройки
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        if (!$botToken || $botToken === '123456789:ABCdefGHIjklMNOpqrsTUVwxyz') {
            $this->stderr("❌ Telegram bot token не настроен в config/params.php\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("✅ Telegram bot token найден\n", Console::FG_GREEN);
        
        // Создаем канал
        $channel = new \app\components\notifications\channels\TelegramChannel();
        
        // Получаем username бота
        $botInfo = $channel->getBotInfo();
        $botUsername = $botInfo['username'] ?? 'неизвестно';
        $this->stdout("Бот: @" . $botUsername . "\n", Console::FG_CYAN);
        
        $chatId = ltrim($to, '@');
        
        // ===== НОВАЯ ЛОГИКА: Ищем пользователя в БД =====
        $user = \app\models\User::find()
            ->where(['username' => $chatId])
            ->orWhere(['telegram' => $chatId])
            ->orWhere(['telegram' => '@' . $chatId])
            ->one();
        
        if (!$user) {
            $this->stderr("❌ Пользователь @{$chatId} не найден в базе данных\n", Console::FG_RED);
            $this->stdout("\nУбедитесь, что пользователь зарегистрирован и указал Telegram в профиле.\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("✅ Пользователь найден в БД: {$user->username}\n", Console::FG_GREEN);
        
        // Проверяем, есть ли chat_id
        if (empty($user->telegram_chat_id)) {
            $this->stderr("⚠️ У пользователя @{$chatId} нет сохраненного chat_id\n", Console::FG_YELLOW);
            $this->stdout("\nЧтобы получить chat_id:\n");
            $this->stdout("  1. Попросите пользователя отправить сообщение боту @" . $botUsername . "\n");
            $this->stdout("  2. После этого chat_id будет сохранен автоматически\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("✅ Найден chat_id: {$user->telegram_chat_id}\n", Console::FG_GREEN);
        
        // Отправляем сообщение
        $this->stdout("\nОтправка сообщения...\n", Console::FG_CYAN);
        
        $subject = 'Тест Telegram';
        $result = $channel->send(
            $user->telegram_chat_id,  // Используем сохраненный chat_id
            $subject,
            $message . "\n\nОтправлено: " . date('Y-m-d H:i:s')
        );
        
        if ($result) {
            $this->stdout("✅ Сообщение успешно отправлено пользователю @{$chatId}\n", Console::FG_GREEN);
            $this->stdout("   Chat ID: {$user->telegram_chat_id}\n");
        } else {
            $this->stderr("❌ Не удалось отправить сообщение\n", Console::FG_RED);
            $this->stdout("\nВозможные причины:\n");
            $this->stdout("  1. Пользователь не начал диалог с ботом\n");
            $this->stdout("  2. Пользователь заблокировал бота\n");
            $this->stdout("  3. Неправильный chat_id\n");
            $this->stdout("  4. Превышен лимит запросов\n");
        }
        
        return ExitCode::OK;
    }

    /**
     * Прямая проверка пользователя через Telegram API
     * 
     * @param string $username Username пользователя
     */
    public function actionCheckTelegramUserRaw($username)
    {
        $this->stdout("=== ПРЯМАЯ ПРОВЕРКА ПОЛЬЗОВАТЕЛЯ В TELEGRAM ===\n", Console::FG_YELLOW);
        $this->stdout("Пользователь: @{$username}\n\n");
        
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        if (!$botToken) {
            $this->stderr("❌ Telegram bot token не настроен\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("Токен бота: " . substr($botToken, 0, 15) . "...\n\n");
        
        $chatId = ltrim($username, '@');
        
        // 1. Проверяем через getChat
        $this->stdout("1. Проверка через getChat...\n", Console::FG_CYAN);
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                'https://api.telegram.org/bot' . $botToken . '/getChat',
                ['chat_id' => $chatId]
            )->send();
            
            $this->stdout("   Статус: " . $response->statusCode . "\n");
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $chat = $response->data['result'];
                $this->stdout("   ✅ Пользователь найден:\n", Console::FG_GREEN);
                $this->stdout("      ID: {$chat['id']}\n");
                $this->stdout("      Username: @" . ($chat['username'] ?? 'не указан') . "\n");
                $this->stdout("      Имя: " . ($chat['first_name'] ?? '') . " " . ($chat['last_name'] ?? '') . "\n");
                $this->stdout("      Тип: {$chat['type']}\n");
            } else {
                $error = isset($response->data['description']) ? $response->data['description'] : 'Unknown error';
                $this->stdout("   ❌ Ошибка: {$error}\n", Console::FG_RED);
            }
        } catch (\Exception $e) {
            $this->stdout("   ❌ Исключение: " . $e->getMessage() . "\n", Console::FG_RED);
        }
        
        // 2. Проверяем через getChatMember (если пользователь подписан на бота)
        $this->stdout("\n2. Проверка через getChatMember (только если пользователь подписан)...\n", Console::FG_CYAN);
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                'https://api.telegram.org/bot' . $botToken . '/getChatMember',
                ['chat_id' => $chatId, 'user_id' => $chatId]
            )->send();
            
            $this->stdout("   Статус: " . $response->statusCode . "\n");
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $member = $response->data['result'];
                $this->stdout("   ✅ Пользователь является членом чата:\n", Console::FG_GREEN);
                $this->stdout("      Статус: " . ($member['status'] ?? 'unknown') . "\n");
            } else {
                $error = isset($response->data['description']) ? $response->data['description'] : 'Unknown error';
                $this->stdout("   ⚠️ " . ($error === 'Bad Request: user not found' ? 'Пользователь не является участником чата' : $error) . "\n", Console::FG_YELLOW);
            }
        } catch (\Exception $e) {
            $this->stdout("   ❌ Исключение: " . $e->getMessage() . "\n", Console::FG_RED);
        }
        
        // 3. Пробуем отправить сообщение через прямой API
        $this->stdout("\n3. Попытка прямой отправки сообщения...\n", Console::FG_CYAN);
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->post(
                'https://api.telegram.org/bot' . $botToken . '/sendMessage',
                [
                    'chat_id' => $chatId,
                    'text' => "🔍 Тестовое сообщение для диагностики\nОтправлено: " . date('Y-m-d H:i:s'),
                ]
            )->send();
            
            $this->stdout("   Статус: " . $response->statusCode . "\n");
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $this->stdout("   ✅ Сообщение отправлено успешно!\n", Console::FG_GREEN);
            } else {
                $error = isset($response->data['description']) ? $response->data['description'] : 'Unknown error';
                $this->stdout("   ❌ Ошибка: {$error}\n", Console::FG_RED);
                
                // Расшифровка ошибок
                if (strpos($error, 'chat not found') !== false) {
                    $this->stdout("\n   📌 Расшифровка: Пользователь @{$chatId} не найден.\n", Console::FG_YELLOW);
                    $this->stdout("   Возможные причины:\n");
                    $this->stdout("   - Пользователь не существует\n");
                    $this->stdout("   - Пользователь имеет приватный аккаунт\n");
                    $this->stdout("   - Неправильно указан username\n");
                    $this->stdout("   - Пользователь не начал диалог с ботом\n");
                } elseif (strpos($error, 'bot was blocked') !== false) {
                    $this->stdout("\n   📌 Расшифровка: Пользователь заблокировал бота.\n", Console::FG_YELLOW);
                } elseif (strpos($error, 'user is deactivated') !== false) {
                    $this->stdout("\n   📌 Расшифровка: Пользователь деактивирован.\n", Console::FG_YELLOW);
                }
            }
        } catch (\Exception $e) {
            $this->stdout("   ❌ Исключение: " . $e->getMessage() . "\n", Console::FG_RED);
        }
        
        // 4. Рекомендации
        $this->stdout("\n=== РЕКОМЕНДАЦИИ ===\n", Console::FG_YELLOW);
        $this->stdout("1. Убедитесь, что пользователь @{$chatId} существует в Telegram\n");
        $this->stdout("2. Попросите пользователя:\n");
        $this->stdout("   - Найти бота @Parasell_Bot в Telegram\n");
        $this->stdout("   - Нажать кнопку 'Старт'\n");
        $this->stdout("   - Отправить любое сообщение боту\n");
        $this->stdout("3. Если пользователь существует, используйте его числовой ID вместо username\n");
        
        return ExitCode::OK;
    }

    /**
     * Проверка chat_id пользователей
     */
    public function actionCheckTelegramChatIds()
    {
        $this->stdout("=== ПРОВЕРКА TELEGRAM CHAT ID ===\n", Console::FG_YELLOW);
        
        $users = \app\models\User::find()
            ->where(['not', ['telegram' => null]])
            ->andWhere(['!=', 'telegram', ''])
            ->all();
        
        if (empty($users)) {
            $this->stdout("Нет пользователей с указанным Telegram\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }
        
        $this->stdout("Найдено пользователей: " . count($users) . "\n\n");
        
        $hasChatId = 0;
        $noChatId = 0;
        
        foreach ($users as $user) {
            $status = !empty($user->telegram_chat_id) ? '✅' : '❌';
            $chatIdDisplay = $user->telegram_chat_id ?? 'не установлен';
            
            $this->stdout("{$status} @{$user->telegram} ({$user->username}) - Chat ID: {$chatIdDisplay}\n");
            
            if (!empty($user->telegram_chat_id)) {
                $hasChatId++;
            } else {
                $noChatId++;
            }
        }
        
        $this->stdout("\n=== ИТОГИ ===\n", Console::FG_YELLOW);
        $this->stdout("С chat_id: {$hasChatId}\n");
        $this->stdout("Без chat_id: {$noChatId}\n");
        
        if ($noChatId > 0) {
            $this->stdout("\n⚠️ Пользователи без chat_id:\n");
            $this->stdout("1. Попросите их отправить сообщение боту @Parasell_Bot\n");
            $this->stdout("2. После этого chat_id будет сохранен автоматически\n");
        }
        
        return ExitCode::OK;
    }

    /**
     * Полная диагностика бота
     */
    public function actionDiagnoseTelegram()
    {
        $this->stdout("=== ДИАГНОСТИКА TELEGRAM БОТА ===\n", Console::FG_YELLOW);
        
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        
        if (!$botToken) {
            $this->stderr("❌ Токен не настроен в config/params.php\n", Console::FG_RED);
            $this->stdout("\nДобавьте в config/params.php:\n");
            $this->stdout("'telegram_bot_token' => 'ваш_токен_от_BotFather',\n");
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $this->stdout("✅ Токен найден: " . substr($botToken, 0, 15) . "...\n\n");
        
        // 1. Проверка бота через getMe
        $this->stdout("1. Проверка бота (getMe)...\n", Console::FG_CYAN);
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                'https://api.telegram.org/bot' . $botToken . '/getMe'
            )->send();
            
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $bot = $response->data['result'];
                $this->stdout("   ✅ Бот активен:\n", Console::FG_GREEN);
                $this->stdout("      ID: {$bot['id']}\n");
                $this->stdout("      Username: @{$bot['username']}\n");
                $this->stdout("      Имя: {$bot['first_name']}\n");
                $this->stdout("      Может ли бот получать сообщения: " . ($bot['can_join_groups'] ? 'Да' : 'Нет') . "\n");
            } else {
                $this->stderr("   ❌ Бот не активен: " . ($response->data['description'] ?? 'Unknown error') . "\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Exception $e) {
            $this->stderr("   ❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        // 2. Проверка прав бота
        $this->stdout("\n2. Проверка прав бота (getMyCommands)...\n", Console::FG_CYAN);
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                'https://api.telegram.org/bot' . $botToken . '/getMyCommands'
            )->send();
            
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $commands = $response->data['result'] ?? [];
                $this->stdout("   ✅ Команды бота (" . count($commands) . "):\n", Console::FG_GREEN);
                foreach ($commands as $cmd) {
                    $this->stdout("      /{$cmd['command']} - {$cmd['description']}\n");
                }
            } else {
                $this->stdout("   ⚠️ Не удалось получить команды: " . ($response->data['description'] ?? 'Unknown') . "\n", Console::FG_YELLOW);
            }
        } catch (\Exception $e) {
            $this->stdout("   ⚠️ Ошибка: " . $e->getMessage() . "\n", Console::FG_YELLOW);
        }
        
        // 3. Проверка пользователей с Telegram в БД
        $this->stdout("\n3. Пользователи с Telegram в базе данных...\n", Console::FG_CYAN);
        $users = \app\models\User::find()
            ->where(['not', ['telegram' => null]])
            ->andWhere(['!=', 'telegram', ''])
            ->all();
        
        if (empty($users)) {
            $this->stdout("   Нет пользователей с указанным Telegram\n", Console::FG_YELLOW);
        } else {
            $this->stdout("   Найдено пользователей: " . count($users) . "\n", Console::FG_GREEN);
            foreach ($users as $user) {
                $hasChatId = !empty($user->telegram_chat_id) ? '✅' : '❌';
                $this->stdout("      {$hasChatId} @{$user->telegram} ({$user->username})\n");
            }
        }
        
        // 4. Рекомендации
        $this->stdout("\n=== РЕКОМЕНДАЦИИ ===\n", Console::FG_YELLOW);
        $this->stdout("1. Убедитесь, что бот @Parasell_Bot активен\n");
        $this->stdout("2. Убедитесь, что пользователи подписались на бота\n");
        $this->stdout("3. Для отправки сообщений используйте:\n");
        $this->stdout("   - Username: @username (если пользователь публичный)\n");
        $this->stdout("   - Chat ID: число (если пользователь приватный)\n");
        $this->stdout("4. Проверьте, что пользователи отправили сообщение боту\n");
        
        return ExitCode::OK;
    }

    /**
     * Проверка существования пользователя в Telegram
     * 
     * @param string $username Username пользователя
     */
    public function actionCheckTelegramUserExists($username)
    {
        $this->stdout("=== ПРОВЕРКА ПОЛЬЗОВАТЕЛЯ В TELEGRAM ===\n", Console::FG_YELLOW);
        $this->stdout("Пользователь: @{$username}\n\n");
        
        $botToken = Yii::$app->params['telegram_bot_token'] ?? null;
        if (!$botToken) {
            $this->stderr("❌ Telegram bot token не настроен\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        
        $channel = new TelegramChannel();
        $chatId = ltrim($username, '@');
        
        // Пробуем получить информацию о чате
        try {
            $client = new \yii\httpclient\Client(['transport' => 'yii\httpclient\CurlTransport']);
            $response = $client->get(
                'https://api.telegram.org/bot' . $botToken . '/getChat',
                ['chat_id' => $chatId]
            )->send();
            
            if ($response->isOk && isset($response->data['ok']) && $response->data['ok'] === true) {
                $chat = $response->data['result'];
                $this->stdout("✅ Пользователь найден:\n", Console::FG_GREEN);
                $this->stdout("  ID: {$chat['id']}\n");
                $this->stdout("  Username: @" . ($chat['username'] ?? 'не указан') . "\n");
                $this->stdout("  Имя: " . ($chat['first_name'] ?? '') . " " . ($chat['last_name'] ?? '') . "\n");
                $this->stdout("  Тип: {$chat['type']}\n");
                return ExitCode::OK;
            } else {
                $error = isset($response->data['description']) ? $response->data['description'] : 'Unknown error';
                $this->stderr("❌ Пользователь не найден: {$error}\n", Console::FG_RED);
                $this->stdout("\nВозможные причины:\n");
                $this->stdout("  1. Пользователь не существует\n");
                $this->stdout("  2. Пользователь с таким username не найден\n");
                $this->stdout("  3. Пользователь имеет приватный аккаунт\n");
                return ExitCode::UNSPECIFIED_ERROR;
            }
        } catch (\Exception $e) {
            $this->stderr("❌ Ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}