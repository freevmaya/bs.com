<?php
// migrations/m240000_000001_create_notification_subscriptions_table.php

use yii\db\Migration;

class m240000_000001_create_notification_subscriptions_table extends Migration
{
    const TABLE_NAME = 'notification_subscriptions';
    
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'event' => $this->string(100)->notNull(),
            'channel' => $this->string(50)->notNull(),
            'is_active' => $this->boolean()->defaultValue(true),
            'settings' => $this->text(),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        
        $this->createIndex('idx-subscription-user_id', self::TABLE_NAME, 'user_id');
        $this->createIndex('idx-subscription-event', self::TABLE_NAME, 'event');
        $this->createIndex('idx-subscription-channel', self::TABLE_NAME, 'channel');
        
        $this->createIndex(
            'idx-subscription-unique', 
            self::TABLE_NAME, 
            ['user_id', 'event', 'channel'], 
            true
        );
        
        // Проверяем, существует ли таблица users перед добавлением внешнего ключа
        $tableSchema = $this->db->schema->getTableSchema('users');
        if ($tableSchema !== null) {
            $this->addForeignKey(
                'fk-subscription-user',
                self::TABLE_NAME,
                'user_id',
                'users',
                'id',
                'CASCADE',
                'CASCADE'
            );
        } else {
            echo "Table 'users' does not exist yet. Foreign key will be added later.\n";
        }
    }
    
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}