<?php

use yii\db\Migration;

class m241208_000001_create_advertisements_table extends Migration
{
    const TABLE_NAME = 'advertisements';
    
    const SECTION_SELL = 'sell';
    const SECTION_BUY = 'buy';
    const STATUS_ACTIVE = 'active';
    const STATUS_MODERATION = 'moderation';
    const STATUS_CLOSED = 'closed';
    
    public function safeUp()
    {
       $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'section' => $this->string(20)->notNull(),
            'title' => $this->string(200)->notNull(),
            'description' => $this->text(),
            'price' => $this->decimal(10, 2),
            'price_negotiable' => $this->boolean()->defaultValue(false),
            'city' => $this->string(100),
            'phone' => $this->string(20),
            'email' => $this->string(100),
            'status' => "ENUM('active', 'moderation', 'closed') NOT NULL DEFAULT 'moderation'",
            'views_count' => $this->integer()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
        
        $this->createIndex('idx-advertisements-user_id', self::TABLE_NAME, 'user_id');
        $this->createIndex('idx-advertisements-section', self::TABLE_NAME, 'section');
        $this->createIndex('idx-advertisements-status', self::TABLE_NAME, 'status');
        $this->createIndex('idx-advertisements-created_at', self::TABLE_NAME, 'created_at');
    }
    
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}