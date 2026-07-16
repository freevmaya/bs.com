<?php
// migrations/m240000_000012_add_fields_to_notification_logs.php

use yii\db\Migration;

class m240000_000012_add_fields_to_notification_logs extends Migration
{
    public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('notification_logs');
        
        // 1. Изменяем поле status на ENUM с добавлением 'queued'
        $this->execute("ALTER TABLE `notification_logs` MODIFY `status` ENUM('pending', 'sent', 'failed', 'queued') NOT NULL DEFAULT 'pending'");
        
        // 2. Добавляем поле queued_at
        if (!$table->getColumn('queued_at')) {
            $this->addColumn('notification_logs', 'queued_at', $this->integer()->after('created_at'));
        }
        
        // 3. Добавляем поле retry_count
        if (!$table->getColumn('retry_count')) {
            $this->addColumn('notification_logs', 'retry_count', $this->integer()->defaultValue(0)->after('queued_at'));
        }
        
        // 4. Добавляем поле html_body
        if (!$table->getColumn('html_body')) {
            $this->addColumn('notification_logs', 'html_body', $this->text()->after('message'));
        }
        
        // 5. Добавляем индексы (с проверкой на существование)
        try {
            $this->createIndex('idx-notification_logs-status-queued_at', 'notification_logs', ['status', 'queued_at']);
        } catch (\Exception $e) {
            // Индекс уже существует - игнорируем
            echo "Index 'idx-notification_logs-status-queued_at' already exists, skipping...\n";
        }
        
        try {
            $this->createIndex('idx-notification_logs-retry_count', 'notification_logs', 'retry_count');
        } catch (\Exception $e) {
            echo "Index 'idx-notification_logs-retry_count' already exists, skipping...\n";
        }
        
        try {
            $this->createIndex('idx-notification_logs-event', 'notification_logs', 'event');
        } catch (\Exception $e) {
            echo "Index 'idx-notification_logs-event' already exists, skipping...\n";
        }
        
        // 6. Обновляем существующие записи
        $this->update('notification_logs', ['queued_at' => new \yii\db\Expression('created_at')], ['queued_at' => null]);
        $this->update('notification_logs', ['retry_count' => 0], ['retry_count' => null]);
        
        // 7. Обновляем старые записи со статусом 'pending' на 'queued'
        $this->update('notification_logs', ['status' => 'queued'], ['status' => 'pending']);
    }
    
    public function safeDown()
    {
        $this->dropIndex('idx-notification_logs-retry_count', 'notification_logs');
        $this->dropIndex('idx-notification_logs-status-queued_at', 'notification_logs');
        $this->dropIndex('idx-notification_logs-event', 'notification_logs');
        
        $this->dropColumn('notification_logs', 'html_body');
        $this->dropColumn('notification_logs', 'retry_count');
        $this->dropColumn('notification_logs', 'queued_at');
        
        // Возвращаем статус обратно к ENUM без 'queued'
        $this->execute("ALTER TABLE `notification_logs` MODIFY `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending'");
    }
}