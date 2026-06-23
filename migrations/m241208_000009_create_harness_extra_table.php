<?php

use yii\db\Migration;

class m241208_000009_create_harness_extra_table extends Migration
{
    const TABLE_NAME = 'advertisement_harness';
    
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
            'size' => "ENUM('XS', 'S', 'SM', 'M', 'ML', 'L', 'XL', 'XXL', 'XXXL', 'OneSize') NOT NULL",
            'date_release' => $this->string(100),
            'condition' => "ENUM('new', 'excellent', 'good', 'fair', 'bad') NOT NULL DEFAULT 'good'",
            'defects' => $this->text(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ], $tableOptions);
        
        $this->createIndex('idx-harness-advertisement_id', self::TABLE_NAME, 'advertisement_id');
        $this->addForeignKey('fk-harness-advertisement', self::TABLE_NAME, 'advertisement_id', 'advertisements', 'id', 'CASCADE', 'CASCADE');
        $this->addForeignKey('fk-harness-producer', self::TABLE_NAME, 'producer_id', 'producers', 'id', 'RESTRICT', 'CASCADE');
    }
    
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}