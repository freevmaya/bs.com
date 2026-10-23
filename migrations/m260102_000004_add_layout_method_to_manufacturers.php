<?php
// FILE: migrations/m260102_000004_add_layout_method_to_manufacturers.php

use yii\db\Migration;

class m260102_000004_add_layout_method_to_manufacturers extends Migration
{
    public function safeUp()
    {
        $this->addColumn(
            'manufacturers',
            'layout_method',
            $this->string(100)->null()->after('website')
        );
    }

    public function safeDown()
    {
        $this->dropColumn('manufacturers', 'layout_method');
    }
}