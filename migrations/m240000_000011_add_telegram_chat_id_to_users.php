<?php
// migrations/m240000_000011_add_telegram_chat_id_to_users.php

use yii\db\Migration;

class m240000_000011_add_telegram_chat_id_to_users extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        // Добавляем поле telegram_chat_id
        $this->addColumn('users', 'telegram_chat_id', $this->string(100)->after('telegram'));
        
        // Добавляем индекс для быстрого поиска по chat_id
        $this->createIndex('idx-users-telegram_chat_id', 'users', 'telegram_chat_id');
        
        // Добавляем комментарий к полю
        $this->execute("ALTER TABLE `users` MODIFY `telegram_chat_id` VARCHAR(100) COMMENT 'Telegram Chat ID для отправки уведомлений'");
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        // Удаляем индекс
        $this->dropIndex('idx-users-telegram_chat_id', 'users');
        
        // Удаляем поле
        $this->dropColumn('users', 'telegram_chat_id');
    }
}