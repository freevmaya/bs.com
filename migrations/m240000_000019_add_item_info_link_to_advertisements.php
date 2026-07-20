<?php
// migrations/m240000_000019_add_item_info_link_to_advertisements.php

use yii\db\Migration;

class m240000_000019_add_item_info_link_to_advertisements extends Migration
{
    public function safeUp()
    {
        $this->addColumn('advertisements', 'item_info_link', $this->string(500)->null()->after('source_url'));
        $this->createIndex('idx-advertisements-item_info_link', 'advertisements', 'item_info_link');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-advertisements-item_info_link', 'advertisements');
        $this->dropColumn('advertisements', 'item_info_link');
    }
}