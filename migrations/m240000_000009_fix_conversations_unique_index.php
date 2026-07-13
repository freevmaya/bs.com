<?php

use yii\db\Migration;

class m240000_000009_fix_conversations_unique_index extends Migration
{
    public function safeUp()
    {
        // Удаляем старый уникальный индекс
        $this->dropIndex('idx-conversations-unique', 'conversations');
        
        // Создаем новый уникальный индекс с нормализованным порядком
        // Используем два новых столбца или создаем уникальный индекс на основе
        // (advertisement_id, LEAST(user1_id, user2_id), GREATEST(user1_id, user2_id))
        // Но в MySQL это делается через виртуальные столбцы или триггеры
        
        // Простой вариант: уникальный индекс на все три поля
        // Он будет работать, так как мы нормализуем порядок в модели
        $this->createIndex(
            'idx-conversations-unique',
            'conversations',
            ['advertisement_id', 'user1_id', 'user2_id'],
            true
        );
        
        // Добавляем индекс для поиска диалогов пользователя
        $this->createIndex(
            'idx-conversations-users',
            'conversations',
            ['user1_id', 'user2_id']
        );
    }
    
    public function safeDown()
    {
        $this->dropIndex('idx-conversations-unique', 'conversations');
        $this->dropIndex('idx-conversations-users', 'conversations');
        
        $this->createIndex(
            'idx-conversations-unique',
            'conversations',
            ['advertisement_id', 'user1_id', 'user2_id'],
            true
        );
    }
}