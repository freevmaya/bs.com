<?php
// FILE: .\migrations\m240000_000023_create_advertisement_reserve_table.php

use yii\db\Migration;

/**
 * Создает таблицу для типа объявления "Запасной парашют".
 *
 * Поле weight хранит вес запаски в килограммах (дробное, например 1.70).
 */
class m240000_000023_create_advertisement_reserve_table extends Migration
{
    const TABLE_NAME = 'advertisement_reserve';

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
            'producer_id' => $this->integer()->null(),
            'canopy_type' => "ENUM('round', 'square', 'multi_slot', 'other') NULL DEFAULT NULL",
            'max_weight' => $this->integer()->null(),
            'area' => $this->decimal(5, 1)->null(),
            'weight' => $this->decimal(5, 2)->null(),
            'date_release' => $this->string(100)->null(),
            'repack_date' => $this->date()->null(),
            'condition' => "ENUM('new', 'excellent', 'good', 'fair', 'bad') NOT NULL DEFAULT 'good'",
            'defects' => $this->text()->null(),
            'was_used' => $this->boolean()->null()->defaultValue(null),
            'equipment' => $this->text()->null(),
            'cause' => $this->text()->null(),
        ], $tableOptions);

        $this->createIndex('idx-reserve-advertisement_id', self::TABLE_NAME, 'advertisement_id');
        $this->createIndex('idx-reserve-producer_id', self::TABLE_NAME, 'producer_id');

        $this->addForeignKey(
            'fk-reserve-advertisement',
            self::TABLE_NAME,
            'advertisement_id',
            'advertisements',
            'id',
            'CASCADE',
            'CASCADE'
        );

        $this->addForeignKey(
            'fk-reserve-producer',
            self::TABLE_NAME,
            'producer_id',
            'producers',
            'id',
            'SET NULL',
            'CASCADE'
        );
    }

    public function safeDown()
    {
        $this->dropTable(self::TABLE_NAME);
    }
}