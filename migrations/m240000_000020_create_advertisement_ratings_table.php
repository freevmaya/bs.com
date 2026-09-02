<?php
// migrations/m240000_000020_create_advertisement_ratings_table.php

use yii\db\Migration;

class m240000_000020_create_advertisement_ratings_table extends Migration
{
    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('advertisement_ratings', [
            'id' => $this->primaryKey(),
            'advertisement_id' => $this->integer()->notNull(),
            'user_id' => $this->integer()->null(),
            'ai_model' => $this->string(50)->notNull(),
            'rating_data' => $this->text()->null(),
            'overall_score' => $this->decimal(3, 1)->null(),
            'summary' => $this->text()->null(),
            'pros' => $this->text()->null(),
            'cons' => $this->text()->null(),
            'recommendation' => $this->text()->null(),
            'created_at' => $this->integer()->notNull(),
        ], $tableOptions);

        $this->createIndex('idx-ratings-advertisement_id', 'advertisement_ratings', 'advertisement_id');
        $this->createIndex('idx-ratings-user_id', 'advertisement_ratings', 'user_id');

        $this->addForeignKey(
            'fk-ratings-advertisement',
            'advertisement_ratings',
            'advertisement_id',
            'advertisements',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-ratings-user',
            'advertisement_ratings',
            'user_id',
            'users',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable('advertisement_ratings');
    }
}