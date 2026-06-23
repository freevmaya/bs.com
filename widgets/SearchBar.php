<?php

namespace app\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use app\models\Advertisement;

class SearchBar extends Widget
{
    /**
     * @var \app\models\AdvertisementSearch
     */
    public $searchModel;
    
    /**
     * @var string|null Секция (sell/buy)
     */
    public $section = null;
    
    /**
     * @var string Действие формы
     */
    public $action = ['index'];
    
    public function init()
    {
        parent::init();
        if ($this->searchModel === null) {
            $this->searchModel = new \app\models\AdvertisementSearch();
        }
    }
    
    public function run()
    {
        return $this->render('search-bar', [
            'searchModel' => $this->searchModel,
            'section' => $this->section,
            'action' => $this->action,
        ]);
    }
}