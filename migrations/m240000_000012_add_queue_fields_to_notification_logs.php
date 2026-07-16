<?php
// migrations/m240000_000012_add_queue_fields_to_notification_logs.php

use yii\db\Migration;

class m240000_000012_add_queue_fields_to_notification_logs extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('notification_logs');
        
        // Добавляем поле queued_at
        if (!$table->getColumn('queued_at')) {
            $this->addColumn('notification_logs', 'queued_at', $this->integer()->after('created_at'));
        }
        
        // Добавляем поле retry_count
        if (!$table->getColumn('retry_count')) {
            $this->addColumn('notification_logs', 'retry_count', $this->integer()->defaultValue(0)->after('queued_at'));
        }
        
        // Добавляем поле html_body (если нужно хранить HTML отдельно)
        if (!$table->getColumn('html_body')) {
            $this->addColumn('notification_logs', 'html_body', $this->text()->after('message'));
        }
        
        // Добавляем индексы с проверкой
        try {
            $this->createIndex('idx-notification_logs-status-queued_at', 'notification_logs', ['status', 'queued_at']);
        } catch (\Exception $e) {
            echo "Index 'idx-notification_logs-status-queued_at' already exists, skipping...\n";
        }
        
        try {
            $this->createIndex('idx-notification_logs-retry_count', 'notification_logs', 'retry_count');
        } catch (\Exception $e) {
            echo "Index 'idx-notification_logs-retry_count' already exists, skipping...\n";
        }
    }
    
    public function safeDown()
    {
        $this->dropIndex('idx-notification_logs-retry_count', 'notification_logs');
        $this->dropIndex('idx-notification_logs-status-queued_at', 'notification_logs');
        $this->dropColumn('notification_logs', 'html_body');
        $this->dropColumn('notification_logs', 'retry_count');
        $this->dropColumn('notification_logs', 'queued_at');
    }
}