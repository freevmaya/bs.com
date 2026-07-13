<?php
// FILE: .\migrations\m240000_000010_add_telegram_whatsapp_to_users.php

use yii\db\Migration;

class m240000_000010_add_telegram_whatsapp_to_users extends Migration
{
    public function safeUp()
    {
        $this->addColumn('users', 'telegram', $this->string(100)->after('vk_profile_url'));
        $this->addColumn('users', 'whatsapp', $this->string(100)->after('telegram'));
    }

    public function safeDown()
    {
        $this->dropColumn('users', 'telegram');
        $this->dropColumn('users', 'whatsapp');
    }
}