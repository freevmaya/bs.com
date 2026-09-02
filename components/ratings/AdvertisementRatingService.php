<?php
// FILE: .\components\ratings\AdvertisementRatingService.php

namespace app\components\ratings;

use Yii;
use app\models\Advertisement;
use app\models\AdvertisementGlider;
use app\models\AdvertisementHarness;
use app\models\AdvertisementDevice;

class AdvertisementRatingService
{
    /**
     * @var array Кэш для объявлений из БД
     */
    private array $advertisementsCache = [];
    
    /**
     * @var array Курсы валют
     */
    private array $currencyRates;
    
    /**
     * @var string Базовая валюта для расчетов
     */
    private string $baseCurrency;
    
    /**
     * Конструктор
     */
    public function __construct()
    {
        // Загружаем курсы валют из параметров
        $this->currencyRates = Yii::$app->params['currency_rates'] ?? [
            'RUB' => 1,
            'USD' => 92.5,
            'EUR' => 100.2,
        ];
        
        $this->baseCurrency = Yii::$app->params['base_currency'] ?? 'RUB';
    }
    
    /**
     * Конвертирует цену в базовую валюту (RUB)
     * 
     * @param float $price Цена
     * @param string $currency Валюта (RUB, USD, EUR)
     * @return float Цена в базовой валюте
     */
    private function convertToBaseCurrency(float $price, string $currency): float
    {
        $currency = strtoupper($currency);
        
        if ($currency === $this->baseCurrency) {
            return $price;
        }
        
        $rate = $this->currencyRates[$currency] ?? null;
        
        if ($rate === null) {
            Yii::warning("Currency rate not found for: {$currency}, using 1:1", 'rating_service');
            return $price;
        }
        
        return $price * $rate;
    }
    
    /**
     * Конвертирует цену из базовой валюты в указанную
     * 
     * @param float $price Цена в базовой валюте
     * @param string $currency Целевая валюта
     * @return float Цена в целевой валюте
     */
    private function convertFromBaseCurrency(float $price, string $currency): float
    {
        $currency = strtoupper($currency);
        
        if ($currency === $this->baseCurrency) {
            return $price;
        }
        
        $rate = $this->currencyRates[$currency] ?? null;
        
        if ($rate === null) {
            return $price;
        }
        
        return $price / $rate;
    }
    
    /**
     * Получить символ валюты
     */
    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'RUB' => '₽',
            'USD' => '$',
            'EUR' => '€',
        ];
        
        return $symbols[strtoupper($currency)] ?? $currency;
    }
    
    /**
     * Получить похожие объявления из БД для сравнения
     * 
     * @param Advertisement $advertisement Объявление для поиска аналогов
     * @param int $limit Максимальное количество аналогов
     * @return array Массив объявлений-аналогов
     */
    public function getSimilarAdvertisements(Advertisement $advertisement, int $limit = 20): array
    {
        $cacheKey = $advertisement->id . '_' . $limit;
        if (isset($this->advertisementsCache[$cacheKey])) {
            return $this->advertisementsCache[$cacheKey];
        }
        
        $query = Advertisement::find()
            ->where(['status' => Advertisement::STATUS_ACTIVE])
            ->andWhere(['section' => Advertisement::SECTION_SELL])
            ->andWhere(['type' => $advertisement->type])
            ->andWhere(['<>', 'advertisements.id', $advertisement->id])
            ->andWhere(['is not', 'price', null])
            ->with(['glider', 'harness', 'device', 'user'])
            ->limit($limit);
        
        // Для glider добавляем дополнительные фильтры
        if ($advertisement->type === Advertisement::TYPE_GLIDER && $advertisement->glider) {
            $glider = $advertisement->glider;
            
            $query->innerJoin('advertisement_glider', 'advertisement_glider.advertisement_id = advertisements.id');
            
            // Приоритет 1: Сертификация (обязательно)
            if ($glider->certification_id) {
                $query->andWhere(['advertisement_glider.certification_id' => $glider->certification_id]);
            }
            
            // Приоритет 2: Производитель (обязательно)
            if ($glider->producer_id) {
                $query->andWhere(['advertisement_glider.producer_id' => $glider->producer_id]);
            }
            
            // Приоритет 3: Состояние (желательно)
            if ($glider->condition) {
                $query->andWhere(['advertisement_glider.condition' => $glider->condition]);
            }
            
            // Приоритет 4: Весовая вилка (с разбросом +/- 15 кг)
            if ($glider->weight_min && $glider->weight_max) {
                $min = $glider->weight_min - 15;
                $max = $glider->weight_max + 15;
                $query->andWhere([
                    'or',
                    ['between', 'advertisement_glider.weight_min', $min, $max],
                    ['between', 'advertisement_glider.weight_max', $min, $max],
                ]);
            }
            
            // Приоритет 5: Год выпуска (с разбросом +/- 5 лет)
            if ($glider->date_release) {
                $year = (int)$glider->date_release;
                $query->andWhere(['between', 'advertisement_glider.date_release', $year - 5, $year + 5]);
            }
        }
        
        // Для harness
        if ($advertisement->type === Advertisement::TYPE_HARNESS && $advertisement->harness) {
            $harness = $advertisement->harness;
            
            $query->innerJoin('advertisement_harness', 'advertisement_harness.advertisement_id = advertisements.id');
            
            if ($harness->producer_id) {
                $query->andWhere(['advertisement_harness.producer_id' => $harness->producer_id]);
            }
            
            if ($harness->condition) {
                $query->andWhere(['advertisement_harness.condition' => $harness->condition]);
            }
            
            if ($harness->size) {
                $query->andWhere(['advertisement_harness.size' => $harness->size]);
            }
            
            if ($harness->date_release) {
                $year = (int)$harness->date_release;
                $query->andWhere(['between', 'advertisement_harness.date_release', $year - 3, $year + 3]);
            }
        }
        
        // Для device
        if ($advertisement->type === Advertisement::TYPE_DEVICE && $advertisement->device) {
            $device = $advertisement->device;
            
            $query->innerJoin('advertisement_device', 'advertisement_device.advertisement_id = advertisements.id');
            
            if ($device->producer_id) {
                $query->andWhere(['advertisement_device.producer_id' => $device->producer_id]);
            }
            
            if ($device->condition) {
                $query->andWhere(['advertisement_device.condition' => $device->condition]);
            }
        }
        
        // Выполняем запрос
        $similar = $query->all();
        
        // Если ничего не найдено, пробуем получить просто похожие по типу и производителю
        if (empty($similar)) {
            $query = Advertisement::find()
                ->where(['status' => Advertisement::STATUS_ACTIVE])
                ->andWhere(['section' => Advertisement::SECTION_SELL])
                ->andWhere(['type' => $advertisement->type])
                ->andWhere(['<>', 'advertisements.id', $advertisement->id])
                ->andWhere(['is not', 'price', null])
                ->with(['glider', 'harness', 'device', 'user'])
                ->limit($limit);
            
            if ($advertisement->type === Advertisement::TYPE_GLIDER && $advertisement->glider) {
                $query->innerJoin('advertisement_glider', 'advertisement_glider.advertisement_id = advertisements.id');
                
                if ($advertisement->glider->producer_id) {
                    $query->andWhere(['advertisement_glider.producer_id' => $advertisement->glider->producer_id]);
                }
            }
            
            $similar = $query->all();
        }
        
        $this->advertisementsCache[$cacheKey] = $similar;
        return $similar;
    }
    
    /**
     * Интерполяция цены на основе года выпуска (цены приведены к базовой валюте)
     * 
     * @param array $similar Аналоги из БД
     * @param int|null $targetYear Год выпуска оцениваемого крыла (может быть null)
     * @param int $currentYear Текущий год
     * @return float|null Интерполированная цена в базовой валюте
     */
    private function interpolatePriceByYear(array $similar, ?int $targetYear, int $currentYear): ?float
    {
        // Если год не указан - возвращаем null, будем использовать медиану
        if ($targetYear === null || $targetYear <= 1990) {
            return null;
        }
        
        $points = [];
        foreach ($similar as $ad) {
            $typeObject = $ad->getTypeObject();
            if (!$typeObject) continue;
            
            $year = $typeObject->date_release;
            $price = $ad->price;
            $currency = $ad->currency ?? 'RUB';
            
            // Пропускаем объявления без года или цены
            if (!$year || !$price || $year <= 1990 || $year > $currentYear) {
                continue;
            }
            
            $priceInBase = $this->convertToBaseCurrency((float)$price, $currency);
            
            $points[] = [
                'year' => (int)$year,
                'price' => $priceInBase,
                'currency' => $currency,
                'original_price' => $price,
                'ad_id' => $ad->id,
            ];
        }
        
        // Если меньше 3 точек - не интерполируем
        if (count($points) < 3) {
            return null;
        }
        
        // Сортируем по году
        usort($points, function($a, $b) {
            return $a['year'] <=> $b['year'];
        });
        
        // 1. Проверяем, есть ли точки с годом, близким к целевому (±3 года)
        $nearbyPoints = [];
        foreach ($points as $p) {
            if (abs($p['year'] - $targetYear) <= 3) {
                $nearbyPoints[] = $p;
            }
        }
        
        // Если есть близкие точки (≥2) - используем их
        if (count($nearbyPoints) >= 2) {
            $points = $nearbyPoints;
        }
        
        // 2. Удаляем выбросы (цены, которые сильно отличаются)
        $prices = array_column($points, 'price');
        $median = $this->calculateMedian($points);
        $stdDev = $this->calculateStdDev($prices, $median);
        
        // Удаляем точки, которые отличаются более чем на 1.5 стандартных отклонения
        $filteredPoints = [];
        foreach ($points as $p) {
            if (abs($p['price'] - $median) <= 1.5 * $stdDev) {
                $filteredPoints[] = $p;
            }
        }
        
        // Если после фильтрации осталось меньше 2 точек - используем все
        if (count($filteredPoints) >= 2) {
            $points = $filteredPoints;
        }
        
        // 3. Если осталось слишком мало точек - возвращаем null
        if (count($points) < 2) {
            return null;
        }
        
        // 4. Интерполяция
        // Если целевой год меньше минимального - экстраполяция с ограничением
        if ($targetYear < $points[0]['year']) {
            $slope = $this->calculateSlope($points);
            $price = $points[0]['price'] - $slope * ($points[0]['year'] - $targetYear);
            // Ограничиваем: цена не может быть ниже 30% от ближайшей точки
            $minPrice = $points[0]['price'] * 0.3;
            return max($minPrice, min($points[0]['price'] * 2, $price));
        }
        
        // Если целевой год больше максимального - экстраполяция с ограничением
        if ($targetYear > $points[count($points) - 1]['year']) {
            $slope = $this->calculateSlope($points);
            $price = $points[count($points) - 1]['price'] + $slope * ($targetYear - $points[count($points) - 1]['year']);
            // Ограничиваем: цена не может быть выше 200% от ближайшей точки
            $maxPrice = $points[count($points) - 1]['price'] * 2;
            $minPrice = $points[count($points) - 1]['price'] * 0.3;
            return max($minPrice, min($maxPrice, $price));
        }
        
        // Находим две ближайшие точки для интерполяции
        for ($i = 0; $i < count($points) - 1; $i++) {
            if ($points[$i]['year'] <= $targetYear && $points[$i + 1]['year'] >= $targetYear) {
                $x1 = $points[$i]['year'];
                $y1 = $points[$i]['price'];
                $x2 = $points[$i + 1]['year'];
                $y2 = $points[$i + 1]['price'];
                
                // Если разница в годах большая (> 5 лет) - используем медиану
                if (($x2 - $x1) > 5) {
                    return $this->calculateMedian($points);
                }
                
                $price = $y1 + ($y2 - $y1) * ($targetYear - $x1) / ($x2 - $x1);
                
                // Ограничиваем: цена должна быть между 30% и 200% от ближайших точек
                $minPrice = min($y1, $y2) * 0.3;
                $maxPrice = max($y1, $y2) * 2;
                
                return max($minPrice, min($maxPrice, $price));
            }
        }
        
        // Если не нашли - возвращаем null
        return null;
    }
    
    /**
     * Вычисляет наклон (slope) для линейной регрессии
     */
    private function calculateSlope(array $points): float
    {
        $n = count($points);
        if ($n < 2) return 0;
        
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        foreach ($points as $p) {
            $sumX += $p['year'];
            $sumY += $p['price'];
            $sumXY += $p['year'] * $p['price'];
            $sumX2 += $p['year'] * $p['year'];
        }
        
        $denominator = $n * $sumX2 - $sumX * $sumX;
        if ($denominator == 0) return 0;
        
        return ($n * $sumXY - $sumX * $sumY) / $denominator;
    }
    
    /**
     * Вычисляет медиану цен
     */
    private function calculateMedian(array $points): float
    {
        $prices = array_column($points, 'price');
        sort($prices);
        $count = count($prices);
        
        if ($count == 0) return 0;
        if ($count % 2 == 1) {
            return $prices[($count - 1) / 2];
        }
        return ($prices[$count / 2 - 1] + $prices[$count / 2]) / 2;
    }
    
    /**
     * Вычисляет стандартное отклонение
     */
    private function calculateStdDev(array $values, float $mean): float
    {
        $count = count($values);
        if ($count < 2) return 0;
        
        $sum = 0;
        foreach ($values as $value) {
            $sum += pow($value - $mean, 2);
        }
        
        return sqrt($sum / ($count - 1));
    }
    
    /**
     * Получить разумную цену с учетом приоритетов:
     * 1. Интерполяция по году (если год указан и есть данные)
     * 2. Корректировка по состоянию (если состояние известно)
     * 3. Медианная цена аналогов (как fallback)
     * 
     * @param array $similar Аналоги
     * @param Advertisement $advertisement Оцениваемое объявление
     * @param float|null $interpolatedPrice Интерполированная цена
     * @return float Разумная цена в базовой валюте
     */
    private function getReasonablePrice(array $similar, Advertisement $advertisement, ?float $interpolatedPrice): float
    {
        $typeObject = $advertisement->getTypeObject();
        $condition = $typeObject->condition ?? null;
        
        // 1. Получаем все цены аналогов в базовой валюте
        $prices = [];
        $pricesByCondition = [];
        $pricesByYear = [];
        
        foreach ($similar as $ad) {
            $currency = $ad->currency ?? 'RUB';
            $price = (float)$ad->price;
            if ($price <= 0) continue;
            
            $priceInBase = $this->convertToBaseCurrency($price, $currency);
            $prices[] = $priceInBase;
            
            // Группируем по состоянию
            $adTypeObject = $ad->getTypeObject();
            if ($adTypeObject && $adTypeObject->condition) {
                $cond = $adTypeObject->condition;
                if (!isset($pricesByCondition[$cond])) {
                    $pricesByCondition[$cond] = [];
                }
                $pricesByCondition[$cond][] = $priceInBase;
            }
            
            // Группируем по году (если есть)
            if ($adTypeObject && $adTypeObject->date_release) {
                $year = (int)$adTypeObject->date_release;
                if ($year > 1990) {
                    if (!isset($pricesByYear[$year])) {
                        $pricesByYear[$year] = [];
                    }
                    $pricesByYear[$year][] = $priceInBase;
                }
            }
        }
        
        if (empty($prices)) {
            return 50000;
        }
        
        // Сортируем и удаляем выбросы (верхние и нижние 15%)
        sort($prices);
        $count = count($prices);
        $trimCount = max(1, (int)($count * 0.15));
        $trimmedPrices = array_slice($prices, $trimCount, $count - 2 * $trimCount);
        
        if (empty($trimmedPrices)) {
            $trimmedPrices = $prices;
        }
        
        // ============================================================
        // ПРИОРИТЕТ 1: Интерполяция по году (если есть)
        // ============================================================
        if ($interpolatedPrice !== null && $interpolatedPrice > 0) {
            // Проверяем, что интерполированная цена в разумных пределах
            $median = $this->calculateMedian(array_map(function($p) { return ['price' => $p]; }, $trimmedPrices));
            $minReasonable = $median * 0.3;
            $maxReasonable = $median * 2.5;
            
            if ($interpolatedPrice >= $minReasonable && $interpolatedPrice <= $maxReasonable) {
                // Применяем корректировку по состоянию
                if ($condition) {
                    $conditionMultiplier = $this->getConditionMultiplier($condition);
                    // Не применяем полный коэффициент, только часть (70%)
                    $adjustedPrice = $interpolatedPrice * (0.7 + 0.3 * $conditionMultiplier);
                    return $adjustedPrice;
                }
                return $interpolatedPrice;
            }
        }
        
        // ============================================================
        // ПРИОРИТЕТ 2: Корректировка по состоянию (если известно)
        // ============================================================
        if ($condition && isset($pricesByCondition[$condition]) && count($pricesByCondition[$condition]) >= 2) {
            $condPrices = $pricesByCondition[$condition];
            sort($condPrices);
            $condMedian = $this->calculateMedian(array_map(function($p) { return ['price' => $p]; }, $condPrices));
            return $condMedian;
        }
        
        // Если есть состояние, но мало аналогов - используем общую медиану с корректировкой
        if ($condition) {
            $median = $this->calculateMedian(array_map(function($p) { return ['price' => $p]; }, $trimmedPrices));
            $conditionMultiplier = $this->getConditionMultiplier($condition);
            return $median * $conditionMultiplier;
        }
        
        // ============================================================
        // ПРИОРИТЕТ 3: Медианная цена аналогов
        // ============================================================
        $median = $this->calculateMedian(array_map(function($p) { return ['price' => $p]; }, $trimmedPrices));
        
        // Если цена слишком низкая - используем среднюю
        if ($median < 10000) {
            return array_sum($trimmedPrices) / count($trimmedPrices);
        }
        
        return $median;
    }
    
    /**
     * Рассчитывает коэффициент износа на основе состояния
     */
    private function getConditionMultiplier(string $condition): float
    {
        $multipliers = [
            'new' => 1.0,
            'excellent' => 0.90,
            'good' => 0.80,
            'fair' => 0.65,
            'bad' => 0.45,
        ];
        
        return $multipliers[$condition] ?? 0.80;
    }
    
    /**
     * Рассчитывает коэффициент дефектов
     */
    private function getDefectsMultiplier(?string $defects): float
    {
        if (empty($defects)) {
            return 1.0;
        }
        
        $defectsLower = mb_strtolower($defects);
        $multiplier = 1.0;
        
        $penalties = [
            'ремонт' => 0.10,
            'рипстоп' => 0.08,
            'заклейк' => 0.07,
            'поврежден' => 0.12,
            'бандаж' => 0.05,
            'дырк' => 0.10,
            'потёртост' => 0.05,
            'замен' => 0.03,
        ];
        
        foreach ($penalties as $keyword => $penalty) {
            if (strpos($defectsLower, $keyword) !== false) {
                $multiplier -= $penalty;
            }
        }
        
        $defectCount = 0;
        foreach ($penalties as $keyword => $penalty) {
            if (strpos($defectsLower, $keyword) !== false) {
                $defectCount++;
            }
        }
        
        if ($defectCount > 1) {
            $multiplier -= 0.05 * ($defectCount - 1);
        }
        
        return max(0.60, $multiplier);
    }
    
    /**
     * Рассчитывает коэффициент налета
     */
    private function getFlightTimeMultiplier(?int $flightTime): float
    {
        if ($flightTime === null || $flightTime <= 0) {
            return 1.0;
        }
        
        if ($flightTime <= 50) {
            return 1.0 - ($flightTime / 50) * 0.05;
        } elseif ($flightTime <= 100) {
            return 0.95 - (($flightTime - 50) / 50) * 0.10;
        } elseif ($flightTime <= 200) {
            return 0.85 - (($flightTime - 100) / 100) * 0.15;
        } else {
            return 0.70 - min(0.20, (($flightTime - 200) / 100) * 0.10);
        }
    }
    
    /**
     * Основной метод оценки стоимости
     */
    public function rateAdvertisement(Advertisement $advertisement, string $context): ?array
    {
        try {
            $similar = $this->getSimilarAdvertisements($advertisement, 20);
            
            if (empty($similar)) {
                return $this->getBaseRating($advertisement);
            }
            
            $typeObject = $advertisement->getTypeObject();
            if (!$typeObject || !($typeObject instanceof AdvertisementGlider)) {
                return $this->getBaseRating($advertisement);
            }
            
            $currentYear = (int)date('Y');
            $targetYear = $typeObject->date_release ? (int)$typeObject->date_release : null;
            
            // 1. Интерполяция цены по году выпуска (если год указан)
            $interpolatedPriceInBase = $this->interpolatePriceByYear($similar, $targetYear, $currentYear);
            
            // 2. Получаем разумную цену с учетом всех факторов
            $basePriceInBase = $this->getReasonablePrice($similar, $advertisement, $interpolatedPriceInBase);
            
            // 3. Дополнительная корректировка на дефекты
            $defectsMultiplier = $this->getDefectsMultiplier($typeObject->defects);
            
            // 4. Дополнительная корректировка на налет
            $flightTimeMultiplier = $this->getFlightTimeMultiplier($typeObject->flight_time);
            
            // Применяем дополнительные коэффициенты
            $estimatedPriceInBase = $basePriceInBase * $defectsMultiplier * $flightTimeMultiplier;
            
            // ✅ Конвертируем в валюту объявления
            $adCurrency = $advertisement->currency ?? 'RUB';
            $estimatedPrice = $this->convertFromBaseCurrency($estimatedPriceInBase, $adCurrency);
            
            // Округляем до тысяч (но не меньше 1000)
            $estimatedPrice = max(1000, round($estimatedPrice / 1000) * 1000);
            
            // Анализируем цену объявления
            $adPrice = (float)($advertisement->price ?? 0);
            $adPriceInBase = $this->convertToBaseCurrency($adPrice, $adCurrency);
            
            $priceDiff = 0;
            $priceAdvice = '';
            if ($adPrice > 0 && $estimatedPriceInBase > 0) {
                $priceDiff = round(($adPriceInBase - $estimatedPriceInBase) / $estimatedPriceInBase * 100);
                if ($priceDiff > 30) {
                    $priceAdvice = '⚠️ Цена ЗНАЧИТЕЛЬНО завышена на ' . $priceDiff . '% относительно рыночной. Рекомендуется снизить цену.';
                } elseif ($priceDiff > 15) {
                    $priceAdvice = 'Цена завышена на ' . $priceDiff . '% относительно рыночной. Рекомендуется снизить цену.';
                } elseif ($priceDiff < -30) {
                    $priceAdvice = '🔥 Цена ЗНАЧИТЕЛЬНО занижена на ' . abs($priceDiff) . '% относительно рыночной. Отличное предложение!';
                } elseif ($priceDiff < -15) {
                    $priceAdvice = '💰 Цена занижена на ' . abs($priceDiff) . '% относительно рыночной. Хорошее предложение!';
                } else {
                    $priceAdvice = '✅ Цена соответствует рыночной.';
                }
            }
            
            // Формируем результат
            $result = [
                'fair_price' => (int)$estimatedPrice,
                'price_range' => [
                    'min' => (int)max(1000, round($this->convertFromBaseCurrency($estimatedPriceInBase * 0.7, $adCurrency) / 1000) * 1000),
                    'max' => (int)round($this->convertFromBaseCurrency($estimatedPriceInBase * 1.3, $adCurrency) / 1000) * 1000,
                ],
                'currency' => $adCurrency,
                'currency_symbol' => $this->getCurrencySymbol($adCurrency),
                'confidence' => $this->calculateConfidence($similar, $typeObject),
                'appeal' => $this->calculateAppeal($advertisement),
                'clarity' => $this->calculateClarity($advertisement),
                'relevance' => $this->calculateRelevance($advertisement, $similar),
                'call_to_action' => $this->calculateCallToAction($advertisement),
                'pros' => $this->generatePros($advertisement, $similar, $estimatedPriceInBase, $adPriceInBase),
                'cons' => $this->generateCons($advertisement, $similar, $estimatedPriceInBase, $adPriceInBase),
                'recommendations' => $this->generateRecommendations($advertisement, $estimatedPriceInBase, $adPriceInBase, $priceAdvice),
                'market_analysis' => $this->generateMarketAnalysis($similar, $targetYear, $estimatedPriceInBase, $adCurrency),
            ];
            
            // Логируем результат для отладки
            Yii::info([
                'ad_id' => $advertisement->id,
                'target_year' => $targetYear,
                'interpolated_price' => $interpolatedPriceInBase,
                'base_price' => $basePriceInBase,
                'estimated_price' => $estimatedPriceInBase,
                'final_price' => $estimatedPrice,
                'similar_count' => count($similar),
                'defects_multiplier' => $defectsMultiplier,
                'flight_time_multiplier' => $flightTimeMultiplier,
            ], 'rating_service.calculation');
            
            return $result;
            
        } catch (\Exception $e) {
            Yii::error('Ошибка оценки: ' . $e->getMessage(), 'rating_service');
            return $this->getBaseRating($advertisement);
        }
    }
    
    /**
     * Базовый рейтинг (когда нет аналогов)
     */
    private function getBaseRating(Advertisement $advertisement): array
    {
        $adCurrency = $advertisement->currency ?? 'RUB';
        $price = (float)($advertisement->price ?? 50000);
        $estimatedPrice = round($price / 1000) * 1000;
        
        return [
            'fair_price' => (int)$estimatedPrice,
            'price_range' => [
                'min' => (int)round($estimatedPrice * 0.7 / 1000) * 1000,
                'max' => (int)round($estimatedPrice * 1.3 / 1000) * 1000,
            ],
            'currency' => $adCurrency,
            'currency_symbol' => $this->getCurrencySymbol($adCurrency),
            'confidence' => 4,
            'appeal' => 6,
            'clarity' => 5,
            'relevance' => 5,
            'call_to_action' => 5,
            'pros' => 'Нет достаточных данных для полного анализа',
            'cons' => 'Нет аналогов в базе данных',
            'recommendations' => 'Добавьте больше деталей в объявление и фото для повышения привлекательности.',
            'market_analysis' => 'Недостаточно данных для точного анализа рынка. Рекомендуется изучить аналогичные объявления.',
        ];
    }
    
    /**
     * Расчет уверенности на основе количества аналогов
     */
    private function calculateConfidence(array $similar, $typeObject): int
    {
        $count = count($similar);
        if ($count >= 10) return 8;
        if ($count >= 7) return 7;
        if ($count >= 5) return 6;
        if ($count >= 3) return 5;
        return 4;
    }
    
    /**
     * Расчет привлекательности объявления
     */
    private function calculateAppeal(Advertisement $ad): int
    {
        $score = 5;
        
        if ($ad->price && $ad->price > 0) $score += 1;
        if ($ad->description && strlen($ad->description) > 50) $score += 1;
        if ($ad->city) $score += 0.5;
        if ($ad->phone || $ad->email || $ad->telegram || $ad->vk_profile_url) $score += 0.5;
        
        $imageCount = $ad->getImages()->count();
        if ($imageCount >= 5) $score += 1;
        elseif ($imageCount >= 3) $score += 0.5;
        
        return min(10, (int)round($score));
    }
    
    /**
     * Расчет ясности описания
     */
    private function calculateClarity(Advertisement $ad): int
    {
        $score = 5;
        $desc = $ad->description ?? '';
        $descLength = strlen($desc);
        
        if ($descLength > 500) $score += 2;
        elseif ($descLength > 200) $score += 1;
        elseif ($descLength > 50) $score += 0.5;
        
        if (substr_count($desc, "\n") >= 3) $score += 1;
        
        $keywords = ['состояние', 'дефект', 'комплект', 'налёт', 'часов'];
        foreach ($keywords as $keyword) {
            if (stripos($desc, $keyword) !== false) $score += 0.3;
        }
        
        return min(10, (int)round($score));
    }
    
    /**
     * Расчет релевантности рыночной ситуации
     */
    private function calculateRelevance(Advertisement $ad, array $similar): int
    {
        $score = 5;
        
        if (!empty($similar)) {
            $adCurrency = $ad->currency ?? 'RUB';
            $adPrice = (float)($ad->price ?? 0);
            $adPriceInBase = $this->convertToBaseCurrency($adPrice, $adCurrency);
            
            $prices = [];
            foreach ($similar as $s) {
                $sCurrency = $s->currency ?? 'RUB';
                $sPrice = (float)$s->price;
                $prices[] = $this->convertToBaseCurrency($sPrice, $sCurrency);
            }
            
            $avgPrice = array_sum($prices) / count($prices);
            
            if ($adPriceInBase > 0 && $avgPrice > 0) {
                $diff = abs($adPriceInBase - $avgPrice) / $avgPrice;
                if ($diff < 0.1) $score += 2;
                elseif ($diff < 0.2) $score += 1;
                elseif ($diff < 0.3) $score += 0.5;
                else $score -= 1;
            }
        }
        
        return min(10, max(1, (int)round($score)));
    }
    
    /**
     * Расчет призыва к действию
     */
    private function calculateCallToAction(Advertisement $ad): int
    {
        $score = 5;
        $desc = $ad->description ?? '';
        
        $ctaWords = ['пишите', 'звоните', 'свяжитесь', 'телеграм', 'whatsapp', 'смотреть', 'вопрос'];
        foreach ($ctaWords as $word) {
            if (stripos($desc, $word) !== false) {
                $score += 0.5;
            }
        }
        
        if ($ad->phone || $ad->email || $ad->telegram || $ad->vk_profile_url) {
            $score += 1;
        }
        
        return min(10, (int)round($score));
    }
    
    /**
     * Генерация плюсов
     */
    private function generatePros(Advertisement $ad, array $similar, float $estimatedPriceInBase, float $adPriceInBase): string
    {
        $pros = [];
        $typeObject = $ad->getTypeObject();
        
        if ($typeObject) {
            $condition = $typeObject->condition ?? 'good';
            $conditionLabels = [
                'new' => 'Новое состояние',
                'excellent' => 'Отличное состояние',
                'good' => 'Хорошее состояние',
            ];
            if (isset($conditionLabels[$condition])) {
                $pros[] = $conditionLabels[$condition];
            }
        }
        
        if ($adPriceInBase > 0 && $estimatedPriceInBase > 0) {
            $diff = ($adPriceInBase - $estimatedPriceInBase) / $estimatedPriceInBase;
            if ($diff < -0.1) {
                $pros[] = 'Цена ниже рыночной';
            }
        }
        
        $imageCount = $ad->getImages()->count();
        if ($imageCount >= 5) {
            $pros[] = 'Много качественных фото';
        } elseif ($imageCount >= 3) {
            $pros[] = 'Есть фото';
        }
        
        if ($ad->phone && $ad->telegram) {
            $pros[] = 'Указаны контакты для связи';
        }
        
        if ($ad->description && strlen($ad->description) > 100) {
            $pros[] = 'Подробное описание';
        }
        
        if (count($pros) < 3) {
            $defaultPros = ['Хороший производитель', 'Адекватная цена'];
            foreach ($defaultPros as $default) {
                if (!in_array($default, $pros)) {
                    $pros[] = $default;
                }
            }
        }
        
        return implode("\n", array_slice($pros, 0, 5));
    }
    
    /**
     * Генерация минусов
     */
    private function generateCons(Advertisement $ad, array $similar, float $estimatedPriceInBase, float $adPriceInBase): string
    {
        $cons = [];
        $typeObject = $ad->getTypeObject();
        
        if ($typeObject && $typeObject->defects) {
            $cons[] = 'Есть дефекты: ' . $typeObject->defects;
        }
        
        if ($adPriceInBase > 0 && $estimatedPriceInBase > 0) {
            $diff = ($adPriceInBase - $estimatedPriceInBase) / $estimatedPriceInBase;
            if ($diff > 0.2) {
                $cons[] = 'Цена завышена';
            }
        }
        
        if (empty($ad->description) || strlen($ad->description) < 50) {
            $cons[] = 'Краткое описание';
        }
        
        $imageCount = $ad->getImages()->count();
        if ($imageCount < 3) {
            $cons[] = 'Мало фото';
        }
        
        if (empty($ad->city)) {
            $cons[] = 'Не указан город';
        }
        
        if (count($cons) < 2) {
            $cons[] = 'Рекомендуется добавить больше информации';
        }
        
        return implode("\n", array_slice($cons, 0, 4));
    }
    
    /**
     * Генерация рекомендаций
     */
    private function generateRecommendations(Advertisement $ad, float $estimatedPriceInBase, float $adPriceInBase, string $priceAdvice): string
    {
        $recommendations = [];
        
        if ($priceAdvice) {
            $recommendations[] = $priceAdvice;
        }
        
        if (empty($ad->description) || strlen($ad->description) < 50) {
            $recommendations[] = 'Добавьте подробное описание с указанием состояния, дефектов и комплектации.';
        }
        
        $imageCount = $ad->getImages()->count();
        if ($imageCount < 5) {
            $recommendations[] = 'Добавьте больше качественных фото (рекомендуется 5-10).';
        }
        
        if (empty($ad->phone) && empty($ad->telegram) && empty($ad->email)) {
            $recommendations[] = 'Укажите контактную информацию для связи.';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Объявление хорошо оформлено. Рекомендуется периодически обновлять его.';
        }
        
        return implode(' ', $recommendations);
    }
    
    /**
     * Генерация анализа рынка
     */
    private function generateMarketAnalysis(array $similar, ?int $targetYear, float $estimatedPriceInBase, string $targetCurrency): string
    {
        if (empty($similar)) {
            return 'Недостаточно данных для анализа рынка. Рекомендуется изучить аналогичные объявления.';
        }
        
        $prices = [];
        $years = [];
        
        foreach ($similar as $ad) {
            $typeObject = $ad->getTypeObject();
            if (!$typeObject) continue;
            
            if ($ad->price) {
                $currency = $ad->currency ?? 'RUB';
                $priceInBase = $this->convertToBaseCurrency((float)$ad->price, $currency);
                $prices[] = $priceInBase;
            }
            if ($typeObject->date_release) {
                $years[] = (int)$typeObject->date_release;
            }
        }
        
        if (empty($prices)) {
            return 'Нет данных о ценах аналогов.';
        }
        
        // Удаляем выбросы
        sort($prices);
        $count = count($prices);
        $trimCount = max(1, (int)($count * 0.1));
        $trimmedPrices = array_slice($prices, $trimCount, $count - 2 * $trimCount);
        
        if (empty($trimmedPrices)) {
            $trimmedPrices = $prices;
        }
        
        $minPrice = min($trimmedPrices);
        $maxPrice = max($trimmedPrices);
        $avgPrice = array_sum($trimmedPrices) / count($trimmedPrices);
        $medianPrice = $this->calculateMedian(array_map(function($p) { return ['price' => $p]; }, $trimmedPrices));
        
        // Конвертируем в валюту объявления
        $minPriceDisplay = $this->convertFromBaseCurrency($minPrice, $targetCurrency);
        $maxPriceDisplay = $this->convertFromBaseCurrency($maxPrice, $targetCurrency);
        $avgPriceDisplay = $this->convertFromBaseCurrency($avgPrice, $targetCurrency);
        $medianPriceDisplay = $this->convertFromBaseCurrency($medianPrice, $targetCurrency);
        $estimatedPriceDisplay = $this->convertFromBaseCurrency($estimatedPriceInBase, $targetCurrency);
        
        $symbol = $this->getCurrencySymbol($targetCurrency);
        
        $yearRange = '';
        if (!empty($years)) {
            $minYear = min($years);
            $maxYear = max($years);
            $yearRange = "Диапазон годов выпуска: {$minYear} - {$maxYear}. ";
        }
        
        // Добавляем информацию о целевом годе
        $targetYearInfo = '';
        if ($targetYear !== null && $targetYear > 1990) {
            $targetYearInfo = "Год выпуска оцениваемого крыла: {$targetYear}. ";
        }
        
        $analysis = "На основе анализа " . count($similar) . " аналогичных объявлений:\n";
        $analysis .= "- Средняя цена: " . number_format($avgPriceDisplay, 0, '.', ' ') . " {$symbol}\n";
        $analysis .= "- Медианная цена: " . number_format($medianPriceDisplay, 0, '.', ' ') . " {$symbol}\n";
        $analysis .= "- Диапазон цен: " . number_format($minPriceDisplay, 0, '.', ' ') . " - " . number_format($maxPriceDisplay, 0, '.', ' ') . " {$symbol}\n";
        $analysis .= $targetYearInfo;
        $analysis .= $yearRange;
        $analysis .= "Рекомендуемая цена: " . number_format($estimatedPriceDisplay, 0, '.', ' ') . " {$symbol}";
        
        return $analysis;
    }
}