<?php

use yii\db\Migration;

class m240000_000016_add_source_url_to_advertisements extends Migration
{
    public function safeUp()
    {
        $this->addColumn('advertisements', 'source_url', $this->string(500)->after('whatsapp'));
        $this->createIndex('idx-advertisements-source_url', 'advertisements', 'source_url');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-advertisements-source_url', 'advertisements');
        $this->dropColumn('advertisements', 'source_url');
    }
}