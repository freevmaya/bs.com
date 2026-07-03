<?php
// FILE: .\models\SearchSubscription.php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class SearchSubscription extends ActiveRecord
{
    public static function tableName()
    {
        return 'search_subscriptions';
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
            [['user_id', 'params', 'section'], 'required'],
            [['user_id'], 'integer'],
            [['params'], 'string'],
            [['section'], 'string', 'max' => 20],
            [['is_active'], 'boolean'],
            [['last_notified_at'], 'integer'],
            ['user_id', 'exist', 'targetClass' => User::class, 'targetAttribute' => 'id'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Пользователь',
            'params' => 'Параметры поиска',
            'section' => 'Раздел',
            'is_active' => 'Активна',
            'last_notified_at' => 'Последнее уведомление',
            'created_at' => 'Создано',
            'updated_at' => 'Обновлено',
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Получить параметры поиска в виде массива
     */
    public function getParamsArray()
    {
        return json_decode($this->params, true) ?: [];
    }

    /**
     * Установить параметры из массива
     */
    public function setParamsArray($params)
    {
        $this->params = json_encode($params, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Получить человекочитаемое описание параметров
     */
    public function getDescription()
    {
        $params = $this->getParamsArray();
        $parts = [];

        if (!empty($params['search_text'])) {
            $parts[] = 'Поиск: "' . $params['search_text'] . '"';
        }
        if (!empty($params['price_min'])) {
            $parts[] = 'Цена от ' . $params['price_min'] . ' ₽';
        }
        if (!empty($params['price_max'])) {
            $parts[] = 'Цена до ' . $params['price_max'] . ' ₽';
        }
        if (!empty($params['city'])) {
            $parts[] = 'Город: ' . $params['city'];
        }
        if (!empty($params['type']) && $params['type'] !== 'normal') {
            $typeList = Advertisement::getTypeList();
            $parts[] = 'Тип: ' . ($typeList[$params['type']] ?? $params['type']);
        }

        // Параметры для glider
        if (!empty($params['glider_model'])) {
            $parts[] = 'Модель: ' . $params['glider_model'];
        }
        if (!empty($params['glider_producer_ids'])) {
            $producerNames = \yii\helpers\ArrayHelper::getColumn(
                Producer::find()->where(['id' => $params['glider_producer_ids']])->all(),
                'name'
            );
            if (!empty($producerNames)) {
                $parts[] = 'Производитель: ' . implode(', ', $producerNames);
            }
        }
        if (!empty($params['glider_certification_ids'])) {
            $certNames = \yii\helpers\ArrayHelper::getColumn(
                Certification::find()->where(['id' => $params['glider_certification_ids']])->all(),
                'name'
            );
            $parts[] = 'Сертификация: ' . implode(', ', $certNames);
        }
        if (!empty($params['glider_weight'])) {
            $parts[] = 'Вес: ' . $params['glider_weight'] . ' кг';
        }
        if (!empty($params['glider_date_release_min'])) {
            $parts[] = 'Год выпуска от: ' . $params['glider_date_release_min'];
        }
        if (!empty($params['glider_flight_time_max'])) {
            $parts[] = 'Налёт до: ' . $params['glider_flight_time_max'] . ' ч';
        }
        if (!empty($params['glider_condition'])) {
            $conditionList = AdvertisementGlider::getConditionList();
            $parts[] = 'Состояние: ' . ($conditionList[$params['glider_condition']] ?? $params['glider_condition']);
        }

        // Параметры для harness
        if (!empty($params['harness_model'])) {
            $parts[] = 'Модель: ' . $params['harness_model'];
        }
        if (!empty($params['harness_producer_ids'])) {
            $producerNames = \yii\helpers\ArrayHelper::getColumn(
                Producer::find()->where(['id' => $params['harness_producer_ids']])->all(),
                'name'
            );
            $parts[] = 'Производитель: ' . implode(', ', $producerNames);
        }
        if (!empty($params['harness_sizes'])) {
            $parts[] = 'Размер: ' . implode(', ', $params['harness_sizes']);
        }
        if (!empty($params['harness_date_release_min'])) {
            $parts[] = 'Год выпуска от: ' . $params['harness_date_release_min'];
        }
        if (!empty($params['harness_condition'])) {
            $conditionList = AdvertisementHarness::getConditionList();
            $parts[] = 'Состояние: ' . ($conditionList[$params['harness_condition']] ?? $params['harness_condition']);
        }

        // Параметры для device
        if (!empty($params['device_model'])) {
            $parts[] = 'Модель: ' . $params['device_model'];
        }
        if (!empty($params['device_producer_ids'])) {
            $producerNames = \yii\helpers\ArrayHelper::getColumn(
                Producer::find()->where(['id' => $params['device_producer_ids']])->all(),
                'name'
            );
            $parts[] = 'Производитель: ' . implode(', ', $producerNames);
        }
        if (!empty($params['device_condition'])) {
            $conditionList = AdvertisementDevice::getConditionList();
            $parts[] = 'Состояние: ' . ($conditionList[$params['device_condition']] ?? $params['device_condition']);
        }

        return $parts ? implode(', ', $parts) : 'Все объявления';
    }

    /**
     * Получить подписки пользователя
     */
    public static function getUserSubscriptions($userId)
    {
        return self::find()
            ->where(['user_id' => $userId, 'is_active' => true])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();
    }

    /**
     * Получить подписки для раздела
     */
    public static function getSubscriptionsForSection($section)
    {
        return self::find()
            ->where(['section' => $section, 'is_active' => true])
            ->all();
    }

    /**
     * Проверить, соответствует ли объявление параметрам подписки
     */
    public function matchesAdvertisement($advertisement)
    {
        $params = $this->getParamsArray();

        // Проверка текста поиска
        if (!empty($params['search_text'])) {
            $searchText = mb_strtolower($params['search_text']);
            $title = mb_strtolower($advertisement->title);
            $description = mb_strtolower($advertisement->description ?? '');
            if (strpos($title, $searchText) === false && strpos($description, $searchText) === false) {
                return false;
            }
        }

        // Проверка цены
        if (!empty($params['price_min']) && $advertisement->price < $params['price_min']) {
            return false;
        }
        if (!empty($params['price_max']) && $advertisement->price > $params['price_max']) {
            return false;
        }

        // Проверка города
        if (!empty($params['city']) && mb_strtolower($advertisement->city) !== mb_strtolower($params['city'])) {
            return false;
        }

        // Проверка типа
        if (!empty($params['type']) && $params['type'] !== 'normal' && $advertisement->type !== $params['type']) {
            return false;
        }

        // Проверка дополнительных полей в зависимости от типа
        $type = $advertisement->type;
        if ($type === 'glider' && $advertisement->glider) {
            return $this->matchesGlider($advertisement->glider, $params);
        } elseif ($type === 'harness' && $advertisement->harness) {
            return $this->matchesHarness($advertisement->harness, $params);
        } elseif ($type === 'device' && $advertisement->device) {
            return $this->matchesDevice($advertisement->device, $params);
        }

        return true;
    }

    protected function matchesGlider($glider, $params)
    {
        if (!empty($params['glider_model']) && stripos($glider->model, $params['glider_model']) === false) {
            return false;
        }
        if (!empty($params['glider_producer_ids']) && !in_array($glider->producer_id, $params['glider_producer_ids'])) {
            return false;
        }
        if (!empty($params['glider_certification_ids']) && !in_array($glider->certification_id, $params['glider_certification_ids'])) {
            return false;
        }
        if (!empty($params['glider_weight'])) {
            $weight = $params['glider_weight'];
            if ($glider->weight_min !== null && $glider->weight_min > $weight) {
                return false;
            }
            if ($glider->weight_max !== null && $glider->weight_max < $weight) {
                return false;
            }
        }
        if (!empty($params['glider_date_release_min']) && $glider->date_release < $params['glider_date_release_min']) {
            return false;
        }
        if (!empty($params['glider_flight_time_max']) && $glider->flight_time > $params['glider_flight_time_max']) {
            return false;
        }
        if (!empty($params['glider_condition']) && $glider->condition !== $params['glider_condition']) {
            return false;
        }
        return true;
    }

    protected function matchesHarness($harness, $params)
    {
        if (!empty($params['harness_model']) && stripos($harness->model, $params['harness_model']) === false) {
            return false;
        }
        if (!empty($params['harness_producer_ids']) && !in_array($harness->producer_id, $params['harness_producer_ids'])) {
            return false;
        }
        if (!empty($params['harness_sizes']) && !in_array($harness->size, $params['harness_sizes'])) {
            return false;
        }
        if (!empty($params['harness_date_release_min']) && $harness->date_release < $params['harness_date_release_min']) {
            return false;
        }
        if (!empty($params['harness_condition']) && $harness->condition !== $params['harness_condition']) {
            return false;
        }
        return true;
    }

    protected function matchesDevice($device, $params)
    {
        if (!empty($params['device_model']) && stripos($device->model, $params['device_model']) === false) {
            return false;
        }
        if (!empty($params['device_producer_ids']) && !in_array($device->producer_id, $params['device_producer_ids'])) {
            return false;
        }
        if (!empty($params['device_condition']) && $device->condition !== $params['device_condition']) {
            return false;
        }
        return true;
    }
}