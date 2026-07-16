<?php
// migrations/m240000_000013_add_oauth_fields_to_users.php

use yii\db\Migration;

class m240000_000013_add_oauth_fields_to_users extends Migration
{
    public function safeUp()
    {
        // Добавляем поля для OAuth
        $this->addColumn('users', 'google_id', $this->string(255)->after('vk_id'));
        $this->addColumn('users', 'facebook_id', $this->string(255)->after('google_id'));
        $this->addColumn('users', 'yandex_id', $this->string(255)->after('facebook_id'));
        $this->addColumn('users', 'github_id', $this->string(255)->after('yandex_id'));
        $this->addColumn('users', 'first_name', $this->string(255)->after('username'));
        $this->addColumn('users', 'last_name', $this->string(255)->after('first_name'));
        $this->addColumn('users', 'photo', $this->string(500)->after('last_name'));
        
        // Добавляем индексы
        $this->createIndex('idx-users-google_id', 'users', 'google_id');
        $this->createIndex('idx-users-facebook_id', 'users', 'facebook_id');
        $this->createIndex('idx-users-yandex_id', 'users', 'yandex_id');
        $this->createIndex('idx-users-github_id', 'users', 'github_id');
    }

    public function safeDown()
    {
        $this->dropIndex('idx-users-github_id', 'users');
        $this->dropIndex('idx-users-yandex_id', 'users');
        $this->dropIndex('idx-users-facebook_id', 'users');
        $this->dropIndex('idx-users-google_id', 'users');
        
        $this->dropColumn('users', 'photo');
        $this->dropColumn('users', 'last_name');
        $this->dropColumn('users', 'first_name');
        $this->dropColumn('users', 'github_id');
        $this->dropColumn('users', 'yandex_id');
        $this->dropColumn('users', 'facebook_id');
        $this->dropColumn('users', 'google_id');
    }
}