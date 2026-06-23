<?php

namespace app\models;

use yii\base\Model;
use yii\data\ActiveDataProvider;

class AdvertisementSearch extends Advertisement
{
    public $search_text;
    public $price_min;
    public $price_max;
    
    public function rules()
    {
        return [
            [['id', 'user_id', 'views_count', 'price_min', 'price_max'], 'integer'],
            [['section', 'title', 'description', 'city', 'status', 'search_text', 'type'], 'safe'],
            [['price'], 'number'],
        ];
    }
    
    public function scenarios()
    {
        return Model::scenarios();
    }
    
    public function search($params, $section = null)
    {
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
        
        if (!$this->validate()) {
            return $dataProvider;
        }
        
        // Поиск по тексту
        if ($this->search_text) {
            $query->andWhere(['or',
                ['like', 'title', $this->search_text],
                ['like', 'description', $this->search_text],
            ]);
        }
        
        // Фильтр по разделу
        $query->andFilterWhere(['section' => $this->section]);
        
        // Фильтр по городу
        $query->andFilterWhere(['like', 'city', $this->city]);
        
        // Фильтр по типу
        $query->andFilterWhere(['type' => $this->type]);
        
        // Фильтр по цене (от и до)
        if ($this->price_min !== null && $this->price_min !== '') {
            $query->andWhere(['>=', 'price', $this->price_min]);
        }
        if ($this->price_max !== null && $this->price_max !== '') {
            $query->andWhere(['<=', 'price', $this->price_max]);
        }
        
        return $dataProvider;
    }
}