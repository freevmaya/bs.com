<?php

use yii\db\Migration;

class m240000_000013_add_new_message_event_to_subscriptions extends Migration
{
    public function safeUp()
    {
        // Добавляем подписки на событие 'new_message' для пользователей,
        // у которых уже есть подписки на другие события.
        // Это предотвратит дублирование.

        $this->execute("
            INSERT IGNORE INTO notification_subscriptions (user_id, event, channel, is_active, created_at, updated_at)
            SELECT DISTINCT user_id, 'new_message', channel, is_active, UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
            FROM notification_subscriptions
            WHERE event != 'new_message'
        ");
    }

    public function safeDown()
    {
        $this->delete('notification_subscriptions', ['event' => 'new_message']);
    }
}