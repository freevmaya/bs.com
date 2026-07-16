<?php
// migrations/m240000_000014_add_contact_fields_to_advertisements.php

use yii\db\Migration;

class m240000_000014_add_contact_fields_to_advertisements extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('advertisements');
        
        if (!$table->getColumn('telegram')) {
            $this->addColumn('advertisements', 'telegram', $this->string(255)->after('email'));
        }
        
        if (!$table->getColumn('vk_profile_url')) {
            $this->addColumn('advertisements', 'vk_profile_url', $this->string(255)->after('telegram'));
        }
        
        if (!$table->getColumn('whatsapp')) {
            $this->addColumn('advertisements', 'whatsapp', $this->string(255)->after('vk_profile_url'));
        }
        
        // Добавляем индексы для быстрого поиска
        $this->createIndex('idx-advertisements-telegram', 'advertisements', 'telegram');
        $this->createIndex('idx-advertisements-vk_profile_url', 'advertisements', 'vk_profile_url');
        $this->createIndex('idx-advertisements-whatsapp', 'advertisements', 'whatsapp');
    }
    
    public function safeDown()
    {
        $this->dropIndex('idx-advertisements-whatsapp', 'advertisements');
        $this->dropIndex('idx-advertisements-vk_profile_url', 'advertisements');
        $this->dropIndex('idx-advertisements-telegram', 'advertisements');
        
        $this->dropColumn('advertisements', 'whatsapp');
        $this->dropColumn('advertisements', 'vk_profile_url');
        $this->dropColumn('advertisements', 'telegram');
    }
}