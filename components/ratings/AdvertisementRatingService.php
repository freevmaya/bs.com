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
        
        // Для glider добавляем дополнительные фильтры (постепенное ослабление)
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
     * Интерполяция цены на основе года выпуска
     * 
     * @param array $similar Аналоги из БД
     * @param int $targetYear Год выпуска оцениваемого крыла
     * @param int $currentYear Текущий год
     * @return float|null Интерполированная цена
     */
    private function interpolatePriceByYear(array $similar, int $targetYear, int $currentYear): ?float
    {
        // Фильтруем аналоги с указанным годом выпуска и ценой
        $points = [];
        foreach ($similar as $ad) {
            $typeObject = $ad->getTypeObject();
            if (!$typeObject) continue;
            
            $year = $typeObject->date_release;
            $price = $ad->price;
            
            if ($year && $price && $year > 1990 && $year <= $currentYear) {
                $points[] = [
                    'year' => (int)$year,
                    'price' => (float)$price,
                ];
            }
        }
        
        // Если меньше 2 точек - не можем интерполировать
        if (count($points) < 2) {
            return null;
        }
        
        // Сортируем по году
        usort($points, function($a, $b) {
            return $a['year'] <=> $b['year'];
        });
        
        // Если целевой год меньше минимального - экстраполяция назад
        if ($targetYear < $points[0]['year']) {
            // Используем линейную регрессию для экстраполяции
            $slope = $this->calculateSlope($points);
            return $points[0]['price'] - $slope * ($points[0]['year'] - $targetYear);
        }
        
        // Если целевой год больше максимального - экстраполяция вперед
        if ($targetYear > $points[count($points) - 1]['year']) {
            $slope = $this->calculateSlope($points);
            return $points[count($points) - 1]['price'] + $slope * ($targetYear - $points[count($points) - 1]['year']);
        }
        
        // Находим две ближайшие точки для интерполяции
        for ($i = 0; $i < count($points) - 1; $i++) {
            if ($points[$i]['year'] <= $targetYear && $points[$i + 1]['year'] >= $targetYear) {
                $x1 = $points[$i]['year'];
                $y1 = $points[$i]['price'];
                $x2 = $points[$i + 1]['year'];
                $y2 = $points[$i + 1]['price'];
                
                // Линейная интерполяция
                return $y1 + ($y2 - $y1) * ($targetYear - $x1) / ($x2 - $x1);
            }
        }
        
        // Если не нашли - используем медиану
        return $this->calculateMedian($points);
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
        
        // Ключевые слова и их влияние на цену
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
        
        // Если есть несколько дефектов - дополнительный штраф
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
        
        // Чем больше налет, тем ниже цена
        // 0-50 часов: почти не влияет
        // 50-100 часов: небольшое влияние
        // 100-200 часов: среднее влияние
        // 200+ часов: значительное влияние
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
     * 
     * @param Advertisement $advertisement Объявление для оценки
     * @param string $context Контекст (не используется, но оставлен для совместимости)
     * @return array|null Результат оценки
     */
    public function rateAdvertisement(Advertisement $advertisement, string $context): ?array
    {
        try {
            // Получаем аналоги из БД
            $similar = $this->getSimilarAdvertisements($advertisement, 20);
            
            // Если нет аналогов - возвращаем базовую оценку
            if (empty($similar)) {
                return $this->getBaseRating($advertisement);
            }
            
            $typeObject = $advertisement->getTypeObject();
            if (!$typeObject || !($typeObject instanceof AdvertisementGlider)) {
                // Пока поддерживаем только парапланы
                return $this->getBaseRating($advertisement);
            }
            
            $currentYear = (int)date('Y');
            $targetYear = (int)($typeObject->date_release ?? $currentYear);
            
            // 1. Интерполяция цены по году выпуска
            $interpolatedPrice = $this->interpolatePriceByYear($similar, $targetYear, $currentYear);
            
            // 2. Корректировка на состояние
            $conditionMultiplier = $this->getConditionMultiplier($typeObject->condition ?? 'good');
            
            // 3. Корректировка на дефекты
            $defectsMultiplier = $this->getDefectsMultiplier($typeObject->defects);
            
            // 4. Корректировка на налет
            $flightTimeMultiplier = $this->getFlightTimeMultiplier($typeObject->flight_time);
            
            // 5. Корректировка на цену в объявлении (если указана)
            $adPrice = (float)($advertisement->price ?? 0);
            
            // Рассчитываем базовую цену
            $basePrice = $interpolatedPrice ?? $this->calculateMedian($similar);
            
            // Применяем все коэффициенты
            $estimatedPrice = $basePrice * $conditionMultiplier * $defectsMultiplier * $flightTimeMultiplier;
            
            // Округляем до тысяч
            $estimatedPrice = round($estimatedPrice / 1000) * 1000;
            
            // Если цена указана в объявлении - сравниваем
            $priceDiff = 0;
            $priceAdvice = '';
            if ($adPrice > 0 && $estimatedPrice > 0) {
                $priceDiff = round(($adPrice - $estimatedPrice) / $estimatedPrice * 100);
                if ($priceDiff > 20) {
                    $priceAdvice = 'Цена завышена на ' . $priceDiff . '% относительно рыночной. Рекомендуется снизить цену.';
                } elseif ($priceDiff < -20) {
                    $priceAdvice = 'Цена занижена на ' . abs($priceDiff) . '% относительно рыночной. Отличное предложение!';
                } else {
                    $priceAdvice = 'Цена соответствует рыночной.';
                }
            }
            
            // Формируем результат
            $result = [
                'fair_price' => (int)$estimatedPrice,
                'price_range' => [
                    'min' => (int)round($estimatedPrice * 0.75 / 1000) * 1000,
                    'max' => (int)round($estimatedPrice * 1.25 / 1000) * 1000,
                ],
                'confidence' => $this->calculateConfidence($similar, $typeObject),
                'appeal' => $this->calculateAppeal($advertisement),
                'clarity' => $this->calculateClarity($advertisement),
                'relevance' => $this->calculateRelevance($advertisement, $similar),
                'call_to_action' => $this->calculateCallToAction($advertisement),
                'pros' => $this->generatePros($advertisement, $similar, $estimatedPrice, $adPrice),
                'cons' => $this->generateCons($advertisement, $similar, $estimatedPrice, $adPrice),
                'recommendations' => $this->generateRecommendations($advertisement, $estimatedPrice, $adPrice, $priceAdvice),
                'market_analysis' => $this->generateMarketAnalysis($similar, $targetYear, $estimatedPrice),
            ];
            
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
        $price = (float)($advertisement->price ?? 50000);
        $estimatedPrice = round($price / 1000) * 1000;
        
        return [
            'fair_price' => (int)$estimatedPrice,
            'price_range' => [
                'min' => (int)round($estimatedPrice * 0.7 / 1000) * 1000,
                'max' => (int)round($estimatedPrice * 1.3 / 1000) * 1000,
            ],
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
        
        // Наличие цены
        if ($ad->price && $ad->price > 0) $score += 1;
        
        // Наличие описания
        if ($ad->description && strlen($ad->description) > 50) $score += 1;
        
        // Наличие города
        if ($ad->city) $score += 0.5;
        
        // Наличие контактов
        if ($ad->phone || $ad->email || $ad->telegram || $ad->vk_profile_url) $score += 0.5;
        
        // Наличие фото
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
        
        // Наличие структуры (абзацев)
        if (substr_count($desc, "\n") >= 3) $score += 1;
        
        // Наличие ключевых слов
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
        
        // Сравниваем цену с аналогами
        if (!empty($similar)) {
            $prices = array_column(array_map(function($a) {
                return ['price' => (float)$a->price];
            }, $similar), 'price');
            
            $avgPrice = array_sum($prices) / count($prices);
            $adPrice = (float)($ad->price ?? 0);
            
            if ($adPrice > 0 && $avgPrice > 0) {
                $diff = abs($adPrice - $avgPrice) / $avgPrice;
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
        
        // Ключевые слова призыва
        $ctaWords = ['пишите', 'звоните', 'свяжитесь', 'телеграм', 'whatsapp', 'смотреть', 'вопрос'];
        foreach ($ctaWords as $word) {
            if (stripos($desc, $word) !== false) {
                $score += 0.5;
            }
        }
        
        // Наличие контактов
        if ($ad->phone || $ad->email || $ad->telegram || $ad->vk_profile_url) {
            $score += 1;
        }
        
        return min(10, (int)round($score));
    }
    
    /**
     * Генерация плюсов
     */
    private function generatePros(Advertisement $ad, array $similar, float $estimatedPrice, float $adPrice): string
    {
        $pros = [];
        $typeObject = $ad->getTypeObject();
        
        // Состояние
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
        
        // Цена
        if ($adPrice > 0 && $estimatedPrice > 0) {
            $diff = ($adPrice - $estimatedPrice) / $estimatedPrice;
            if ($diff < -0.1) {
                $pros[] = 'Цена ниже рыночной';
            }
        }
        
        // Наличие фото
        $imageCount = $ad->getImages()->count();
        if ($imageCount >= 5) {
            $pros[] = 'Много качественных фото';
        } elseif ($imageCount >= 3) {
            $pros[] = 'Есть фото';
        }
        
        // Контакты
        if ($ad->phone && $ad->telegram) {
            $pros[] = 'Указаны контакты для связи';
        }
        
        // Описание
        if ($ad->description && strlen($ad->description) > 100) {
            $pros[] = 'Подробное описание';
        }
        
        // Если плюсов мало - добавляем стандартные
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
    private function generateCons(Advertisement $ad, array $similar, float $estimatedPrice, float $adPrice): string
    {
        $cons = [];
        $typeObject = $ad->getTypeObject();
        
        // Дефекты
        if ($typeObject && $typeObject->defects) {
            $cons[] = 'Есть дефекты: ' . $typeObject->defects;
        }
        
        // Цена
        if ($adPrice > 0 && $estimatedPrice > 0) {
            $diff = ($adPrice - $estimatedPrice) / $estimatedPrice;
            if ($diff > 0.2) {
                $cons[] = 'Цена завышена';
            }
        }
        
        // Отсутствие описания
        if (empty($ad->description) || strlen($ad->description) < 50) {
            $cons[] = 'Краткое описание';
        }
        
        // Отсутствие фото
        $imageCount = $ad->getImages()->count();
        if ($imageCount < 3) {
            $cons[] = 'Мало фото';
        }
        
        // Отсутствие города
        if (empty($ad->city)) {
            $cons[] = 'Не указан город';
        }
        
        // Если минусов мало - добавляем стандартные
        if (count($cons) < 2) {
            $cons[] = 'Рекомендуется добавить больше информации';
        }
        
        return implode("\n", array_slice($cons, 0, 4));
    }
    
    /**
     * Генерация рекомендаций
     */
    private function generateRecommendations(Advertisement $ad, float $estimatedPrice, float $adPrice, string $priceAdvice): string
    {
        $recommendations = [];
        
        // Рекомендации по цене
        if ($priceAdvice) {
            $recommendations[] = $priceAdvice;
        }
        
        // Рекомендации по описанию
        if (empty($ad->description) || strlen($ad->description) < 50) {
            $recommendations[] = 'Добавьте подробное описание с указанием состояния, дефектов и комплектации.';
        }
        
        // Рекомендации по фото
        $imageCount = $ad->getImages()->count();
        if ($imageCount < 5) {
            $recommendations[] = 'Добавьте больше качественных фото (рекомендуется 5-10).';
        }
        
        // Рекомендации по контактам
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
    private function generateMarketAnalysis(array $similar, int $targetYear, float $estimatedPrice): string
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
                $prices[] = (float)$ad->price;
            }
            if ($typeObject->date_release) {
                $years[] = (int)$typeObject->date_release;
            }
        }
        
        if (empty($prices)) {
            return 'Нет данных о ценах аналогов.';
        }
        
        $minPrice = min($prices);
        $maxPrice = max($prices);
        $avgPrice = array_sum($prices) / count($prices);
        $medianPrice = $this->calculateMedian(array_map(function($p) { return ['price' => $p]; }, $prices));
        
        $yearRange = '';
        if (!empty($years)) {
            $minYear = min($years);
            $maxYear = max($years);
            $yearRange = "Диапазон годов выпуска: {$minYear} - {$maxYear}. ";
        }
        
        $analysis = "На основе анализа " . count($similar) . " аналогичных объявлений:\n";
        $analysis .= "- Средняя цена: " . number_format($avgPrice, 0, '.', ' ') . " ₽\n";
        $analysis .= "- Медианная цена: " . number_format($medianPrice, 0, '.', ' ') . " ₽\n";
        $analysis .= "- Диапазон цен: " . number_format($minPrice, 0, '.', ' ') . " - " . number_format($maxPrice, 0, '.', ' ') . " ₽\n";
        $analysis .= $yearRange;
        $analysis .= "Рекомендуемая цена: " . number_format($estimatedPrice, 0, '.', ' ') . " ₽";
        
        return $analysis;
    }
}