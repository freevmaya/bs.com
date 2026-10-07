<?php
// FILE: .\widgets\SimilarAdvertisements.php

namespace app\widgets;

use Yii;
use yii\base\Widget;
use app\models\Advertisement;
use app\components\SimilarAdvertisementService;

class SimilarAdvertisements extends Widget
{
    /**
     * @var Advertisement Текущее объявление
     */
    public $advertisement;

    /**
     * @var int Лимит объявлений
     */
    public $limit = 4;

    /**
     * @var int Минимум для показа блока
     */
    public $minResults = 2;

    /**
     * @var bool Использовать кэш
     */
    public $useCache = true;

    public function run()
    {
        if (!$this->advertisement instanceof Advertisement) {
            return '';
        }

        $service = new SimilarAdvertisementService();
        $service->limit = $this->limit;
        $service->minResults = $this->minResults;
        $service->useCache = $this->useCache;

        $similar = $service->findSimilar($this->advertisement);

        if (count($similar) < $this->minResults) {
            return '';
        }

        return $this->render('similar-advertisements', [
            'advertisements' => $similar,
        ]);
    }
}