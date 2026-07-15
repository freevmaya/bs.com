<?php
namespace app\components;

use Yii;
use yii\base\Component;

class TelegramWebhook extends Component
{
    public function handle()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (isset($data['message'])) {
            $chatId = $data['message']['chat']['id'];
            $username = $data['message']['chat']['username'] ?? null;
            
            // Ищем пользователя по username в БД
            if ($username) {
                $user = \app\models\User::find()->where(['telegram' => $username])->one();
                if ($user) {
                    // Сохраняем chat_id в отдельное поле или просто логируем
                    Yii::info("Telegram chat_id for @{$username}: {$chatId}", 'telegram');
                }
            }
        }
    }
}