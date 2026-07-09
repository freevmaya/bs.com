<?php

use yii\db\Migration;

class m240000_000006_add_vk_profile_url_to_users extends Migration
{
    public function safeUp()
    {
        $this->addColumn('users', 'vk_profile_url', $this->string(255)->after('vk_id'));
    }

    public function safeDown()
    {
        $this->dropColumn('users', 'vk_profile_url');
    }
}