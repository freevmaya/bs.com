<?php

use yii\db\Migration;

class m241208_000004_create_advertisement_images_table extends Migration
{
    const TABLE_NAME = 'advertisement_images';
    
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }
        
        $this->createTable(self::TABLE_NAME, [
            'id' => $this->primaryKey(),
            'advertisement_id' => $this->integer()->notNull(),
            'file_name' => $this->string(255)->notNull(),
            'file_path' => $this->string(500)->notNull(),
            'thumbnail_path' => $this->string(500)->notNull(),
            'sort_order' => $this->integer()->defaultValue(0),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);
        
        $this->createIndex('idx-images-advertisement_id', self::TABLE_NAME, 'advertisement_id');
        $this->addForeignKey('fk-images-advertisement', self::TABLE_NAME, 'advertisement_id', 'advertisements', 'id', 'CASCADE', 'CASCADE');
    }
    
    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}