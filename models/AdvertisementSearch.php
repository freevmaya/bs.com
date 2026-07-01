<?php

namespace app\models;

use Yii;
use yii\base\Model;
use yii\data\ActiveDataProvider;

class AdvertisementSearch extends Advertisement
{
    public $search_text;
    public $price_min;
    public $price_max;
    
    // Поля для фильтрации glider
    public $glider_model;
    public $glider_producer_ids = [];
    public $glider_certification_ids = [];
    public $glider_weight;
    public $glider_date_release_min;
    public $glider_flight_time_max;
    public $glider_condition;
    
    // Поля для фильтрации harness
    public $harness_model;
    public $harness_producer_ids = [];
    public $harness_sizes = [];
    public $harness_date_release_min;
    public $harness_condition;
    
    // Поля для фильтрации device
    public $device_model;
    public $device_producer_ids = [];
    public $device_condition;
    
    public function rules()
    {
        return [
            [['id', 'user_id', 'views_count', 'price_min', 'price_max'], 'integer'],
            [['section', 'title', 'description', 'city', 'status', 'search_text', 'type'], 'safe'],
            [['price'], 'number'],
            
            // Glider fields
            [['glider_model', 'glider_condition'], 'string'],
            [['glider_producer_ids', 'glider_certification_ids'], 'safe'],
            [['glider_weight', 'glider_date_release_min', 'glider_flight_time_max'], 'integer'],
            
            // Harness fields
            [['harness_model', 'harness_condition'], 'string'],
            [['harness_producer_ids', 'harness_sizes'], 'safe'],
            [['harness_date_release_min'], 'integer'],
            
            // Device fields
            [['device_model', 'device_condition'], 'string'],
            [['device_producer_ids'], 'safe'],
        ];
    }
    
    public function scenarios()
    {
        return Model::scenarios();
    }
    
    /**
     * Переопределяем метки атрибутов для отображения на русском языке
     */
    public function attributeLabels()
    {
        return [
            // Основные поля
            'search_text' => 'Поиск',
            'price_min' => 'Цена от',
            'price_max' => 'Цена до',
            'city' => 'Город',
            'type' => 'Тип оборудования',
            
            // Поля для GLIDER
            'glider_model' => 'Модель',
            'glider_producer_ids' => 'Производитель',
            'glider_certification_ids' => 'Сертификация',
            'glider_weight' => 'Взлетный вес',
            'glider_date_release_min' => 'Год выпуска от',
            'glider_flight_time_max' => 'Налёт до',
            'glider_condition' => 'Состояние',
            
            // Поля для HARNESS
            'harness_model' => 'Модель',
            'harness_producer_ids' => 'Производитель',
            'harness_sizes' => 'Размер',
            'harness_date_release_min' => 'Год выпуска от',
            'harness_condition' => 'Состояние',
            
            // Поля для DEVICE
            'device_model' => 'Модель',
            'device_producer_ids' => 'Производитель',
            'device_condition' => 'Состояние',
        ];
    }
    
    public function search($params, $section = null)
    {
        // Логируем входящие параметры
        Yii::info('=== SEARCH INPUT PARAMS ===', 'search');
        Yii::info('Raw params: ' . json_encode($params), 'search');
        
        $query = Advertisement::find()->where(['status' => Advertisement::STATUS_ACTIVE]);
        
        if ($section) {
            $query->andWhere(['section' => $section]);
        }
        
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'sort' => [
                'defaultOrder' => [
                    'created_at' => SORT_DESC,
                ],
            ],
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
        
        $this->load($params);
        
        // Логируем загруженные параметры
        Yii::info('Loaded search params: ' . json_encode($this->getAttributes()), 'search');
        
        if (!$this->validate()) {
            Yii::info('Validation errors: ' . json_encode($this->errors), 'search');
            return $dataProvider;
        }
        
        // Поиск по тексту
        if ($this->search_text) {
            $query->andWhere(['or',
                ['like', 'title', $this->search_text],
                ['like', 'description', $this->search_text],
            ]);
            Yii::info('Applied search_text filter: ' . $this->search_text, 'search');
        }
        
        // Фильтр по разделу
        $query->andFilterWhere(['section' => $this->section]);
        
        // Фильтр по городу
        $query->andFilterWhere(['like', 'city', $this->city]);
        
        // Фильтр по типу
        $query->andFilterWhere(['type' => $this->type]);
        Yii::info('Applied type filter: ' . $this->type, 'search');
        
        // Фильтр по цене (от и до)
        if ($this->price_min !== null && $this->price_min !== '') {
            $query->andWhere(['>=', 'price', $this->price_min]);
            Yii::info('Applied price_min filter: ' . $this->price_min, 'search');
        }
        if ($this->price_max !== null && $this->price_max !== '') {
            $query->andWhere(['<=', 'price', $this->price_max]);
            Yii::info('Applied price_max filter: ' . $this->price_max, 'search');
        }
        
        // Дополнительные фильтры в зависимости от типа
        $this->applyExtraFilters($query);
        
        // Логируем итоговый SQL
        Yii::info('Final SQL: ' . $query->createCommand()->getRawSql(), 'search');
        
        return $dataProvider;
    }
    
    /**
     * Применяет дополнительные фильтры в зависимости от типа
     */
    protected function applyExtraFilters($query)
    {
        $type = $this->type;
        
        Yii::info('=== START applyExtraFilters ===', 'search');
        Yii::info('Type: ' . $type, 'search');
        
        if ($type === self::TYPE_GLIDER) {
            Yii::info('Applying GLIDER filters', 'search');
            $this->applyGliderFilters($query);
        } elseif ($type === self::TYPE_HARNESS) {
            Yii::info('Applying HARNESS filters', 'search');
            $this->applyHarnessFilters($query);
        } elseif ($type === self::TYPE_DEVICE) {
            Yii::info('Applying DEVICE filters', 'search');
            $this->applyDeviceFilters($query);
        } else {
            Yii::info('No extra filters for type: ' . $type, 'search');
        }
        
        Yii::info('=== END applyExtraFilters ===', 'search');
    }
    
    /**
     * Фильтры для парапланов (glider)
     */
    protected function applyGliderFilters($query)
    {
        Yii::info('=== GLIDER FILTERS START ===', 'search');
        Yii::info('glider_model: ' . $this->glider_model, 'search');
        Yii::info('glider_producer_ids: ' . json_encode($this->glider_producer_ids), 'search');
        Yii::info('glider_certification_ids: ' . json_encode($this->glider_certification_ids), 'search');
        Yii::info('glider_weight: ' . $this->glider_weight, 'search');
        Yii::info('glider_date_release_min: ' . $this->glider_date_release_min, 'search');
        Yii::info('glider_flight_time_max: ' . $this->glider_flight_time_max, 'search');
        Yii::info('glider_condition: ' . $this->glider_condition, 'search');
        
        // JOIN с таблицей advertisement_glider
        $query->join('INNER JOIN', 'advertisement_glider', 
            'advertisement_glider.advertisement_id = advertisements.id');
        Yii::info('Added INNER JOIN to advertisement_glider', 'search');
        
        // Модель (похожие названия)
        if (!empty($this->glider_model)) {
            $query->andWhere(['like', 'advertisement_glider.model', $this->glider_model]);
            Yii::info('Added filter: model LIKE "%' . $this->glider_model . '%"', 'search');
        }
        
        // Производители (несколько)
        if (!empty($this->glider_producer_ids)) {
            if (!is_array($this->glider_producer_ids)) {
                $this->glider_producer_ids = [$this->glider_producer_ids];
            }
            
            // Фильтруем пустые значения
            $producerIds = array_filter($this->glider_producer_ids, function($id) {
                return $id !== '' && $id !== null && $id !== '0' && $id !== 0;
            });
            
            Yii::info('Filtered producer IDs: ' . json_encode($producerIds), 'search');
            
            if (!empty($producerIds)) {
                $query->andWhere(['in', 'advertisement_glider.producer_id', $producerIds]);
                Yii::info('Added filter: producer_id IN (' . implode(', ', $producerIds) . ')', 'search');
            }
        }
        
        // Сертификации (несколько)
        if (!empty($this->glider_certification_ids)) {
            if (!is_array($this->glider_certification_ids)) {
                $this->glider_certification_ids = [$this->glider_certification_ids];
            }
            
            $certIds = array_filter($this->glider_certification_ids, function($id) {
                return $id !== '' && $id !== null && $id !== '0' && $id !== 0;
            });
            
            Yii::info('Filtered certification IDs: ' . json_encode($certIds), 'search');
            
            if (!empty($certIds)) {
                $query->andWhere(['in', 'advertisement_glider.certification_id', $certIds]);
                Yii::info('Added filter: certification_id IN (' . implode(', ', $certIds) . ')', 'search');
            }
        }
        
        // Взлетный вес (должен быть в пределах weight_min - weight_max)
        if ($this->glider_weight !== null && $this->glider_weight !== '') {
            $query->andWhere(['<=', 'advertisement_glider.weight_min', $this->glider_weight]);
            $query->andWhere(['>=', 'advertisement_glider.weight_max', $this->glider_weight]);
            Yii::info('Added filter: weight_min <= ' . $this->glider_weight . ' AND weight_max >= ' . $this->glider_weight, 'search');
        }
        
        // Минимальная дата выпуска
        if (!empty($this->glider_date_release_min)) {
            $query->andWhere(['>=', 'advertisement_glider.date_release', $this->glider_date_release_min]);
            Yii::info('Added filter: date_release >= ' . $this->glider_date_release_min, 'search');
        }
        
        // Максимальный налет
        if ($this->glider_flight_time_max !== null && $this->glider_flight_time_max !== '') {
            $query->andWhere(['<=', 'advertisement_glider.flight_time', $this->glider_flight_time_max]);
            Yii::info('Added filter: flight_time <= ' . $this->glider_flight_time_max, 'search');
        }
        
        // Состояние
        if (!empty($this->glider_condition)) {
            $query->andWhere(['advertisement_glider.condition' => $this->glider_condition]);
            Yii::info('Added filter: condition = ' . $this->glider_condition, 'search');
        }
    }
    
    /**
     * Фильтры для подвесных систем (harness)
     */
    protected function applyHarnessFilters($query)
    {
        Yii::info('=== HARNESS FILTERS START ===', 'search');
        Yii::info('harness_model: ' . $this->harness_model, 'search');
        Yii::info('harness_producer_ids: ' . json_encode($this->harness_producer_ids), 'search');
        Yii::info('harness_sizes: ' . json_encode($this->harness_sizes), 'search');
        Yii::info('harness_date_release_min: ' . $this->harness_date_release_min, 'search');
        Yii::info('harness_condition: ' . $this->harness_condition, 'search');
        
        // JOIN с таблицей advertisement_harness
        $query->join('INNER JOIN', 'advertisement_harness', 
            'advertisement_harness.advertisement_id = advertisements.id');
        Yii::info('Added INNER JOIN to advertisement_harness', 'search');
        
        // Модель (похожие названия)
        if (!empty($this->harness_model)) {
            $query->andWhere(['like', 'advertisement_harness.model', $this->harness_model]);
            Yii::info('Added filter: model LIKE "%' . $this->harness_model . '%"', 'search');
        }
        
        // Производители (несколько)
        if (!empty($this->harness_producer_ids)) {
            if (!is_array($this->harness_producer_ids)) {
                $this->harness_producer_ids = [$this->harness_producer_ids];
            }
            
            $producerIds = array_filter($this->harness_producer_ids, function($id) {
                return $id !== '' && $id !== null && $id !== '0' && $id !== 0;
            });
            
            Yii::info('Filtered harness producer IDs: ' . json_encode($producerIds), 'search');
            
            if (!empty($producerIds)) {
                $query->andWhere(['in', 'advertisement_harness.producer_id', $producerIds]);
                Yii::info('Added filter: producer_id IN (' . implode(', ', $producerIds) . ')', 'search');
            }
        }
        
        // Размеры (несколько)
        if (!empty($this->harness_sizes)) {
            if (!is_array($this->harness_sizes)) {
                $this->harness_sizes = [$this->harness_sizes];
            }
            
            $sizes = array_filter($this->harness_sizes, function($size) {
                return $size !== '' && $size !== null && $size !== '0';
            });
            
            Yii::info('Filtered harness sizes: ' . json_encode($sizes), 'search');
            
            if (!empty($sizes)) {
                $query->andWhere(['in', 'advertisement_harness.size', $sizes]);
                Yii::info('Added filter: size IN (' . implode(', ', $sizes) . ')', 'search');
            }
        }
        
        // Минимальная дата выпуска
        if (!empty($this->harness_date_release_min)) {
            $query->andWhere(['>=', 'advertisement_harness.date_release', $this->harness_date_release_min]);
            Yii::info('Added filter: date_release >= ' . $this->harness_date_release_min, 'search');
        }
        
        // Состояние
        if (!empty($this->harness_condition)) {
            $query->andWhere(['advertisement_harness.condition' => $this->harness_condition]);
            Yii::info('Added filter: condition = ' . $this->harness_condition, 'search');
        }
    }
    
    /**
     * Фильтры для приборов (device)
     */
    protected function applyDeviceFilters($query)
    {
        Yii::info('=== DEVICE FILTERS START ===', 'search');
        Yii::info('device_model: ' . $this->device_model, 'search');
        Yii::info('device_producer_ids: ' . json_encode($this->device_producer_ids), 'search');
        Yii::info('device_condition: ' . $this->device_condition, 'search');
        
        // JOIN с таблицей advertisement_device
        $query->join('INNER JOIN', 'advertisement_device', 
            'advertisement_device.advertisement_id = advertisements.id');
        Yii::info('Added INNER JOIN to advertisement_device', 'search');
        
        // Модель (похожие названия)
        if (!empty($this->device_model)) {
            $query->andWhere(['like', 'advertisement_device.model', $this->device_model]);
            Yii::info('Added filter: model LIKE "%' . $this->device_model . '%"', 'search');
        }
        
        // Производители (несколько)
        if (!empty($this->device_producer_ids)) {
            if (!is_array($this->device_producer_ids)) {
                $this->device_producer_ids = [$this->device_producer_ids];
            }
            
            $producerIds = array_filter($this->device_producer_ids, function($id) {
                return $id !== '' && $id !== null && $id !== '0' && $id !== 0;
            });
            
            Yii::info('Filtered device producer IDs: ' . json_encode($producerIds), 'search');
            
            if (!empty($producerIds)) {
                $query->andWhere(['in', 'advertisement_device.producer_id', $producerIds]);
                Yii::info('Added filter: producer_id IN (' . implode(', ', $producerIds) . ')', 'search');
            }
        }
        
        // Состояние
        if (!empty($this->device_condition)) {
            $query->andWhere(['advertisement_device.condition' => $this->device_condition]);
            Yii::info('Added filter: condition = ' . $this->device_condition, 'search');
        }
    }
}