<?php
// FILE: .\models\Advertisement.php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Advertisement extends ActiveRecord
{
    const SECTION_SELL = 'sell';
    const SECTION_BUY = 'buy';
    
    const STATUS_ACTIVE = 'active';
    const STATUS_MODERATION = 'moderation';
    const STATUS_CLOSED = 'closed';

    const TYPE_NORMAL = 'normal';
    const TYPE_GLIDER = 'glider';
    const TYPE_HARNESS = 'harness';
    const TYPE_DEVICE = 'device';
    
    public static function tableName()
    {
        return 'advertisements';
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
            ],
        ];
    }
    
    public function rules()
    {
        return [
            [['user_id', 'section'], 'required'],
            // title - НЕ обязателен (будет генерироваться автоматически)
            [['title'], 'string', 'max' => 200],
            [['user_id', 'views_count'], 'integer'],
            [['type'], 'in', 'range' => [self::TYPE_NORMAL, self::TYPE_GLIDER, self::TYPE_HARNESS, self::TYPE_DEVICE]],
            [['price'], 'number', 'min' => 0],
            [['price_negotiable'], 'boolean'],
            [['description'], 'string'],
            [['section'], 'in', 'range' => [self::SECTION_SELL, self::SECTION_BUY]],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_MODERATION, self::STATUS_CLOSED]],
            [['city', 'phone', 'email', 'telegram', 'vk_profile_url', 'whatsapp', 'source_url'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['phone'], 'match', 'pattern' => '/^[\d\s\+\(\)\-]*$/', 'message' => 'Телефон может содержать только цифры, пробелы, +, (, ), -'],
            [['telegram'], 'match', 'pattern' => '/^@?[a-zA-Z0-9_]{5,32}$/', 'message' => 'Введите корректный username Telegram (например: @username или username)'],
            [['vk_profile_url'], 'validateVkProfileUrl'],
            [['whatsapp'], 'match', 'pattern' => '/^[\d\s\+\(\)\-]{5,20}$/', 'message' => 'Введите корректный номер WhatsApp'],
            [['source_url'], 'url', 'message' => 'Введите корректный URL (например: https://example.com)'],
        ];
    }
    
    /**
     * Валидация ссылки на профиль VK
     */
    public function validateVkProfileUrl($attribute, $params)
    {
        if (empty($this->$attribute)) {
            return;
        }
        
        if (!preg_match('/^https?:\/\/(?:www\.)?vk\.com\/(?:id\d+|[\w\.]+)$/i', $this->$attribute)) {
            $this->addError($attribute, 'Введите корректную ссылку на профиль VK (например: https://vk.com/durov)');
        }
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Пользователь',
            'type' => 'Тип снаряжения',
            'section' => 'Раздел',
            'title' => 'Заголовок',
            'description' => 'Описание',
            'price' => 'Цена',
            'price_negotiable' => 'Цена договорная',
            'city' => 'Город',
            'phone' => 'Телефон',
            'email' => 'Email',
            'telegram' => 'Telegram',
            'vk_profile_url' => 'VK профиль',
            'whatsapp' => 'WhatsApp',
            'source_url' => 'Источник (URL)',
            'status' => 'Статус',
            'views_count' => 'Просмотры',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }
    
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }
    
    public function getImages()
    {
        return $this->hasMany(AdvertisementImage::class, ['advertisement_id' => 'id'])->orderBy(['sort_order' => SORT_ASC]);
    }
    
    public function getMainImage()
    {
        return $this->hasOne(AdvertisementImage::class, ['advertisement_id' => 'id'])->orderBy(['sort_order' => SORT_ASC]);
    }
    
    public function getImageCount()
    {
        return $this->getImages()->count();
    }
    
    public function getMainImageUrl()
    {
        $mainImage = $this->mainImage;
        if ($mainImage) {
            return $mainImage->getThumbnailUrl();
        }
        return null;
    }
    
    public function getSectionLabel()
    {
        return $this->section === self::SECTION_SELL ? 'Продам' : 'Куплю';
    }
    
    public function getStatusLabel()
    {
        $labels = [
            self::STATUS_ACTIVE => 'Активно',
            self::STATUS_MODERATION => 'На модерации',
            self::STATUS_CLOSED => 'Закрыто',
        ];
        return $labels[$this->status] ?? $this->status;
    }
    
    public function incrementViews()
    {
        $this->views_count++;
        return $this->save(false, ['views_count']);
    }
    
    /**
     * Заполняет контакты из профиля пользователя
     */
    public function fillContactsFromUser($user)
    {
        if (!$user) {
            return;
        }
        
        // Заполняем только если поля пустые
        if (empty($this->phone) && !empty($user->phone)) {
            $this->phone = $user->phone;
        }
        if (empty($this->email) && !empty($user->email)) {
            $this->email = $user->email;
        }
        if (empty($this->telegram) && !empty($user->telegram)) {
            $this->telegram = $user->telegram;
        }
        if (empty($this->vk_profile_url) && !empty($user->vk_profile_url)) {
            $this->vk_profile_url = $user->vk_profile_url;
        }
        if (empty($this->whatsapp) && !empty($user->whatsapp)) {
            $this->whatsapp = $user->whatsapp;
        }
    }
    
    public function afterDelete()
    {
        foreach ($this->images as $image) {
            $image->delete();
        }
        parent::afterDelete();
    }

    public function getGlider()
    {
        return $this->hasOne(AdvertisementGlider::class, ['advertisement_id' => 'id']);
    }

    public function getHarness()
    {
        return $this->hasOne(AdvertisementHarness::class, ['advertisement_id' => 'id']);
    }

    public function getDevice()
    {
        return $this->hasOne(AdvertisementDevice::class, ['advertisement_id' => 'id']);
    }

    public function getTypeLabel()
    {
        $labels = [
            self::TYPE_NORMAL => 'Обычное',
            self::TYPE_GLIDER => 'Параплан',
            self::TYPE_HARNESS => 'Подвесная система',
            self::TYPE_DEVICE => 'Прибор',
        ];
        return $labels[$this->type] ?? $this->type;
    }

    public static function getTypeList()
    {
        return [
            self::TYPE_NORMAL => 'Обычное объявление',
            self::TYPE_GLIDER => 'Параплан',
            self::TYPE_HARNESS => 'Подвесная система',
            self::TYPE_DEVICE => 'Прибор',
        ];
    }

    /**
     * Генерирует заголовок на основе типа и данных из связанных моделей
     */
    public function generateTitle()
    {
        if (!empty($this->title)) {
            return $this->title;
        }

        $modelName = '';
        $producerName = '';

        if ($this->type === self::TYPE_GLIDER && $this->glider) {
            $modelName = $this->glider->model;
            if ($this->glider->producer) {
                $producerName = $this->glider->producer->short ?? $this->glider->producer->name;
            }
        } elseif ($this->type === self::TYPE_HARNESS && $this->harness) {
            $modelName = $this->harness->model;
            if ($this->harness->producer) {
                $producerName = $this->harness->producer->short ?? $this->harness->producer->name;
            }
        } elseif ($this->type === self::TYPE_DEVICE && $this->device) {
            $modelName = $this->device->model;
            if ($this->device->producer) {
                $producerName = $this->device->producer->short ?? $this->device->producer->name;
            }
        }

        $parts = [];
        if ($producerName) {
            $parts[] = $producerName;
        }
        if ($modelName) {
            $parts[] = $modelName;
        }

        $title = implode(' ', $parts);
        
        // Если заголовок все еще пустой, используем стандартный
        if (empty(trim($title))) {
            $sectionLabel = $this->section === self::SECTION_SELL ? 'Продам' : 'Куплю';
            $title = $sectionLabel . ' ' . ($this->type ? $this->getTypeLabel() : 'объявление');
        }
        
        return $title;
    }

    /**
     * Генерирует заголовок перед сохранением, если он пустой
     */
    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            // Если это НЕ обычное объявление, ВСЕГДА генерируем заголовок
            if ($this->type !== self::TYPE_NORMAL) {
                // Загружаем связанные модели, если они еще не загружены
                if ($this->type === self::TYPE_GLIDER && !$this->isRelationPopulated('glider')) {
                    $this->populateRelation('glider', $this->getGlider()->one());
                } elseif ($this->type === self::TYPE_HARNESS && !$this->isRelationPopulated('harness')) {
                    $this->populateRelation('harness', $this->getHarness()->one());
                } elseif ($this->type === self::TYPE_DEVICE && !$this->isRelationPopulated('device')) {
                    $this->populateRelation('device', $this->getDevice()->one());
                }
                
                // Генерируем заголовок, перезаписывая любое значение, введенное пользователем
                $this->title = $this->generateTitle();
            } 
            // Для обычных объявлений генерируем только если поле пустое
            elseif (empty($this->title)) {
                $this->title = $this->generateTitle();
            }
            return true;
        }
        return false;
    }
}