<?php
// FILE: .\migrations\m240000_000004_create_search_subscriptions_table.php

use yii\db\Migration;

class m240000_000004_create_search_subscriptions_table extends Migration
{
    const TABLE_NAME = 'search_subscriptions';

    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'params' => $this->text()->notNull(),
            'section' => $this->string(20)->notNull(),
            'is_active' => $this->boolean()->defaultValue(true),
            'last_notified_at' => $this->integer(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-search-subscriptions-user_id', self::TABLE_NAME, 'user_id');
        $this->createIndex('idx-search-subscriptions-section', self::TABLE_NAME, 'section');
        $this->createIndex('idx-search-subscriptions-is_active', self::TABLE_NAME, 'is_active');

        $this->addForeignKey(
            'fk-search-subscriptions-user',
            self::TABLE_NAME,
            'user_id',
            'users',
            'id',
            'CASCADE',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}