<?php

namespace app\components\notifications\events;

use app\models\Advertisement;
use yii\base\Event;

class NewAdvertisementEvent extends Event
{
    const EVENT_NEW_ADVERTISEMENT = 'new_advertisement';
    
    public $advertisement;
    public $userId;
    
    public function __construct(Advertisement $advertisement, $config = [])
    {
        $this->advertisement = $advertisement;
        parent::__construct($config);
    }
}