<?php

namespace app\components\notifications\channels;

use Yii;
use yii\mail\MailerInterface;

class EmailChannel implements NotificationChannelInterface
{
    private $mailer;
    
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }
    
    public function send($to, $subject, $message, $options = [])
    {
        try {
            // Проверяем, что email получателя не пустой
            if (empty($to)) {
                Yii::error('Email recipient is empty', 'notification');
                return false;
            }
            
            // Проверяем, что mailer настроен
            if (!$this->mailer) {
                Yii::error('Mailer is not configured', 'notification');
                return false;
            }
            
            // Получаем отправителя из параметров
            $senderEmail = Yii::$app->params['senderEmail'] ?? 'noreply@bs.com';
            $senderName = Yii::$app->params['senderName'] ?? 'BS.com';
            
            $compose = $this->mailer->compose()
                ->setFrom([$senderEmail => $senderName])
                ->setTo($to)
                ->setSubject($subject)
                ->setTextBody($message);
            
            if (isset($options['html_body'])) {
                $compose->setHtmlBody($options['html_body']);
            }
            
            // Для отладки
            Yii::info("Sending email to: {$to}, subject: {$subject}, from: {$senderEmail}", 'notification');
            
            $result = $compose->send();
            
            if ($result) {
                Yii::info("Email sent successfully to: {$to}", 'notification');
            } else {
                Yii::error("Email send failed to: {$to}", 'notification');
            }
            
            return $result;
        } catch (\Exception $e) {
            Yii::error('Email send failed: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'notification');
            return false;
        }
    }
    
    public function getName()
    {
        return 'email';
    }
    
    public function getDescription()
    {
        return 'Email уведомления';
    }
    
    public function isAvailable()
    {
        // Проверяем, настроен ли mailer
        return Yii::$app->has('mailer') && $this->mailer !== null;
    }
}