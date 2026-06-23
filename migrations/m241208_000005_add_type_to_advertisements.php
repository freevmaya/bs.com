<?php

use yii\db\Migration;

class m241208_000005_add_type_to_advertisements extends Migration
{
    public function safeUp()
    {
        // Сначала добавляем колонку как VARCHAR, затем меняем на ENUM
        $this->addColumn('advertisements', 'type', $this->string(20)->notNull()->defaultValue('normal')->after('section'));
        
        // Меняем тип на ENUM
        $this->execute("ALTER TABLE `advertisements` MODIFY `type` ENUM('normal', 'glider', 'harness', 'device') NOT NULL DEFAULT 'normal'");
    }
    
    public function safeDown()
    {
        $this->dropColumn('advertisements', 'type');
    }
}