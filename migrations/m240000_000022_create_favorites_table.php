<?php
// FILE: .\migrations\m240000_000022_create_favorites_table.php

use yii\db\Migration;

class m240000_000022_create_favorites_table extends Migration
{
    const TABLE_NAME = 'favorites';

    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'advertisement_id' => $this->integer()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-favorites-user_id', self::TABLE_NAME, 'user_id');
        $this->createIndex('idx-favorites-advertisement_id', self::TABLE_NAME, 'advertisement_id');
        $this->createIndex(
            'idx-favorites-unique',
            self::TABLE_NAME,
            ['user_id', 'advertisement_id'],
            true
        );

        $this->addForeignKey(
            'fk-favorites-user',
            self::TABLE_NAME,
            'user_id',
            'users',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-favorites-advertisement',
            self::TABLE_NAME,
            'advertisement_id',
            'advertisements',
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