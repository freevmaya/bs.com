<?php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use app\models\Advertisement;
use app\models\SearchSubscription;
use app\components\notifications\channels\VkChannel;

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
        
        $this->stdout("Пользователей с email: {$usersWithEmail}\n");
        $this->stdout("Пользователей с телефоном: {$usersWithPhone}\n");
        $this->stdout("Пользователей с VK: {$usersWithVk}\n");
        
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
            if (!Yii::$app->has('mailer')) {
                $this->stderr("❌ Mailer не зарегистрирован в консольном приложении\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            
            $senderEmail = Yii::$app->params['senderEmail'] ?? 'freevmaya@yandex.ru';
            $senderName = Yii::$app->params['senderName'] ?? 'parasell.vmaya.ru';
            
            $this->stdout("Отправитель: {$senderEmail} ({$senderName})\n", Console::FG_CYAN);
            
            // Проверяем, что пароль установлен
            $smtpPassword = Yii::$app->params['smtp_password'] ?? '';
            if (empty($smtpPassword)) {
                $this->stderr("❌ Пароль SMTP не установлен в config/params.php\n", Console::FG_RED);
                $this->stdout("   Добавьте 'smtp_password' => 'ПАРОЛЬ_ПРИЛОЖЕНИЯ' в config/params.php\n", Console::FG_YELLOW);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->stdout("Пароль SMTP: " . str_repeat('*', strlen($smtpPassword)) . " (длина: " . strlen($smtpPassword) . ")\n", Console::FG_CYAN);
            
            // Проверяем настройки mailer
            $mailer = Yii::$app->mailer;
            $this->stdout("UseFileTransport: " . ($mailer->useFileTransport ? 'true' : 'false') . "\n", Console::FG_CYAN);
            
            // Проверяем транспорт
            if (method_exists($mailer, 'getTransport')) {
                $transport = $mailer->getTransport();
                $this->stdout("Transport class: " . get_class($transport) . "\n", Console::FG_CYAN);
            }
            
            $this->stdout("\nПроверка конфигурации mailer...\n", Console::FG_CYAN);
            
            // Создаем сообщение - ИСПРАВЛЯЕМ ФОРМАТ to
            $this->stdout("Создание сообщения...\n", Console::FG_CYAN);
            $message = $mailer->compose()
                ->setFrom([$senderEmail => $senderName])
                ->setTo($to)  // Просто строка, без массива
                ->setSubject('Test email from console - ' . date('Y-m-d H:i:s'))
                ->setTextBody('This is a test email body. Sent at ' . date('Y-m-d H:i:s'));
            
            if (!$message) {
                $this->stderr("❌ Не удалось создать сообщение\n", Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            
            // Получаем информацию о сообщении
            $this->stdout("   From: " . json_encode($message->getFrom()) . "\n", Console::FG_CYAN);
            $this->stdout("   To: " . json_encode($message->getTo()) . "\n", Console::FG_CYAN);
            $this->stdout("   Subject: " . $message->getSubject() . "\n", Console::FG_CYAN);
            $this->stdout("✅ Сообщение создано\n", Console::FG_GREEN);
            
            // Отправляем с перехватом ошибок
            $this->stdout("Отправка...\n", Console::FG_CYAN);
            
            try {
                $result = $message->send();
                if ($result) {
                    $this->stdout("✅ Email отправлен успешно на {$to}\n", Console::FG_GREEN);
                } else {
                    $this->stderr("❌ Не удалось отправить email (send вернул false)\n", Console::FG_RED);
                    
                    $error = error_get_last();
                    if ($error) {
                        $this->stdout("   Последняя ошибка PHP: " . $error['message'] . "\n", Console::FG_YELLOW);
                    }
                }
            } catch (\Exception $e) {
                $this->stderr("❌ Ошибка при отправке: " . $e->getMessage() . "\n", Console::FG_RED);
                $this->stderr("   Тип: " . get_class($e) . "\n", Console::FG_RED);
                $this->stderr("   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n", Console::FG_RED);
                
                if (strpos($e->getMessage(), '535') !== false) {
                    $this->stdout("\n   🔍 Ошибка 535: Неверный пароль или доступ запрещен.\n", Console::FG_YELLOW);
                    $this->stdout("   Проверьте:\n", Console::FG_YELLOW);
                    $this->stdout("   1. Используете ли вы пароль приложения (не основной пароль)\n", Console::FG_YELLOW);
                    $this->stdout("   2. Правильно ли скопирован пароль (без пробелов)\n", Console::FG_YELLOW);
                    $this->stdout("   3. Включен ли доступ к внешним приложениям в настройках Яндекс\n", Console::FG_YELLOW);
                }
            }
            
            if ($mailer->useFileTransport) {
                $mailPath = Yii::getAlias('@runtime/mail');
                $this->stdout("\nFile transport включен. Проверьте {$mailPath}\n", Console::FG_YELLOW);
                if (is_dir($mailPath)) {
                    $files = glob($mailPath . '/*.eml');
                    $this->stdout("Найдено " . count($files) . " email файлов\n", Console::FG_CYAN);
                    if (!empty($files)) {
                        $lastFile = end($files);
                        $this->stdout("Последний файл: " . basename($lastFile) . "\n", Console::FG_CYAN);
                    }
                }
            }
            
        } catch (\Exception $e) {
            $this->stderr("❌ Общая ошибка: " . $e->getMessage() . "\n", Console::FG_RED);
            $this->stderr("   Тип: " . get_class($e) . "\n", Console::FG_RED);
            $this->stderr("   Файл: " . $e->getFile() . ":" . $e->getLine() . "\n", Console::FG_RED);
            $this->stderr("\nStack trace:\n" . $e->getTraceAsString() . "\n", Console::FG_RED);
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
}