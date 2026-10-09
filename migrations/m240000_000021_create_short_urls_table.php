<?php
// FILE: .\migrations\m240000_000021_create_short_urls_table.php

use yii\db\Migration;

class m240000_000021_create_short_urls_table extends Migration
{
    const TABLE_NAME = 'short_urls';

    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'code' => $this->string(16)->notNull(),
            'url_hash' => $this->char(64)->notNull(),
            'url' => $this->text()->notNull(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-short_urls-code', self::TABLE_NAME, 'code', true);
        $this->createIndex('idx-short_urls-url_hash', self::TABLE_NAME, 'url_hash', true);
    }

    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}