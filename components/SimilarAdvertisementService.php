<?php
// FILE: .\components\SimilarAdvertisementService.php

namespace app\components;

use Yii;
use app\models\Advertisement;
use app\models\AdvertisementGlider;
use app\models\AdvertisementHarness;
use app\models\AdvertisementDevice;
use app\helpers\CurrencyRate;

/**
 * Сервис подбора похожих объявлений для страницы просмотра
 */
class SimilarAdvertisementService
{
    /**
     * @var int Сколько объявлений показывать в блоке
     */
    public $limit = 4;

    /**
     * @var int Время кэширования в секундах (по умолчанию 1 час)
     */
    public $cacheTtl = 3600;

    /**
     * @var bool Использовать кэш
     */
    public $useCache = true;

    /**
     * @var int Минимальное количество объявлений, при котором блок показывается
     */
    public $minResults = 2;

    /**
     * Найти похожие объявления
     *
     * @param Advertisement $advertisement Текущее объявление
     * @return array Массив объектов Advertisement с полем 'match_reason'
     */
    public function findSimilar(Advertisement $advertisement)
    {
        $cacheKey = 'similar_ads_' . $advertisement->id;

        if ($this->useCache) {
            $cached = Yii::$app->cache->get($cacheKey);
            if ($cached !== false) {
                return $this->hydrateFromCache($cached);
            }
        }

        $results = $this->collectSimilar($advertisement);

        if ($this->useCache) {
            Yii::$app->cache->set($cacheKey, $this->serializeForCache($results), $this->cacheTtl);
        }

        return $results;
    }

    /**
     * Собрать похожие объявления с приоритетами
     *
     * @param Advertisement $advertisement
     * @return array
     */
    protected function collectSimilar(Advertisement $advertisement)
    {
        $collected = []; // id => ['ad' => obj, 'priority' => int, 'reason' => string]

        // Базовые фильтры для всех запросов
        $baseQuery = function () use ($advertisement) {
            return Advertisement::find()
                ->where(['status' => Advertisement::STATUS_ACTIVE])
                ->andWhere(['section' => $advertisement->section])
                ->andWhere(['type' => $advertisement->type])
                ->andWhere(['<>', 'advertisements.id', $advertisement->id])
                ->andWhere(['<>', 'advertisements.user_id', $advertisement->user_id]);
        };

        // Ценовой диапазон ±50%
        $priceRange = $this->getPriceRange($advertisement);

        // ============================================================
        // ПРИОРИТЕТ 1: Та же модель + тот же производитель
        // ============================================================
        if ($advertisement->type === Advertisement::TYPE_GLIDER && $advertisement->glider) {
            $this->collectByGliderModel($collected, $baseQuery(), $advertisement, 1, 'Та же модель', $priceRange);
        } elseif ($advertisement->type === Advertisement::TYPE_HARNESS && $advertisement->harness) {
            $this->collectByHarnessModel($collected, $baseQuery(), $advertisement, 1, 'Та же модель', $priceRange);
        } elseif ($advertisement->type === Advertisement::TYPE_DEVICE && $advertisement->device) {
            $this->collectByDeviceModel($collected, $baseQuery(), $advertisement, 1, 'Та же модель', $priceRange);
        }

        // ============================================================
        // ПРИОРИТЕТ 2: Тот же производитель + та же сертификация (только glider)
        // ============================================================
        if ($advertisement->type === Advertisement::TYPE_GLIDER && $advertisement->glider) {
            $this->collectByProducerAndCert($collected, $baseQuery(), $advertisement, 2, 'Та же сертификация', $priceRange);
        }

        // ============================================================
        // ПРИОРИТЕТ 3: Тот же производитель
        // ============================================================
        if ($advertisement->type === Advertisement::TYPE_GLIDER && $advertisement->glider) {
            $this->collectByGliderProducer($collected, $baseQuery(), $advertisement, 3, 'Тот же производитель', $priceRange);
        } elseif ($advertisement->type === Advertisement::TYPE_HARNESS && $advertisement->harness) {
            $this->collectByHarnessProducer($collected, $baseQuery(), $advertisement, 3, 'Тот же производитель', $priceRange);
        } elseif ($advertisement->type === Advertisement::TYPE_DEVICE && $advertisement->device) {
            $this->collectByDeviceProducer($collected, $baseQuery(), $advertisement, 3, 'Тот же производитель', $priceRange);
        }

        // ============================================================
        // ПРИОРИТЕТ 4: Похожее название
        // ============================================================
        $this->collectByTitle($collected, $baseQuery(), $advertisement, 4, 'Похожее название', $priceRange);

        // Если набралось мало — расширяем ценовой диапазон
        if (count($collected) < $this->limit) {
            $wideRange = $this->getPriceRange($advertisement, 1.0);
            if ($advertisement->type === Advertisement::TYPE_GLIDER && $advertisement->glider) {
                $this->collectByGliderProducer($collected, $baseQuery(), $advertisement, 5, 'Тот же производитель', $wideRange);
            } elseif ($advertisement->type === Advertisement::TYPE_HARNESS && $advertisement->harness) {
                $this->collectByHarnessProducer($collected, $baseQuery(), $advertisement, 5, 'Тот же производитель', $wideRange);
            } elseif ($advertisement->type === Advertisement::TYPE_DEVICE && $advertisement->device) {
                $this->collectByDeviceProducer($collected, $baseQuery(), $advertisement, 5, 'Тот же производитель', $wideRange);
            }
        }

        // Финальная выборка и сортировка
        return $this->finalize($collected, $advertisement);
    }

    /**
     * Получить диапазон цен [min, max] с учётом валюты
     *
     * @param Advertisement $ad
     * @param float $factor Коэффициент расширения (0.5 — ±50%, 1.0 — ±100%)
     * @return array|null
     */
    protected function getPriceRange(Advertisement $ad, float $factor = 0.5)
    {
        if (empty($ad->price) || $ad->price <= 0) {
            return null;
        }

        $currency = $ad->currency ?? 'RUB';
        $rate = CurrencyRate::getRate($currency);
        $priceInBase = (float)$ad->price * $rate;

        return [
            'min' => $priceInBase * (1 - $factor),
            'max' => $priceInBase * (1 + $factor),
        ];
    }

    /**
     * Применить фильтр по цене и валюте к запросу
     * (учитываем, что цена в разных объявлениях может быть в разных валютах)
     */
    protected function applyPriceFilter($query, ?array $priceRange)
    {
        if ($priceRange === null) {
            return $query;
        }

        $rates = CurrencyRate::getRates();
        $baseCurrency = CurrencyRate::getBaseCurrency();

        $cases = [];
        $params = [];
        $i = 0;

        foreach ($rates as $currency => $rate) {
            $currency = strtoupper($currency);
            $rate = (float)$rate;
            if ($rate <= 0) {
                continue;
            }
            $paramName = ':pf_rate_' . strtolower($currency) . '_' . $i;
            $cases[] = "WHEN advertisements.currency = '{$currency}' THEN advertisements.price * {$paramName}";
            $params[$paramName] = $rate;
            $i++;
        }

        if (empty($cases)) {
            return $query;
        }

        $caseExpr = 'CASE ' . implode(' ', $cases) . ' ELSE advertisements.price END';

        $minParam = ':pf_min';
        $maxParam = ':pf_max';
        $params[$minParam] = $priceRange['min'];
        $params[$maxParam] = $priceRange['max'];

        $query->andWhere(new \yii\db\Expression(
            "({$caseExpr}) BETWEEN {$minParam} AND {$maxParam}",
            $params
        ));

        return $query;
    }

    /**
     * Применить фильтр по весовой вилке (пересечение ±10 кг)
     */
    protected function applyWeightFilter($query, AdvertisementGlider $glider)
    {
        if (empty($glider->weight_min) && empty($glider->weight_max)) {
            return $query;
        }

        $min = $glider->weight_min !== null ? (int)$glider->weight_min - 10 : null;
        $max = $glider->weight_max !== null ? (int)$glider->weight_max + 10 : null;

        if ($min !== null && $max !== null) {
            $query->andWhere([
                'or',
                ['between', 'advertisement_glider.weight_min', $min, $max],
                ['between', 'advertisement_glider.weight_max', $min, $max],
                [
                    'and',
                    ['<=', 'advertisement_glider.weight_min', $min],
                    ['>=', 'advertisement_glider.weight_max', $max],
                ],
            ]);
        }

        return $query;
    }

    /**
     * Применить фильтр по году выпуска (±4 года)
     */
    protected function applyYearFilter($query, $dateRelease, $alias)
    {
        if (empty($dateRelease) || !is_numeric($dateRelease)) {
            return $query;
        }

        $year = (int)$dateRelease;
        if ($year < 1990) {
            return $query;
        }

        $query->andWhere([
            'between',
            "{$alias}.date_release",
            (string)($year - 4),
            (string)($year + 4),
        ]);

        return $query;
    }

    /**
     * Собрать по модели glider
     */
    protected function collectByGliderModel(&$collected, $query, $ad, $priority, $reason, $priceRange)
    {
        $glider = $ad->glider;
        if (empty($glider->model)) {
            return;
        }

        $query->innerJoin('advertisement_glider', 'advertisement_glider.advertisement_id = advertisements.id')
            ->andWhere(['like', 'advertisement_glider.model', $glider->model]);

        if ($glider->producer_id) {
            $query->andWhere(['advertisement_glider.producer_id' => $glider->producer_id]);
        }

        $this->applyPriceFilter($query, $priceRange);
        $this->applyWeightFilter($query, $glider);
        $this->applyYearFilter($query, $glider->date_release, 'advertisement_glider');

        foreach ($query->with(['images', 'glider'])->limit(20)->all() as $similar) {
            $this->addToCollected($collected, $similar, $priority, $reason);
        }
    }

    /**
     * Собрать по производителю + сертификации
     */
    protected function collectByProducerAndCert(&$collected, $query, $ad, $priority, $reason, $priceRange)
    {
        $glider = $ad->glider;
        if (empty($glider->producer_id) || empty($glider->certification_id)) {
            return;
        }

        $query->innerJoin('advertisement_glider', 'advertisement_glider.advertisement_id = advertisements.id')
            ->andWhere(['advertisement_glider.producer_id' => $glider->producer_id])
            ->andWhere(['advertisement_glider.certification_id' => $glider->certification_id]);

        $this->applyPriceFilter($query, $priceRange);

        foreach ($query->with(['images', 'glider'])->limit(20)->all() as $similar) {
            $this->addToCollected($collected, $similar, $priority, $reason);
        }
    }

    /**
     * Собрать по производителю glider
     */
    protected function collectByGliderProducer(&$collected, $query, $ad, $priority, $reason, $priceRange)
    {
        $glider = $ad->glider;
        if (empty($glider->producer_id)) {
            return;
        }

        $query->innerJoin('advertisement_glider', 'advertisement_glider.advertisement_id = advertisements.id')
            ->andWhere(['advertisement_glider.producer_id' => $glider->producer_id]);

        $this->applyPriceFilter($query, $priceRange);

        foreach ($query->with(['images', 'glider'])->limit(20)->all() as $similar) {
            $this->addToCollected($collected, $similar, $priority, $reason);
        }
    }

    /**
     * Собрать по модели harness
     */
    protected function collectByHarnessModel(&$collected, $query, $ad, $priority, $reason, $priceRange)
    {
        $harness = $ad->harness;
        if (empty($harness->model)) {
            return;
        }

        $query->innerJoin('advertisement_harness', 'advertisement_harness.advertisement_id = advertisements.id')
            ->andWhere(['like', 'advertisement_harness.model', $harness->model]);

        if ($harness->producer_id) {
            $query->andWhere(['advertisement_harness.producer_id' => $harness->producer_id]);
        }

        $this->applyPriceFilter($query, $priceRange);
        $this->applyYearFilter($query, $harness->date_release, 'advertisement_harness');

        foreach ($query->with(['images', 'harness'])->limit(20)->all() as $similar) {
            $this->addToCollected($collected, $similar, $priority, $reason);
        }
    }

    /**
     * Собрать по производителю harness
     */
    protected function collectByHarnessProducer(&$collected, $query, $ad, $priority, $reason, $priceRange)
    {
        $harness = $ad->harness;
        if (empty($harness->producer_id)) {
            return;
        }

        $query->innerJoin('advertisement_harness', 'advertisement_harness.advertisement_id = advertisements.id')
            ->andWhere(['advertisement_harness.producer_id' => $harness->producer_id]);

        $this->applyPriceFilter($query, $priceRange);

        foreach ($query->with(['images', 'harness'])->limit(20)->all() as $similar) {
            $this->addToCollected($collected, $similar, $priority, $reason);
        }
    }

    /**
     * Собрать по модели device
     */
    protected function collectByDeviceModel(&$collected, $query, $ad, $priority, $reason, $priceRange)
    {
        $device = $ad->device;
        if (empty($device->model)) {
            return;
        }

        $query->innerJoin('advertisement_device', 'advertisement_device.advertisement_id = advertisements.id')
            ->andWhere(['like', 'advertisement_device.model', $device->model]);

        if ($device->producer_id) {
            $query->andWhere(['advertisement_device.producer_id' => $device->producer_id]);
        }

        $this->applyPriceFilter($query, $priceRange);

        foreach ($query->with(['images', 'device'])->limit(20)->all() as $similar) {
            $this->addToCollected($collected, $similar, $priority, $reason);
        }
    }

    /**
     * Собрать по производителю device
     */
    protected function collectByDeviceProducer(&$collected, $query, $ad, $priority, $reason, $priceRange)
    {
        $device = $ad->device;
        if (empty($device->producer_id)) {
            return;
        }

        $query->innerJoin('advertisement_device', 'advertisement_device.advertisement_id = advertisements.id')
            ->andWhere(['advertisement_device.producer_id' => $device->producer_id]);

        $this->applyPriceFilter($query, $priceRange);

        foreach ($query->with(['images', 'device'])->limit(20)->all() as $similar) {
            $this->addToCollected($collected, $similar, $priority, $reason);
        }
    }

    /**
     * Собрать по похожему названию
     */
    protected function collectByTitle(&$collected, $query, $ad, $priority, $reason, $priceRange)
    {
        if (empty($ad->title)) {
            return;
        }

        // Разбиваем название на значимые слова (длиннее 3 символов)
        $words = preg_split('/\s+/', mb_strtolower($ad->title));
        $words = array_filter($words, function ($w) {
            return mb_strlen($w) > 3;
        });

        if (empty($words)) {
            return;
        }

        $orConditions = ['or'];
        foreach (array_slice($words, 0, 3) as $word) {
            $orConditions[] = ['like', 'advertisements.title', $word];
        }

        $query->andWhere($orConditions);

        $this->applyPriceFilter($query, $priceRange);

        foreach ($query->with(['images', 'glider', 'harness', 'device'])->limit(20)->all() as $similar) {
            $this->addToCollected($collected, $similar, $priority, $reason);
        }
    }

    /**
     * Добавить в коллекцию (если ещё нет или приоритет выше)
     */
    protected function addToCollected(&$collected, $ad, $priority, $reason)
    {
        $id = $ad->id;
        if (isset($collected[$id])) {
            // Уже есть — если новый приоритет выше (число меньше), обновляем
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
     * Финальная сортировка и обрезка до лимита
     */
    protected function finalize(array $collected, Advertisement $ad)
    {
        if (empty($collected)) {
            return [];
        }

        // Сортировка: сначала по приоритету, затем по свежести (updated_at DESC)
        usort($collected, function ($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $a['priority'] <=> $b['priority'];
            }
            return $b['ad']->updated_at <=> $a['ad']->updated_at;
        });

        // Обрезка до лимита
        $result = array_slice($collected, 0, $this->limit);

        // Добавляем поле match_reason к каждой модели
        $ads = [];
        foreach ($result as $item) {
            $adItem = $item['ad'];
            $adItem->match_reason = $item['reason'];
            $ads[] = $adItem;
        }

        return $ads;
    }

    /**
     * Сериализация для кэша (только id и reason)
     */
    protected function serializeForCache(array $results)
    {
        $out = [];
        foreach ($results as $ad) {
            $out[] = [
                'id' => $ad->id,
                'reason' => $ad->match_reason ?? null,
            ];
        }
        return $out;
    }

    /**
     * Гидратация из кэша
     */
    protected function hydrateFromCache(array $cached)
    {
        if (empty($cached)) {
            return [];
        }

        $ids = array_column($cached, 'id');
        $reasons = [];
        foreach ($cached as $item) {
            $reasons[$item['id']] = $item['reason'];
        }

        $ads = Advertisement::find()
            ->where(['id' => $ids])
            ->andWhere(['status' => Advertisement::STATUS_ACTIVE])
            ->with(['images', 'glider', 'harness', 'device'])
            ->all();

        // Сохраняем порядок из кэша
        $ordered = [];
        foreach ($ids as $id) {
            foreach ($ads as $ad) {
                if ($ad->id === $id) {
                    $ad->match_reason = $reasons[$id] ?? null;
                    $ordered[] = $ad;
                    break;
                }
            }
        }

        return $ordered;
    }

    /**
     * Проверить, достаточно ли результатов для показа блока
     */
    public function hasEnough(Advertisement $advertisement)
    {
        $results = $this->findSimilar($advertisement);
        return count($results) >= $this->minResults;
    }
}