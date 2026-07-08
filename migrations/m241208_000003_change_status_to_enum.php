<?php

use yii\db\Migration;

class m241208_000003_change_status_to_enum extends Migration
{
    public function safeUp()
    {
        // Меняем тип поля на ENUM
        $this->alterColumn('advertisements', 'status', "ENUM('active', 'moderation', 'closed') NOT NULL DEFAULT 'active'");
    }
    
    public function safeDown()
    {
        // Возвращаем обратно к VARCHAR
        $this->alterColumn('advertisements', 'status', $this->string(20)->notNull()->defaultValue('active'));
    }
}