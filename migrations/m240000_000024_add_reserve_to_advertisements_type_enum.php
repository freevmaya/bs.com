<?php
// FILE: .\migrations\m240000_000024_add_reserve_to_advertisements_type_enum.php

use yii\db\Migration;

/**
 * Добавляет 'reserve' в ENUM-значения поля advertisements.type.
 *
 * Без этой миграции вставка объявления с type='reserve' вызывает
 * SQLSTATE[01000] Data truncated for column 'type'.
 */
class m240000_000024_add_reserve_to_advertisements_type_enum extends Migration
{
    const TABLE_NAME = 'advertisements';

    public function safeUp()
    {
        $this->execute(
            "ALTER TABLE `" . self::TABLE_NAME . "` " .
            "MODIFY `type` ENUM('normal', 'glider', 'harness', 'device', 'reserve') " .
            "NOT NULL DEFAULT 'normal'"
        );
    }

    public function safeDown()
    {
        // Перед откатом нужно убедиться, что в таблице нет строк с type='reserve',
        // иначе MySQL выдаст ошибку при сужении ENUM.
        $exists = (new \yii\db\Query())
            ->from(self::TABLE_NAME)
            ->where(['type' => 'reserve'])
            ->exists($this->db);

        if ($exists) {
            echo "В таблице " . self::TABLE_NAME . " есть записи с type='reserve'. " .
                 "Удалите или измените их перед откатом миграции.\n";
            return false;
        }

        $this->execute(
            "ALTER TABLE `" . self::TABLE_NAME . "` " .
            "MODIFY `type` ENUM('normal', 'glider', 'harness', 'device') " .
            "NOT NULL DEFAULT 'normal'"
        );

        return true;
    }
}