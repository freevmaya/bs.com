<?php
// FILE: .\helpers\CurrencyRate.php

namespace app\helpers;

use Yii;
use app\components\CurrencyRateStorage;

/**
 * Хелпер для получения курсов валют
 *
 * Единая точка доступа к курсам валют с fallback-цепочкой:
 *   1. Файл runtime/currency_rates.json
 *   2. params.php['currency_rates']
 *   3. Жёсткий дефолт
 */
class CurrencyRate
{
    /**
     * @var array|null Кэш курсов в рамках одного запроса
     */
    private static $ratesCache = null;

    /**
     * Получить все курсы валют
     *
     * @return array Например ['RUB' => 1, 'USD' => 85.5, 'EUR' => 92.3]
     */
    public static function getRates()
    {
        if (self::$ratesCache !== null) {
            return self::$ratesCache;
        }

        // 1. Пробуем файл
        try {
            $storage = new CurrencyRateStorage();
            $rates = $storage->getRates();
            if (!empty($rates) && is_array($rates)) {
                self::$ratesCache = $rates;
                return $rates;
            }
        } catch (\Exception $e) {
            Yii::warning('Failed to read currency rates from file: ' . $e->getMessage(), 'currency');
        }

        // 2. Fallback на params.php
        $rates = Yii::$app->params['currency_rates'] ?? null;
        if (!empty($rates) && is_array($rates)) {
            self::$ratesCache = $rates;
            return $rates;
        }

        // 3. Жёсткий дефолт
        self::$ratesCache = ['RUB' => 1];
        return self::$ratesCache;
    }

    /**
     * Получить курс конкретной валюты к базовой
     *
     * @param string $currency
     * @return float
     */
    public static function getRate($currency)
    {
        $currency = strtoupper((string)$currency);
        $rates = self::getRates();

        if (isset($rates[$currency])) {
            return (float)$rates[$currency];
        }

        // Неизвестная валюта — 1:1
        return 1.0;
    }

    /**
     * Получить базовую валюту
     *
     * @return string
     */
    public static function getBaseCurrency()
    {
        return Yii::$app->params['base_currency'] ?? 'RUB';
    }

    /**
     * Сбросить кэш (для тестов/отладки)
     */
    public static function clearCache()
    {
        self::$ratesCache = null;
    }
}