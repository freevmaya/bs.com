<?php

use yii\db\Migration;

class m240000_000005_add_vk_id_to_users extends Migration
{
    public function safeUp()
    {
        $this->addColumn('users', 'vk_id', $this->string(100)->after('phone'));
    }

    public function safeDown()
    {
        $this->dropColumn('users', 'vk_id');
    }
}