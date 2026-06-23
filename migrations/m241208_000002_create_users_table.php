<?php

use yii\db\Migration;

class m241208_000002_create_users_table extends Migration
{
    public function safeUp()
    {
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
        ]);
    }
    
    public function safeDown()
    {
        $this->dropTable('users');
    }
}