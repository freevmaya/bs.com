<?php

use yii\db\Migration;

class m240000_000015_add_type_to_users extends Migration
{
    public function safeUp()
    {
        // Используем execute для прямого SQL запроса без кавычек вокруг имени столбца
        $this->execute("ALTER TABLE `users` ADD `type` ENUM('user', 'admin') NOT NULL DEFAULT 'user' AFTER `id`");
        $this->createIndex('idx-users-type', 'users', 'type');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-users-type', 'users');
        $this->dropColumn('users', 'type');
    }
}