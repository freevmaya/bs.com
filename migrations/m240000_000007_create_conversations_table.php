<?php

use yii\db\Migration;

class m240000_000007_create_conversations_table extends Migration
{
    const TABLE_NAME = 'conversations';
    
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'advertisement_id' => $this->integer()->notNull(),
            'user1_id' => $this->integer()->notNull(),
            'user2_id' => $this->integer()->notNull(),
            'last_message_at' => $this->integer()->notNull(),
            'is_active' => $this->boolean()->defaultValue(true),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        
        $this->createIndex('idx-conversations-advertisement_id', self::TABLE_NAME, 'advertisement_id');
        $this->createIndex('idx-conversations-user1_id', self::TABLE_NAME, 'user1_id');
        $this->createIndex('idx-conversations-user2_id', self::TABLE_NAME, 'user2_id');
        $this->createIndex('idx-conversations-last_message_at', self::TABLE_NAME, 'last_message_at');
        $this->createIndex('idx-conversations-unique', self::TABLE_NAME, ['advertisement_id', 'user1_id', 'user2_id'], true);
        
        $this->addForeignKey(
            'fk-conversations-advertisement',
            self::TABLE_NAME,
            'advertisement_id',
            'advertisements',
            'id',
            'CASCADE',
            'CASCADE'
        );
        
        $this->addForeignKey(
            'fk-conversations-user1',
            self::TABLE_NAME,
            'user1_id',
            'users',
            'id',
            'CASCADE',
            'CASCADE'
        );
        
        $this->addForeignKey(
            'fk-conversations-user2',
            self::TABLE_NAME,
            'user2_id',
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