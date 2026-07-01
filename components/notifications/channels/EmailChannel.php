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
            $compose = $this->mailer->compose()
                ->setTo($to)
                ->setSubject($subject)
                ->setTextBody($message);
            
            if (isset($options['html_body'])) {
                $compose->setHtmlBody($options['html_body']);
            }
            
            return $compose->send();
        } catch (\Exception $e) {
            Yii::error('Email send failed: ' . $e->getMessage(), 'notification');
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
        return Yii::$app->has('mailer');
    }
}