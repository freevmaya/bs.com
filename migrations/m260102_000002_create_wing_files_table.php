<?php
// FILE: migrations/m260102_000002_create_wing_files_table.php

use yii\db\Migration;

class m260102_000002_create_wing_files_table extends Migration
{
    const TABLE_NAME = 'wing_files';

    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable(self::TABLE_NAME, [
            'id'         => $this->primaryKey(),
            'wing_id'    => $this->integer()->notNull(),
            'name'       => $this->string(255)->notNull(),
            'url'        => $this->string(500)->notNull(),
            'file_date'  => $this->date()->null(),
            'sort_order' => $this->smallInteger()->notNull()->defaultValue(0),
        ], $tableOptions);

        $this->createIndex(
            'idx-wing_files-wing_id',
            self::TABLE_NAME,
            'wing_id'
        );
        $this->createIndex(
            'idx-wing_files-unique',
            self::TABLE_NAME,
            ['wing_id', 'url'],
            true
        );

        $this->addForeignKey(
            'fk-wing_files-wing',
            self::TABLE_NAME,
            'wing_id',
            'wings',
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