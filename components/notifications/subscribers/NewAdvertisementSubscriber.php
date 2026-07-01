<?php

namespace app\components\notifications\subscribers;

use Yii;
use yii\base\Event;
use app\components\notifications\events\NewAdvertisementEvent;
use app\components\notifications\NotificationManager;
use app\models\Advertisement;

class NewAdvertisementSubscriber
{
    private $notificationManager;
    
    public function __construct(NotificationManager $notificationManager)
    {
        $this->notificationManager = $notificationManager;
    }
    
    /**
     * Подписаться на события
     */
    public static function subscribe()
    {
        $manager = Yii::$app->notificationManager;
        
        Event::on(
            Advertisement::class,
            Advertisement::EVENT_AFTER_INSERT,
            function($event) use ($manager) {
                $instance = new self($manager);
                $instance->onNewAdvertisement($event);
            }
        );
    }
    
    /**
     * Обработчик нового объявления
     */
    public function onNewAdvertisement($event)
    {
        /** @var Advertisement $advertisement */
        $advertisement = $event->sender;
        
        // Создаем событие
        $newEvent = new NewAdvertisementEvent($advertisement);
        
        // Формируем сообщение
        $subject = "Новое объявление: {$advertisement->title}";
        $message = $this->buildMessage($advertisement);
        
        // Отправляем уведомления всем подписчикам
        $result = $this->notificationManager->sendToSubscribers(
            NewAdvertisementEvent::EVENT_NEW_ADVERTISEMENT,
            $subject,
            $message,
            [
                'html_body' => $this->buildHtmlMessage($advertisement),
            ]
        );
        
        Yii::info("New advertisement notification sent to {$result['sent']} of {$result['total']} subscribers", 'notification');
    }
    
    /**
     * Собрать текстовое сообщение
     */
    protected function buildMessage($advertisement)
    {
        $parts = [
            "Новое объявление на сайте " . Yii::$app->name,
            "",
            "Заголовок: {$advertisement->title}",
            "Раздел: " . $advertisement->getSectionLabel(),
            "Цена: " . ($advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана'),
            "Город: " . ($advertisement->city ?: 'не указан'),
            "",
            "Описание:",
            $advertisement->description ?: 'не указано',
            "",
            "Ссылка: " . Yii::$app->urlManager->createAbsoluteUrl(['advertisements/view', 'id' => $advertisement->id]),
        ];
        
        return implode("\n", $parts);
    }
    
    /**
     * Собрать HTML сообщение
     */
    protected function buildHtmlMessage($advertisement)
    {
        $price = $advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана';
        $link = Yii::$app->urlManager->createAbsoluteUrl(['advertisements/view', 'id' => $advertisement->id]);
        
        return "
            <html>
            <head><style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #007bff; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .price { font-size: 24px; color: #d9534f; font-weight: bold; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            </style></head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>Новое объявление</h2>
                    </div>
                    <div class='content'>
                        <h3>{$advertisement->title}</h3>
                        <p><strong>Раздел:</strong> {$advertisement->getSectionLabel()}</p>
                        <p class='price'>{$price}</p>
                        <p><strong>Город:</strong> {$advertisement->city}</p>
                        <p><strong>Описание:</strong></p>
                        <p>" . nl2br($advertisement->description) . "</p>
                        <p style='margin-top: 20px;'>
                            <a href='{$link}' class='btn'>Посмотреть объявление</a>
                        </p>
                    </div>
                </div>
            </body>
            </html>
        ";
    }
}