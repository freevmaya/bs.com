<?php
// FILE: .\commands\SubscribeAdminsController.php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use app\models\User;
use app\models\NotificationSubscription;

/**
 * Подписка администраторов на события
 *
 * Использование:
 *   php yii subscribe-admins/access-request
 */
class SubscribeAdminsController extends Controller
{
    /**
     * Подписать всех админов на событие access_request
     */
    public function actionAccessRequest()
    {
        $this->stdout("=== ПОДПИСКА АДМИНОВ НА ACCESS_REQUEST ===\n", Console::FG_YELLOW);

        $admins = User::find()->where(['type' => User::TYPE_ADMIN])->all();

        if (empty($admins)) {
            $this->stdout("Администраторы не найдены\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $channels = [
            NotificationSubscription::CHANNEL_EMAIL,
            NotificationSubscription::CHANNEL_TELEGRAM,
            NotificationSubscription::CHANNEL_VK,
        ];

        foreach ($admins as $admin) {
            $this->stdout("Admin #{$admin->id} ({$admin->username}):\n", Console::FG_CYAN);

            foreach ($channels as $channel) {
                if (!NotificationSubscription::isChannelAvailableForUser($admin, $channel)) {
                    $this->stdout("  - {$channel}: пропущен (нет контакта)\n", Console::FG_YELLOW);
                    continue;
                }

                if (NotificationSubscription::subscribe($admin->id, 'access_request', $channel)) {
                    $this->stdout("  - {$channel}: подписан\n", Console::FG_GREEN);
                } else {
                    $this->stdout("  - {$channel}: ошибка подписки\n", Console::FG_RED);
                }
            }
        }

        $this->stdout("\nГотово!\n", Console::FG_CYAN);
        return ExitCode::OK;
    }
}