<?php
// migrations/m240000_000012_add_queued_fields_to_notification_logs.php

use yii\db\Migration;

class m240000_000012_add_queued_fields_to_notification_logs extends Migration
{
    public function safeUp()
    {
        // Добавляем поле queued_at
        if (!$this->db->schema->getTableSchema('notification_logs')->getColumn('queued_at')) {
            $this->addColumn('notification_logs', 'queued_at', $this->integer()->after('created_at'));
        }
        
        // Добавляем поле retry_count
        if (!$this->db->schema->getTableSchema('notification_logs')->getColumn('retry_count')) {
            $this->addColumn('notification_logs', 'retry_count', $this->integer()->defaultValue(0)->after('queued_at'));
        }
        
        // Добавляем поле html_body (если нужно хранить HTML отдельно)
        if (!$this->db->schema->getTableSchema('notification_logs')->getColumn('html_body')) {
            $this->addColumn('notification_logs', 'html_body', $this->text()->after('message'));
        }
        
        // Добавляем индексы для быстрого поиска
        $this->createIndex('idx-notification_logs-status-queued_at', 'notification_logs', ['status', 'queued_at']);
        $this->createIndex('idx-notification_logs-retry_count', 'notification_logs', 'retry_count');
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