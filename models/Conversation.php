<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Conversation extends ActiveRecord
{
    public static function tableName()
    {
        return 'conversations';
    }
    
    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }
    
    public function rules()
    {
        return [
            [['advertisement_id', 'user1_id', 'user2_id'], 'required'],
            [['advertisement_id', 'user1_id', 'user2_id', 'last_message_at'], 'integer'],
            [['is_active'], 'boolean'],
            ['user1_id', 'compare', 'compareAttribute' => 'user2_id', 'operator' => '!=', 'message' => 'Нельзя создать диалог с самим собой'],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'advertisement_id' => 'Объявление',
            'user1_id' => 'Пользователь 1',
            'user2_id' => 'Пользователь 2',
            'last_message_at' => 'Последнее сообщение',
            'is_active' => 'Активен',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }
    
    public function getAdvertisement()
    {
        return $this->hasOne(Advertisement::class, ['id' => 'advertisement_id']);
    }
    
    public function getUser1()
    {
        return $this->hasOne(User::class, ['id' => 'user1_id']);
    }
    
    public function getUser2()
    {
        return $this->hasOne(User::class, ['id' => 'user2_id']);
    }
    
    public function getMessages()
    {
        return $this->hasMany(Message::class, ['conversation_id' => 'id'])->orderBy(['created_at' => SORT_ASC]);
    }
    
    public function getLastMessage()
    {
        return $this->hasOne(Message::class, ['conversation_id' => 'id'])->orderBy(['created_at' => SORT_DESC]);
    }
    
    public function getUnreadCount($userId)
    {
        return Message::find()
            ->where([
                'conversation_id' => $this->id,
                'receiver_id' => $userId,
                'is_read' => 0,
            ])
            ->count();
    }
    
    public function getOtherUser($userId)
    {
        if ($this->user1_id == $userId) {
            return $this->user2;
        }
        return $this->user1;
    }
    
    public function getOtherUserId($userId)
    {
        if ($this->user1_id == $userId) {
            return $this->user2_id;
        }
        return $this->user1_id;
    }
    
    /**
     * Находит или создает диалог между двумя пользователями
     * 
     * @param int $advertisementId ID объявления
     * @param int $user1Id ID первого пользователя (обычно текущий)
     * @param int $user2Id ID второго пользователя (обычно автор объявления)
     * @return Conversation|null
     */
    public static function findOrCreate($advertisementId, $user1Id, $user2Id)
    {
        if ($user1Id == $user2Id) {
            return null;
        }
        
        // Нормализуем порядок: меньший ID всегда user1, больший - user2
        $normalizedUser1 = min($user1Id, $user2Id);
        $normalizedUser2 = max($user1Id, $user2Id);
        
        // Находим существующий диалог с нормализованными ID
        $conversation = self::find()
            ->where([
                'advertisement_id' => $advertisementId,
                'user1_id' => $normalizedUser1,
                'user2_id' => $normalizedUser2,
                'is_active' => true,
            ])
            ->one();
        
        if ($conversation) {
            return $conversation;
        }
        
        // Проверяем неактивные диалоги (если пользователь закрыл диалог)
        $inactiveConversation = self::find()
            ->where([
                'advertisement_id' => $advertisementId,
                'user1_id' => $normalizedUser1,
                'user2_id' => $normalizedUser2,
                'is_active' => false,
            ])
            ->one();
        
        if ($inactiveConversation) {
            // Реактивируем диалог
            $inactiveConversation->is_active = true;
            $inactiveConversation->last_message_at = time();
            $inactiveConversation->save();
            return $inactiveConversation;
        }
        
        // Создаем новый диалог с нормализованными ID
        $conversation = new self();
        $conversation->advertisement_id = $advertisementId;
        $conversation->user1_id = $normalizedUser1;
        $conversation->user2_id = $normalizedUser2;
        $conversation->last_message_at = time();
        
        if ($conversation->save()) {
            return $conversation;
        }
        
        return null;
    }
    
    public function markAsRead($userId)
    {
        return Message::updateAll(
            ['is_read' => 1, 'read_at' => time()],
            [
                'conversation_id' => $this->id,
                'receiver_id' => $userId,
                'is_read' => 0,
            ]
        );
    }
    
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($this->isNewRecord) {
                // Нормализуем порядок перед сохранением
                if ($this->user1_id > $this->user2_id) {
                    $temp = $this->user1_id;
                    $this->user1_id = $this->user2_id;
                    $this->user2_id = $temp;
                }
                $this->last_message_at = time();
            }
            return true;
        }
        return false;
    }
}