<?php

use yii\db\Migration;

class m240000_000008_create_messages_table extends Migration
{
    const TABLE_NAME = 'messages';
    
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'conversation_id' => $this->integer()->notNull(),
            'sender_id' => $this->integer()->notNull(),
            'receiver_id' => $this->integer()->notNull(),
            'message' => $this->text()->notNull(),
            'is_read' => $this->boolean()->defaultValue(false),
            'read_at' => $this->integer(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
        
        $this->createIndex('idx-messages-conversation_id', self::TABLE_NAME, 'conversation_id');
        $this->createIndex('idx-messages-sender_id', self::TABLE_NAME, 'sender_id');
        $this->createIndex('idx-messages-receiver_id', self::TABLE_NAME, 'receiver_id');
        $this->createIndex('idx-messages-is_read', self::TABLE_NAME, 'is_read');
        $this->createIndex('idx-messages-created_at', self::TABLE_NAME, 'created_at');
        
        $this->addForeignKey(
            'fk-messages-conversation',
            self::TABLE_NAME,
            'conversation_id',
            'conversations',
            'id',
            'CASCADE',
            'CASCADE'
        );
        
        $this->addForeignKey(
            'fk-messages-sender',
            self::TABLE_NAME,
            'sender_id',
            'users',
            'id',
            'CASCADE',
            'CASCADE'
        );
        
        $this->addForeignKey(
            'fk-messages-receiver',
            self::TABLE_NAME,
            'receiver_id',
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