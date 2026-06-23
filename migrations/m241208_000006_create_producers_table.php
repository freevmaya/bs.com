<?php

use yii\db\Migration;

class m241208_000006_create_producers_table extends Migration
{
    const TABLE_NAME = 'producers';
    
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'name' => $this->string(255)->notNull(),
            'short' => $this->string(100),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ], $tableOptions);
        
        // Добавляем начальные данные
        $this->batchInsert(self::TABLE_NAME, ['name', 'short'], [
            ['Advance', 'Advance'],
            ['AirDesign', 'AirDesign'],
            ['Axis', 'Axis'],
            ['BGD', 'BGD'],
            ['DaVinci', 'DaVinci'],
            ['Dudek', 'Dudek'],
            ['Flow', 'Flow'],
            ['Gin', 'Gin'],
            ['Gradient', 'Gradient'],
            ['Icaro', 'Icaro'],
            ['Independence', 'Independence'],
            ['MAC Para', 'MAC Para'],
            ['Niviuk', 'Niviuk'],
            ['NoLimit', 'NoLimit'],
            ['Nova', 'Nova'],
            ['Ozone', 'Ozone'],
            ['Paratech', 'Paratech'],
            ['Skywalk', 'Skywalk'],
            ['Sky Country', 'Sky Country'],
            ['Swing', 'Swing'],
            ['Triple Seven', '777'],
            ['UP', 'UP'],
            ['Windtech', 'Windtech'],
            ['Woody Valley', 'Woody Valley'],
        ]);
    }
    
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}