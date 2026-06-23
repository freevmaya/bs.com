<?php

use yii\db\Migration;

class m241208_000007_create_certification_table extends Migration
{
    const TABLE_NAME = 'certification';
    
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ], $tableOptions);
        
        // Добавляем начальные данные
        $this->batchInsert(self::TABLE_NAME, ['name'], [
            ['EN A'],
            ['EN B'],
            ['EN C'],
            ['EN D'],
            ['CCC'],
            ['LFT'],
            ['LFT A'],
            ['LFT B'],
        ]);
    }
    
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}