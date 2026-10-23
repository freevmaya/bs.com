<?php
// FILE: migrations/m260102_000001_create_we_measure_tables.php

use yii\db\Migration;

class m260102_000001_create_we_measure_tables extends Migration
{
    const TABLE_MANUFACTURERS    = 'manufacturers';
    const TABLE_WINGS            = 'wings';
    const TABLE_WING_LOWER_LINES = 'wing_lower_lines';
    const TABLE_WING_UPPER_LINES = 'wing_upper_lines';
    const TABLE_PARSE_MISMATCHES = 'parse_mismatches';

    public function safeUp()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        // ============================================================
        // manufacturers
        // ============================================================
        $this->createTable(self::TABLE_MANUFACTURERS, [
            'id'      => $this->primaryKey(),
            'name'    => $this->string(150)->notNull(),
            'website' => $this->string(500)->null(),
        ], $tableOptions);

        $this->createIndex(
            'idx-manufacturers-name',
            self::TABLE_MANUFACTURERS,
            'name',
            true
        );

        // ============================================================
        // wings
        // ============================================================
        $this->createTable(self::TABLE_WINGS, [
            'id'              => $this->primaryKey(),
            'source_id'       => $this->integer()->notNull(),
            'manufacturer_id' => $this->integer()->null(),
            'model'           => $this->string(150)->notNull(),
            'model_year'      => $this->smallInteger()->null(),
            'size'            => $this->string(30)->notNull(),
            'full_name'       => $this->string(255)->notNull(),
            'source_url'      => $this->string(500)->null(),
            'catalog_match'   => $this->boolean()->notNull()->defaultValue(1),
            'parsed_at'       => $this->dateTime()->null(),
        ], $tableOptions);

        $this->createIndex(
            'idx-wings-source_id',
            self::TABLE_WINGS,
            'source_id',
            true
        );
        $this->createIndex(
            'idx-wings-manufacturer_id',
            self::TABLE_WINGS,
            'manufacturer_id'
        );

        $this->addForeignKey(
            'fk-wings-manufacturer',
            self::TABLE_WINGS,
            'manufacturer_id',
            self::TABLE_MANUFACTURERS,
            'id',
            'SET NULL',
            'CASCADE'
        );

        // ============================================================
        // wing_lower_lines
        // ============================================================
        $this->createTable(self::TABLE_WING_LOWER_LINES, [
            'id'          => $this->primaryKey(),
            'wing_id'     => $this->integer()->notNull(),
            'group_index' => $this->smallInteger()->notNull(),
            'name'        => $this->string(50)->notNull(),
            'length_mm'   => $this->decimal(10, 2)->null(),
            'riser_note'  => $this->string(50)->null(),
            'raw_value'   => $this->string(100)->null(),
        ], $tableOptions);

        $this->createIndex(
            'idx-wing_lower_lines-wing_id-group_index',
            self::TABLE_WING_LOWER_LINES,
            ['wing_id', 'group_index'],
            true
        );

        $this->addForeignKey(
            'fk-wing_lower_lines-wing',
            self::TABLE_WING_LOWER_LINES,
            'wing_id',
            self::TABLE_WINGS,
            'id',
            'CASCADE',
            'CASCADE'
        );

        // ============================================================
        // wing_upper_lines
        // ============================================================
        $this->createTable(self::TABLE_WING_UPPER_LINES, [
            'id'            => $this->primaryKey(),
            'wing_id'       => $this->integer()->notNull(),
            'lower_line_id' => $this->integer()->notNull(),
            'name'          => $this->string(50)->notNull(),
            'length_mm'     => $this->decimal(10, 2)->notNull(),
            'sort_order'    => $this->smallInteger()->notNull()->defaultValue(0),
        ], $tableOptions);

        $this->createIndex(
            'idx-wing_upper_lines-wing_id',
            self::TABLE_WING_UPPER_LINES,
            'wing_id'
        );
        $this->createIndex(
            'idx-wing_upper_lines-lower_line_id',
            self::TABLE_WING_UPPER_LINES,
            'lower_line_id'
        );
        $this->createIndex(
            'idx-wing_upper_lines-unique',
            self::TABLE_WING_UPPER_LINES,
            ['lower_line_id', 'name'],
            true
        );

        $this->addForeignKey(
            'fk-wing_upper_lines-wing',
            self::TABLE_WING_UPPER_LINES,
            'wing_id',
            self::TABLE_WINGS,
            'id',
            'CASCADE',
            'CASCADE'
        );
        $this->addForeignKey(
            'fk-wing_upper_lines-lower_line',
            self::TABLE_WING_UPPER_LINES,
            'lower_line_id',
            self::TABLE_WING_LOWER_LINES,
            'id',
            'CASCADE',
            'CASCADE'
        );

        // ============================================================
        // parse_mismatches
        // ============================================================
        $this->createTable(self::TABLE_PARSE_MISMATCHES, [
            'id'            => $this->primaryKey(),
            'source_id'     => $this->integer()->notNull(),
            'catalog_value' => $this->string(255)->null(),
            'page_value'    => $this->string(255)->null(),
            'detected_at'   => $this->dateTime()->notNull(),
        ], $tableOptions);

        $this->createIndex(
            'idx-parse_mismatches-source_id',
            self::TABLE_PARSE_MISMATCHES,
            'source_id'
        );
    }

    public function safeDown()
    {
        $this->dropTable(self::TABLE_PARSE_MISMATCHES);
        $this->dropTable(self::TABLE_WING_UPPER_LINES);
        $this->dropTable(self::TABLE_WING_LOWER_LINES);
        $this->dropTable(self::TABLE_WINGS);
        $this->dropTable(self::TABLE_MANUFACTURERS);
    }
}