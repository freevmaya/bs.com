<?php

namespace app\components\notifications\channels;

use app\models\NotificationLog;

interface NotificationChannelInterface
{
    /**
     * Отправить уведомление
     * @param string $to - получатель (email, телефон, и т.д.)
     * @param string $subject - тема
     * @param string $message - сообщение
     * @param array $options - дополнительные параметры
     * @return bool
     */
    public function send($to, $subject, $message, $options = []);
    
    /**
     * Получить имя канала
     * @return string
     */
    public function getName();
    
    /**
     * Получить описание канала
     * @return string
     */
    public function getDescription();
    
    /**
     * Проверить, доступен ли канал
     * @return bool
     */
    public function isAvailable();
}