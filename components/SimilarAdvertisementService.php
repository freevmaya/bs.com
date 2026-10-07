<?php
// FILE: .\components\SimilarAdvertisementService.php

namespace app\components;

use Yii;
use app\models\Advertisement;

/**
 * Простой сервис подбора похожих объявлений
 *
 * Правила:
 *   1. Похожие по названию (LIKE по значимым словам из заголовка)
 *   2. Похожие по сертификации (для парапланов)
 */
class SimilarAdvertisementService
{
    /**
     * @var int Сколько объявлений показывать
     */
    public $limit = 4;

    /**
     * @var array Стоп-слова, которые не учитываются при поиске по названию
     */
    public $stopWords = [
        'продам', 'продаю', 'продается', 'продаётся', 'продажа',
        'куплю', 'покупаю', 'куплю',
        'крыло', 'крылья', 'параплан', 'параплане', 'параплана',
        'подвеска', 'подвеску', 'подвески',
        'прибор', 'приборы', 'прибора',
        'спарка', 'спарку',
        'новый', 'новая', 'новое', 'новые',
        'бу', 'б/у',
    ];

    /**
     * Найти похожие объявления
     *
     * @param Advertisement $advertisement
     * @return Advertisement[] Массив с полем match_reason
     */
   public function findSimilar(Advertisement $advertisement)
    {
        $collected = []; // id => ['ad' => obj, 'priority' => int, 'reason' => string]

        // 1. Похожие по названию
        $this->collectByTitle($collected, $advertisement);

        // 2. Похожие по сертификации (только для парапланов)
        if ($advertisement->type === Advertisement::TYPE_GLIDER && $advertisement->glider) {
            $this->collectByCertification($collected, $advertisement);
        }

        // 3. Если ничего не нашли — добавляем случайные объявления
        if (empty($collected)) {
            $this->collectRandom($collected, $advertisement);
        }

        return $this->finalize($collected);
    }

    /**
     * Найти по похожему названию
     */
    protected function collectByTitle(&$collected, Advertisement $advertisement)
    {
        if (empty($advertisement->title)) {
            return;
        }

        // Разбиваем заголовок на слова
        $words = preg_split('/\s+/u', mb_strtolower($advertisement->title));

        // Фильтруем: длина > 3, не стоп-слово, только буквы и цифры
        $words = array_filter($words, function ($w) {
            $w = trim($w, ".,;:!?()[]{}\"'«»");
            return mb_strlen($w) > 3 && !in_array($w, $this->stopWords, true);
        });

        // Убираем пунктуацию для поиска
        $words = array_map(function ($w) {
            return trim($w, ".,;:!?()[]{}\"'«»");
        }, $words);

        // Убираем пустые после тримминга
        $words = array_filter($words, function ($w) {
            return $w !== '' && !in_array($w, $this->stopWords, true);
        });

        if (empty($words)) {
            return;
        }

        // Берём максимум 3 слова
        $words = array_slice(array_values($words), 0, 3);

        // OR-условие: заголовок LIKE по каждому слову
        $orConditions = ['or'];
        foreach ($words as $word) {
            $orConditions[] = ['like', 'advertisements.title', $word];
        }

        $query = Advertisement::find()
            ->where(['status' => Advertisement::STATUS_ACTIVE])
            ->andWhere(['section' => $advertisement->section])
            ->andWhere(['type' => $advertisement->type])
            ->andWhere(['<>', 'advertisements.id', $advertisement->id])
            ->andWhere($orConditions)
            ->orderBy(['advertisements.updated_at' => SORT_DESC])
            ->with(['images', 'glider', 'harness', 'device'])
            ->limit(20);

        foreach ($query->all() as $similar) {
            $this->addToCollected($collected, $similar, 1, 'Похожее название');
        }
    }

    /**
     * Найти по сертификации (только для парапланов)
     */
    protected function collectByCertification(&$collected, Advertisement $advertisement)
    {
        $glider = $advertisement->glider;
        if (empty($glider->certification_id)) {
            return;
        }

        $query = Advertisement::find()
            ->where(['status' => Advertisement::STATUS_ACTIVE])
            ->andWhere(['section' => $advertisement->section])
            ->andWhere(['type' => Advertisement::TYPE_GLIDER])
            ->andWhere(['<>', 'advertisements.id', $advertisement->id])
            ->innerJoin('advertisement_glider', 'advertisement_glider.advertisement_id = advertisements.id')
            ->andWhere(['advertisement_glider.certification_id' => $glider->certification_id])
            ->orderBy(['advertisements.updated_at' => SORT_DESC])
            ->with(['images', 'glider'])
            ->limit(20);

        foreach ($query->all() as $similar) {
            $this->addToCollected($collected, $similar, 2, 'Та же сертификация');
        }
    }

    /**
     * Добавить случайные объявления того же раздела и типа
     *
     * Используется как fallback, если по правилам ничего не нашлось.
     *
     * @param array $collected
     * @param Advertisement $advertisement
     */
    protected function collectRandom(&$collected, Advertisement $advertisement)
    {
        // Получаем список id всех подходящих объявлений
        $query = Advertisement::find()
            ->select(['id'])
            ->where(['status' => Advertisement::STATUS_ACTIVE])
            ->andWhere(['section' => $advertisement->section])
            ->andWhere(['type' => $advertisement->type])
            ->andWhere(['<>', 'advertisements.id', $advertisement->id]);

        // MySQL: RAND() для случайной сортировки
        $query->orderBy(new \yii\db\Expression('RAND()'))
            ->limit($this->limit);

        $ids = $query->column();

        if (empty($ids)) {
            
            $query = Advertisement::find()
            ->select(['id'])
            ->where(['status' => Advertisement::STATUS_ACTIVE])
            ->andWhere(['section' => $advertisement->section])
            ->andWhere(['<>', 'advertisements.id', $advertisement->id]);

            // MySQL: RAND() для случайной сортировки
            $query->orderBy(new \yii\db\Expression('RAND()'))
                ->limit($this->limit);

            $ids = $query->column();

            if (empty($ids)) 
                return;
        }

        // Загружаем модели с нужными relation
        $ads = Advertisement::find()
            ->where(['id' => $ids])
            ->with(['images', 'glider', 'harness', 'device'])
            ->all();

        foreach ($ads as $similar) {
            // Приоритет 99 — чтобы всегда шли последними (не критично, так как это fallback)
            $this->addToCollected($collected, $similar, 99);
        }
    }

    /**
     * Добавить в коллекцию, если ещё нет
     */
    protected function addToCollected(&$collected, Advertisement $ad, $priority, $reason = '')
    {
        $id = $ad->id;
        if (isset($collected[$id])) {
            // Уже есть — оставляем более важный приоритет
            if ($priority < $collected[$id]['priority']) {
                $collected[$id]['priority'] = $priority;
                $collected[$id]['reason'] = $reason;
            }
            return;
        }

        $collected[$id] = [
            'ad' => $ad,
            'priority' => $priority,
            'reason' => $reason,
        ];
    }

    /**
     * Сортировка и обрезка до лимита
     */
    protected function finalize(array $collected)
    {
        if (empty($collected)) {
            return [];
        }

        // Сортировка: по приоритету
        usort($collected, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        $result = array_slice($collected, 0, $this->limit);

        // Возвращаем массив структур: [['ad' => Advertisement, 'reason' => string], ...]
        $items = [];
        foreach ($result as $item) {
            $items[] = [
                'ad' => $item['ad'],
                'reason' => $item['reason'],
            ];
        }

        return $items;
    }
}