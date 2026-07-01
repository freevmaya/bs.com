<?php

use yii\db\Migration;

class m240000_000002_create_notification_logs_table extends Migration
{
    const TABLE_NAME = 'notification_logs';
    
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'channel' => $this->string(50)->notNull(),
            'event' => $this->string(100)->notNull(),
            'subject' => $this->string(255),
            'message' => $this->text(),
            'status' => "ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending'",
            'error' => $this->text(),
            'created_at' => $this->integer()->notNull(),
            'sent_at' => $this->integer(),
        ], $tableOptions);
        
        $this->createIndex('idx-log-user_id', self::TABLE_NAME, 'user_id');
        $this->createIndex('idx-log-channel', self::TABLE_NAME, 'channel');
        $this->createIndex('idx-log-event', self::TABLE_NAME, 'event');
        $this->createIndex('idx-log-status', self::TABLE_NAME, 'status');
        $this->createIndex('idx-log-created_at', self::TABLE_NAME, 'created_at');
        
        $this->addForeignKey(
            'fk-log-user',
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