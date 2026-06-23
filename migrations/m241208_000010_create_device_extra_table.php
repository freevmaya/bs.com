<?php

use yii\db\Migration;

class m241208_000010_create_device_extra_table extends Migration
{
    const TABLE_NAME = 'advertisement_device';
    
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'advertisement_id' => $this->integer()->notNull()->unique(),
            'model' => $this->string(255)->notNull(),
            'producer_id' => $this->integer()->notNull(),
            'condition' => "ENUM('new', 'excellent', 'good', 'fair', 'bad') NOT NULL DEFAULT 'good'",
            'defects' => $this->text(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ], $tableOptions);
        
        $this->createIndex('idx-device-advertisement_id', self::TABLE_NAME, 'advertisement_id');
        $this->addForeignKey('fk-device-advertisement', self::TABLE_NAME, 'advertisement_id', 'advertisements', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-device-producer', self::TABLE_NAME, 'producer_id', 'producers', 'id', 'RESTRICT', 'CASCADE');
    }
    
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}