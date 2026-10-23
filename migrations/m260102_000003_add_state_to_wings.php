<?php
// FILE: migrations/m260102_000003_add_state_to_wings.php

use yii\db\Migration;

class m260102_000003_add_state_to_wings extends Migration
{
    public function safeUp()
    {
        $this->execute(
            "ALTER TABLE `wings` "
            . "ADD `state` ENUM('active','require_update','hide','delete') "
            . "NOT NULL DEFAULT 'active' AFTER `catalog_match`"
        );
        $this->createIndex('idx-wings-state', 'wings', 'state');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-wings-state', 'wings');
        $this->dropColumn('wings', 'state');
    }
}