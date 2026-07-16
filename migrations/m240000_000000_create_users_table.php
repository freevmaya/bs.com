<?php
// migrations/m240000_000000_create_users_table.php

use yii\db\Migration;

class m240000_000000_create_users_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable('users', [
            'id' => $this->primaryKey(),
            'username' => $this->string(100)->notNull()->unique(),
            'email' => $this->string(100)->notNull()->unique(),
            'password_hash' => $this->string(255)->notNull(),
            'auth_key' => $this->string(32)->notNull(),
            'phone' => $this->string(20),
            'city' => $this->string(100),
            'created_at' => $this->integer()->notNull(),
            'updated_at' => $this->integer()->notNull(),
        ], $tableOptions);
    }
    
    public function safeDown()
    {
        $this->dropTable('users');
    }
}