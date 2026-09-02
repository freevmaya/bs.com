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
     * Числовые значения состояний для интерполяции
     */
    private const CONDITION_VALUES = [
        'new' => 5,
        'excellent' => 4,
        'good' => 3,
        'fair' => 2,
        'bad' => 1,
    ];
    
    /**
     * Коэффициенты состояний (для случая, когда интерполяция невозможна)
     */
    private const CONDITION_MULTIPLIERS = [
        'new' => 1.0,
        'excellent' => 0.9,
        'good' => 0.75,
        'fair' => 0.55,
        'bad' => 0.35,
    ];
    
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
     * ============================================================
     * НОВЫЙ АЛГОРИТМ ОЦЕНКИ ЦЕНЫ
     * ============================================================
     */
    
    /**
     * Основной метод оценки стоимости (НОВАЯ ВЕРСИЯ)
     */
    public function rateAdvertisement(Advertisement $advertisement, string $context): ?array
    {
        try {
            // Получаем выборку аналогов
            $similar = $this->getSimilarAdvertisements($advertisement, 50);
            
            if (empty($similar)) {
                return $this->getBaseRating($advertisement);
            }
            
            $typeObject = $advertisement->getTypeObject();
            if (!$typeObject || !($typeObject instanceof AdvertisementGlider)) {
                return $this->getBaseRating($advertisement);
            }
            
            // Получаем параметры объявления
            $targetYear = $typeObject->date_release ? (int)$typeObject->date_release : null;
            $targetCondition = $typeObject->condition ?? null;
            $targetCertificationId = $typeObject->certification_id ?? null;
            $targetProducerId = $typeObject->producer_id ?? null;
            
            $adCurrency = $advertisement->currency ?? 'RUB';
            
            // ============================================================
            // БЛОК 1: Есть год выпуска
            // ============================================================
            if ($targetYear !== null && $targetYear > 1990) {
                $estimatedPriceInBase = $this->estimateByYear(
                    $similar, 
                    $targetYear, 
                    $targetCertificationId,
                    $adCurrency
                );
                
                if ($estimatedPriceInBase !== null) {
                    // Конвертируем в валюту объявления
                    $estimatedPrice = $this->convertFromBaseCurrency($estimatedPriceInBase, $adCurrency);
                    $estimatedPrice = max(1000, round($estimatedPrice / 1000) * 1000);
                    
                    return $this->buildResult($advertisement, $similar, $estimatedPriceInBase, $estimatedPrice);
                }
            }
            
            // ============================================================
            // БЛОК 2: Нет года, но есть состояние
            // ============================================================
            if ($targetCondition !== null) {
                $estimatedPriceInBase = $this->estimateByCondition(
                    $similar,
                    $targetCondition,
                    $targetCertificationId,
                    $targetProducerId,
                    $adCurrency
                );
                
                if ($estimatedPriceInBase !== null) {
                    // Конвертируем в валюту объявления
                    $estimatedPrice = $this->convertFromBaseCurrency($estimatedPriceInBase, $adCurrency);
                    $estimatedPrice = max(1000, round($estimatedPrice / 1000) * 1000);
                    
                    return $this->buildResult($advertisement, $similar, $estimatedPriceInBase, $estimatedPrice);
                }
            }
            
            // ============================================================
            // БЛОК 3: Медианная цена всех крыльев
            // ============================================================
            $estimatedPriceInBase = $this->getMedianPriceAllWings($similar, $adCurrency);
            
            if ($estimatedPriceInBase !== null) {
                $estimatedPrice = $this->convertFromBaseCurrency($estimatedPriceInBase, $adCurrency);
                $estimatedPrice = max(1000, round($estimatedPrice / 1000) * 1000);
                
                return $this->buildResult($advertisement, $similar, $estimatedPriceInBase, $estimatedPrice);
            }
            
            return $this->getBaseRating($advertisement);
            
        } catch (\Exception $e) {
            Yii::error('Ошибка оценки: ' . $e->getMessage(), 'rating_service');
            return $this->getBaseRating($advertisement);
        }
    }
    
    /**
     * Оценка по году выпуска
     */
    private function estimateByYear(
        array $similar, 
        int $targetYear, 
        ?int $certificationId,
        string $adCurrency
    ): ?float {
        // 1. Пытаемся с сертификацией
        if ($certificationId) {
            $sample = $this->filterByCertification($similar, $certificationId);
            $sample = $this->filterByYear($sample, $targetYear);
            
            if (count($sample) >= 3) {
                $price = $this->interpolateByYear($sample, $targetYear, $adCurrency);
                if ($price !== null) {
                    return $price;
                }
            }
        }
        
        // 2. Расширяем: убираем сертификацию (только по году)
        $sample = $this->filterByYear($similar, $targetYear);
        if (count($sample) >= 3) {
            $price = $this->interpolateByYear($sample, $targetYear, $adCurrency);
            if ($price !== null) {
                return $price;
            }
        }
        
        // 3. Если всё ещё < 3, возвращаем null (пойдём дальше по алгоритму)
        return null;
    }
    
    /**
     * Оценка по состоянию
     */
    private function estimateByCondition(
        array $similar,
        string $targetCondition,
        ?int $certificationId,
        ?int $producerId,
        string $adCurrency
    ): ?float {
        // 1. Пытаемся с сертификацией
        if ($certificationId) {
            $sample = $this->filterByCertification($similar, $certificationId);
            
            if (count($sample) >= 3) {
                $price = $this->interpolateByCondition($sample, $targetCondition, $adCurrency);
                if ($price !== null) {
                    return $price;
                }
            }
        }
        
        // 2. Если нет сертификации, но есть производитель
        if ($producerId) {
            $sample = $this->filterByProducer($similar, $producerId);
            
            if (count($sample) >= 3) {
                $price = $this->interpolateByCondition($sample, $targetCondition, $adCurrency);
                if ($price !== null) {
                    return $price;
                }
            }
        }
        
        // 3. Если не хватило данных, возвращаем null (пойдём в блок 3)
        return null;
    }
    
    /**
     * Фильтр по сертификации
     */
    private function filterByCertification(array $similar, int $certificationId): array
    {
        return array_filter($similar, function($ad) use ($certificationId) {
            $typeObject = $ad->getTypeObject();
            if (!$typeObject) return false;
            return $typeObject->certification_id == $certificationId;
        });
    }
    
    /**
     * Фильтр по производителю
     */
    private function filterByProducer(array $similar, int $producerId): array
    {
        return array_filter($similar, function($ad) use ($producerId) {
            $typeObject = $ad->getTypeObject();
            if (!$typeObject) return false;
            return $typeObject->producer_id == $producerId;
        });
    }
    
    /**
     * Фильтр по году (с разбросом +/- 3 года)
     */
    private function filterByYear(array $similar, int $targetYear): array
    {
        return array_filter($similar, function($ad) use ($targetYear) {
            $typeObject = $ad->getTypeObject();
            if (!$typeObject) return false;
            
            $year = $typeObject->date_release;
            if (!$year) return false;
            
            $year = (int)$year;
            return abs($year - $targetYear) <= 3;
        });
    }
    
    /**
     * Интерполяция по году (линейная)
     */
    private function interpolateByYear(array $sample, int $targetYear, string $adCurrency): ?float
    {
        // Собираем точки (год, цена)
        $points = [];
        foreach ($sample as $ad) {
            $typeObject = $ad->getTypeObject();
            if (!$typeObject) continue;
            
            $year = $typeObject->date_release;
            $price = $ad->price;
            $currency = $ad->currency ?? 'RUB';
            
            if (!$year || !$price) continue;
            
            $year = (int)$year;
            $priceInBase = $this->convertToBaseCurrency((float)$price, $currency);
            
            $points[] = [
                'year' => $year,
                'price' => $priceInBase,
            ];
        }
        
        if (count($points) < 3) {
            return null;
        }
        
        // Сортируем по году
        usort($points, function($a, $b) {
            return $a['year'] <=> $b['year'];
        });
        
        // Удаляем выбросы
        $points = $this->removeOutliers($points, 'price');
        
        if (count($points) < 3) {
            return null;
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
                    return $this->calculateMedianPrice($points);
                }
                
                $price = $y1 + ($y2 - $y1) * ($targetYear - $x1) / ($x2 - $x1);
                
                // Ограничиваем цену
                return $this->limitPrice($price, $points);
            }
        }
        
        // Если целевой год вне диапазона - экстраполяция
        if ($targetYear < $points[0]['year']) {
            // Экстраполяция вниз (старше)
            $slope = $this->calculateSlope($points);
            $price = $points[0]['price'] - $slope * ($points[0]['year'] - $targetYear);
            return $this->limitPrice($price, $points);
        }
        
        if ($targetYear > $points[count($points) - 1]['year']) {
            // Экстраполяция вверх (новее)
            $slope = $this->calculateSlope($points);
            $price = $points[count($points) - 1]['price'] + $slope * ($targetYear - $points[count($points) - 1]['year']);
            return $this->limitPrice($price, $points);
        }
        
        return null;
    }
    
    /**
     * Интерполяция по состоянию
     */
    private function interpolateByCondition(array $sample, string $targetCondition, string $adCurrency): ?float
    {
        // Проверяем, есть ли targetCondition в карте состояний
        if (!isset(self::CONDITION_VALUES[$targetCondition])) {
            return null;
        }
        
        $targetValue = self::CONDITION_VALUES[$targetCondition];
        
        // Собираем точки (состояние, цена)
        $points = [];
        foreach ($sample as $ad) {
            $typeObject = $ad->getTypeObject();
            if (!$typeObject) continue;
            
            $condition = $typeObject->condition;
            $price = $ad->price;
            $currency = $ad->currency ?? 'RUB';
            
            if (!$condition || !$price) continue;
            if (!isset(self::CONDITION_VALUES[$condition])) continue;
            
            $conditionValue = self::CONDITION_VALUES[$condition];
            $priceInBase = $this->convertToBaseCurrency((float)$price, $currency);
            
            $points[] = [
                'condition' => $condition,
                'condition_value' => $conditionValue,
                'price' => $priceInBase,
            ];
        }
        
        if (count($points) < 2) {
            return null;
        }
        
        // Сортируем по значению состояния
        usort($points, function($a, $b) {
            return $a['condition_value'] <=> $b['condition_value'];
        });
        
        // Проверяем, есть ли разные состояния
        $uniqueConditions = array_unique(array_column($points, 'condition_value'));
        if (count($uniqueConditions) < 2) {
            // Все состояния одинаковые → используем медиану с коэффициентом
            $medianPrice = $this->calculateMedianPrice($points);
            $multiplier = self::CONDITION_MULTIPLIERS[$targetCondition] ?? 0.75;
            return $medianPrice * $multiplier;
        }
        
        // Удаляем выбросы
        $points = $this->removeOutliers($points, 'price');
        
        if (count($points) < 2) {
            return null;
        }
        
        // Ищем точки для интерполяции/экстраполяции
        $minValue = $points[0]['condition_value'];
        $maxValue = $points[count($points) - 1]['condition_value'];
        
        // Если target внутри диапазона - интерполяция
        if ($targetValue >= $minValue && $targetValue <= $maxValue) {
            for ($i = 0; $i < count($points) - 1; $i++) {
                $v1 = $points[$i]['condition_value'];
                $p1 = $points[$i]['price'];
                $v2 = $points[$i + 1]['condition_value'];
                $p2 = $points[$i + 1]['price'];
                
                if ($v1 <= $targetValue && $v2 >= $targetValue) {
                    // Если состояния одинаковые - используем медиану с коэффициентом
                    if ($v1 == $v2) {
                        $medianPrice = $this->calculateMedianPrice($points);
                        $multiplier = self::CONDITION_MULTIPLIERS[$targetCondition] ?? 0.75;
                        return $medianPrice * $multiplier;
                    }
                    
                    $price = $p1 + ($p2 - $p1) * ($targetValue - $v1) / ($v2 - $v1);
                    return $this->limitPrice($price, $points);
                }
            }
        }
        
        // Экстраполяция вниз (хуже, чем все в выборке)
        if ($targetValue < $minValue) {
            // Находим наклон по двум ближайшим точкам
            $slope = $this->calculateConditionSlope($points);
            $price = $points[0]['price'] - $slope * ($points[0]['condition_value'] - $targetValue);
            return $this->limitPrice($price, $points);
        }
        
        // Экстраполяция вверх (лучше, чем все в выборке)
        if ($targetValue > $maxValue) {
            $slope = $this->calculateConditionSlope($points);
            $price = $points[count($points) - 1]['price'] + $slope * ($targetValue - $points[count($points) - 1]['condition_value']);
            return $this->limitPrice($price, $points);
        }
        
        return null;
    }
    
    /**
     * Вычисляет наклон для интерполяции по состоянию
     */
    private function calculateConditionSlope(array $points): float
    {
        if (count($points) < 2) return 0;
        
        // Берем первую и последнюю точки для упрощения
        $v1 = $points[0]['condition_value'];
        $p1 = $points[0]['price'];
        $v2 = $points[count($points) - 1]['condition_value'];
        $p2 = $points[count($points) - 1]['price'];
        
        if ($v2 == $v1) return 0;
        
        return ($p2 - $p1) / ($v2 - $v1);
    }
    
    /**
     * Медианная цена всех крыльев (блок 3)
     */
    private function getMedianPriceAllWings(array $similar, string $adCurrency): ?float
    {
        $prices = [];
        foreach ($similar as $ad) {
            $price = $ad->price;
            $currency = $ad->currency ?? 'RUB';
            
            if ($price) {
                $prices[] = $this->convertToBaseCurrency((float)$price, $currency);
            }
        }
        
        if (empty($prices)) {
            return null;
        }
        
        sort($prices);
        $count = count($prices);
        
        // Удаляем выбросы (верхние и нижние 15%)
        $trimCount = max(1, (int)($count * 0.15));
        $trimmedPrices = array_slice($prices, $trimCount, $count - 2 * $trimCount);
        
        if (empty($trimmedPrices)) {
            $trimmedPrices = $prices;
        }
        
        return $this->calculateMedianFromArray($trimmedPrices);
    }
    
    /**
     * Удаление выбросов
     */
    private function removeOutliers(array $points, string $priceKey): array
    {
        $prices = array_column($points, $priceKey);
        $median = $this->calculateMedianFromArray($prices);
        $stdDev = $this->calculateStdDev($prices, $median);
        
        // Удаляем точки, которые отличаются более чем на 2 стандартных отклонения
        return array_filter($points, function($point) use ($median, $stdDev, $priceKey) {
            return abs($point[$priceKey] - $median) <= 2 * $stdDev;
        });
    }
    
    /**
     * Вычисляет медиану из массива цен
     */
    private function calculateMedianPrice(array $points): float
    {
        $prices = array_column($points, 'price');
        return $this->calculateMedianFromArray($prices);
    }
    
    /**
     * Вычисляет медиану из массива
     */
    private function calculateMedianFromArray(array $values): float
    {
        sort($values);
        $count = count($values);
        
        if ($count == 0) return 0;
        if ($count % 2 == 1) {
            return $values[($count - 1) / 2];
        }
        return ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
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
     * Вычисляет наклон для интерполяции по году
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
     * Ограничивает цену (не более +100%, не менее -50%)
     */
    private function limitPrice(float $price, array $points): float
    {
        $prices = array_column($points, 'price');
        $minPrice = min($prices);
        $maxPrice = max($prices);
        
        $lowerLimit = $minPrice * 0.5;
        $upperLimit = $maxPrice * 2.0;
        
        return max($lowerLimit, min($upperLimit, $price));
    }
    
    /**
     * Формирует результат
     */
    private function buildResult(
        Advertisement $advertisement,
        array $similar,
        float $estimatedPriceInBase,
        float $estimatedPrice
    ): array {
        $adCurrency = $advertisement->currency ?? 'RUB';
        
        return [
            'fair_price' => (int)$estimatedPrice,
            'price_range' => [
                'min' => (int)max(1000, round($this->convertFromBaseCurrency($estimatedPriceInBase * 0.7, $adCurrency) / 1000) * 1000),
                'max' => (int)round($this->convertFromBaseCurrency($estimatedPriceInBase * 1.3, $adCurrency) / 1000) * 1000,
            ],
            'currency' => $adCurrency,
            'currency_symbol' => $this->getCurrencySymbol($adCurrency),
            'confidence' => $this->calculateConfidence($similar),
            'appeal' => $this->calculateAppeal($advertisement),
            'clarity' => $this->calculateClarity($advertisement),
            'relevance' => $this->calculateRelevance($advertisement, $similar),
            'call_to_action' => $this->calculateCallToAction($advertisement),
            'pros' => $this->generatePros($advertisement, $similar, $estimatedPriceInBase),
            'cons' => $this->generateCons($advertisement, $similar, $estimatedPriceInBase),
            'recommendations' => $this->generateRecommendations($advertisement, $estimatedPriceInBase),
            'market_analysis' => $this->generateMarketAnalysis($similar, $estimatedPriceInBase, $adCurrency),
        ];
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
    private function calculateConfidence(array $similar): int
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
    private function generatePros(Advertisement $ad, array $similar, float $estimatedPriceInBase): string
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
        
        $adCurrency = $ad->currency ?? 'RUB';
        $adPrice = (float)($ad->price ?? 0);
        $adPriceInBase = $this->convertToBaseCurrency($adPrice, $adCurrency);
        
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
    private function generateCons(Advertisement $ad, array $similar, float $estimatedPriceInBase): string
    {
        $cons = [];
        $typeObject = $ad->getTypeObject();
        
        if ($typeObject && $typeObject->defects) {
            $cons[] = 'Есть дефекты: ' . $typeObject->defects;
        }
        
        $adCurrency = $ad->currency ?? 'RUB';
        $adPrice = (float)($ad->price ?? 0);
        $adPriceInBase = $this->convertToBaseCurrency($adPrice, $adCurrency);
        
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
    private function generateRecommendations(Advertisement $ad, float $estimatedPriceInBase): string
    {
        $recommendations = [];
        
        $adCurrency = $ad->currency ?? 'RUB';
        $adPrice = (float)($ad->price ?? 0);
        $adPriceInBase = $this->convertToBaseCurrency($adPrice, $adCurrency);
        
        if ($adPriceInBase > 0 && $estimatedPriceInBase > 0) {
            $diff = ($adPriceInBase - $estimatedPriceInBase) / $estimatedPriceInBase;
            if ($diff > 30) {
                $recommendations[] = '⚠️ Цена ЗНАЧИТЕЛЬНО завышена относительно рыночной. Рекомендуется снизить цену.';
            } elseif ($diff > 15) {
                $recommendations[] = 'Цена завышена относительно рыночной. Рекомендуется снизить цену.';
            } elseif ($diff < -30) {
                $recommendations[] = '🔥 Цена ЗНАЧИТЕЛЬНО занижена относительно рыночной. Отличное предложение!';
            } elseif ($diff < -15) {
                $recommendations[] = '💰 Цена занижена относительно рыночной. Хорошее предложение!';
            } else {
                $recommendations[] = '✅ Цена соответствует рыночной.';
            }
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
    private function generateMarketAnalysis(array $similar, float $estimatedPriceInBase, string $targetCurrency): string
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
        $medianPrice = $this->calculateMedianFromArray($trimmedPrices);
        
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
        
        $analysis = "На основе анализа " . count($similar) . " аналогичных объявлений:\n";
        $analysis .= "- Средняя цена: " . number_format($avgPriceDisplay, 0, '.', ' ') . " {$symbol}\n";
        $analysis .= "- Медианная цена: " . number_format($medianPriceDisplay, 0, '.', ' ') . " {$symbol}\n";
        $analysis .= "- Диапазон цен: " . number_format($minPriceDisplay, 0, '.', ' ') . " - " . number_format($maxPriceDisplay, 0, '.', ' ') . " {$symbol}\n";
        $analysis .= $yearRange;
        $analysis .= "Рекомендуемая цена: " . number_format($estimatedPriceDisplay, 0, '.', ' ') . " {$symbol}";
        
        return $analysis;
    }
}