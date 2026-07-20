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
            [['title'], 'string', 'max' => 200],
            [['user_id', 'views_count', 'invitation_token_created_at'], 'integer'],
            [['type'], 'in', 'range' => [self::TYPE_NORMAL, self::TYPE_GLIDER, self::TYPE_HARNESS, self::TYPE_DEVICE]],
            [['price'], 'number', 'min' => 0],
            [['price_negotiable'], 'boolean'],
            [['description'], 'string'],
            [['section'], 'in', 'range' => [self::SECTION_SELL, self::SECTION_BUY]],
            [['status'], 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_MODERATION, self::STATUS_CLOSED]],
            [['city', 'phone', 'email', 'telegram', 'vk_profile_url', 'whatsapp', 'source_url', 'invitation_token'], 'string', 'max' => 255],
            [['email'], 'email'],
            [['phone'], 'match', 'pattern' => '/^[\d\s\+\(\)\-]*$/', 'message' => 'Телефон может содержать только цифры, пробелы, +, (, ), -'],
            [['telegram'], 'match', 'pattern' => '/^@?[a-zA-Z0-9_]{5,32}$/', 'message' => 'Введите корректный username Telegram (например: @username или username)'],
            // ИСПРАВЛЕНО: добавляем skipOnEmpty => true и указываем, что это метод валидации
            [['vk_profile_url'], 'validateVkProfileUrl', 'skipOnEmpty' => true],
            [['whatsapp'], 'match', 'pattern' => '/^[\d\s\+\(\)\-]{5,20}$/', 'message' => 'Введите корректный номер WhatsApp'],
            [['source_url'], 'url', 'message' => 'Введите корректный URL (например: https://example.com)'],
            [['invitation_token'], 'unique', 'message' => 'Этот токен уже используется'],
        ];
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
            'invitation_token' => 'Токен приглашения',
            'invitation_token_created_at' => 'Дата создания токена',
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
        
        // Проверяем, что ссылка ведет на VK
        if (!preg_match('/^https?:\/\/(?:www\.)?vk\.com\/(?:id\d+|[\w\.]+)$/i', $this->$attribute)) {
            $this->addError($attribute, 'Введите корректную ссылку на профиль VK (например: https://vk.com/durov)');
        }
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
    
    public function fillContactsFromUser($user)
    {
        if (!$user) {
            return;
        }
        
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

    public static function getTypeList()
    {
        return [
            self::TYPE_NORMAL => 'Обычное объявление',
            self::TYPE_GLIDER => 'Параплан',
            self::TYPE_HARNESS => 'Подвесная система',
            self::TYPE_DEVICE => 'Прибор',
        ];
    }

    public function generateTitle()
    {
        if (!empty($this->title) && $this->type === self::TYPE_NORMAL) {
            return $this->title;
        }

        if ($this->type === self::TYPE_NORMAL) {
            if (!empty($this->title)) {
                return $this->title;
            }
            $sectionLabel = $this->section === self::SECTION_SELL ? 'Продам' : 'Куплю';
            return $sectionLabel . ' ' . ($this->type ? $this->getTypeLabel() : 'объявление');
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
        
        if (empty(trim($title))) {
            $sectionLabel = $this->section === self::SECTION_SELL ? 'Продам' : 'Куплю';
            $title = $sectionLabel . ' ' . ($this->type ? $this->getTypeLabel() : 'объявление');
        }
        
        return $title;
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($this->type === self::TYPE_GLIDER && !$this->isRelationPopulated('glider')) {
                $this->populateRelation('glider', $this->getGlider()->one());
            } elseif ($this->type === self::TYPE_HARNESS && !$this->isRelationPopulated('harness')) {
                $this->populateRelation('harness', $this->getHarness()->one());
            } elseif ($this->type === self::TYPE_DEVICE && !$this->isRelationPopulated('device')) {
                $this->populateRelation('device', $this->getDevice()->one());
            }
            
            if ($this->type !== self::TYPE_NORMAL) {
                $this->title = $this->generateTitle();
            } elseif (empty($this->title)) {
                $this->title = $this->generateTitle();
            }
            return true;
        }
        return false;
    }

    // ============================================================
    // НОВЫЕ МЕТОДЫ ДЛЯ РАБОТЫ С ТИПАМИ
    // ============================================================

    /**
     * Возвращает объект типа (glider, harness, device) или null
     * 
     * @return BaseAdvertisementType|null
     */
    public function getTypeObject()
    {
        switch ($this->type) {
            case self::TYPE_GLIDER:
                return $this->glider;
            case self::TYPE_HARNESS:
                return $this->harness;
            case self::TYPE_DEVICE:
                return $this->device;
            default:
                return null;
        }
    }

    /**
     * Возвращает краткую строку с характеристиками объявления в зависимости от типа
     * Делегирует вызов к соответствующему объекту типа
     * 
     * @param string $separator Разделитель между элементами (по умолчанию ' | ')
     * @return string
     */
    public function getShortInfoString($separator = ' | ')
    {
        $typeObject = $this->getTypeObject();
        if ($typeObject) {
            return $typeObject->getShortInfoString($separator);
        }
        return '';
    }

    /**
     * Возвращает название типа товара
     * Делегирует вызов к соответствующему объекту типа
     * 
     * @return string
     */
    public function getTypeLabel()
    {
        $typeObject = $this->getTypeObject();
        if ($typeObject) {
            return $typeObject->getTypeLabel();
        }
        return 'Обычное объявление';
    }

    /**
     * Проверяет, есть ли у объявления объект типа
     * 
     * @return bool
     */
    public function hasTypeObject()
    {
        return $this->getTypeObject() !== null;
    }

    // ============================================================
    // МЕТОДЫ ДЛЯ РАБОТЫ С ПРИГЛАШЕНИЯМИ
    // ============================================================

    /**
     * Генерирует новый токен приглашения
     * 
     * @return string Сгенерированный GUID
     */
    public function generateInvitationToken()
    {
        $this->invitation_token = $this->generateGUID();
        $this->invitation_token_created_at = time();
        return $this->invitation_token;
    }

    /**
     * Генерирует GUID (UUID v4)
     * 
     * @return string
     */
    private function generateGUID()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * Проверяет, действителен ли токен приглашения
     * Токен действителен 7 дней (604800 секунд)
     * 
     * @return bool
     */
    public function isInvitationTokenValid()
    {
        if (empty($this->invitation_token) || empty($this->invitation_token_created_at)) {
            return false;
        }
        // Токен действителен 7 дней
        return (time() - $this->invitation_token_created_at) < 7 * 24 * 60 * 60;
    }

    /**
     * Очищает токен приглашения
     */
    public function clearInvitationToken()
    {
        $this->invitation_token = null;
        $this->invitation_token_created_at = null;
    }

    /**
     * Получить ссылку-приглашение
     * 
     * @return string|null URL ссылки или null если токен не сгенерирован
     */
    public function getInvitationLink()
    {
        if (empty($this->invitation_token)) {
            return null;
        }
        return Yii::$app->urlManager->createAbsoluteUrl([
            'site/register-invitation',
            'token' => $this->invitation_token
        ]);
    }
}