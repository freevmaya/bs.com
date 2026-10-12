<?php
// FILE: .\helpers\CurrencyFormatter.php

namespace app\helpers;

use Yii;

/**
 * Хелпер для отображения цен с конвертацией в выбранную пользователем валюту.
 *
 * Логика:
 *  - Если GET-параметр display_currency пуст — цена показывается в валюте объявления (как есть).
 *  - Если указана конкретная валюта — цена конвертируется по текущему курсу.
 *
 * Курсы берутся из CurrencyRate::getRates() (fallback — params.php).
 */
class CurrencyFormatter
{
    /**
     * Символы валют
     */
    const SYMBOLS = [
        'RUB' => '₽',
        'USD' => '$',
        'EUR' => '€',
    ];

    /**
     * Получить символ валюты
     *
     * @param string $currency
     * @return string
     */
    public static function getSymbol($currency)
    {
        $currency = strtoupper((string)$currency);
        return self::SYMBOLS[$currency] ?? $currency;
    }

    /**
     * Получить валюту отображения из GET-параметра
     *
     * @return string|null Код валюты (RUB/USD/EUR) или null, если отображение "как есть"
     */
    public static function getDisplayCurrency()
    {
        $currency = Yii::$app->request->get('display_currency', '');
        $currency = strtoupper(trim((string)$currency));

        if ($currency === '' || !isset(self::SYMBOLS[$currency])) {
            return null;
        }

        return $currency;
    }

    /**
     * Конвертировать сумму из одной валюты в другую по курсу
     *
     * @param float  $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float|null Сконвертированная сумма или null, если курс неизвестен
     */
    public static function convert($amount, $fromCurrency, $toCurrency)
    {
        $fromCurrency = strtoupper((string)$fromCurrency);
        $toCurrency   = strtoupper((string)$toCurrency);

        if ($fromCurrency === $toCurrency) {
            return (float)$amount;
        }

        $rates = CurrencyRate::getRates();

        $rateFrom = isset($rates[$fromCurrency]) ? (float)$rates[$fromCurrency] : null;
        $rateTo   = isset($rates[$toCurrency])   ? (float)$rates[$toCurrency]   : null;

        if ($rateFrom === null || $rateTo === null || $rateFrom <= 0 || $rateTo <= 0) {
            return null;
        }

        // Приводим к базовой (RUB), затем к целевой
        $amountInBase   = (float)$amount * $rateFrom;
        $amountInTarget = $amountInBase / $rateTo;

        return $amountInTarget;
    }

    /**
     * Отформатировать цену для отображения.
     *
     * Если валюта отображения задана — конвертирует из валюты объявления.
     * Если не задана или конвертация невозможна — показывает как есть.
     *
     * @param float|null  $amount                Сумма
     * @param string|null $advertisementCurrency Валюта объявления (RUB/USD/EUR)
     * @return string Готовая строка, например "165 000 ₽"
     */
    public static function display($amount, $advertisementCurrency = null)
    {
        if ($amount === null || $amount === '' || (float)$amount <= 0) {
            return 'Цена не указана';
        }

        $amount = (float)$amount;
        $advertisementCurrency = strtoupper((string)($advertisementCurrency ?: 'RUB'));

        $displayCurrency = self::getDisplayCurrency();

        // Режим "Все валюты" — показываем как есть
        if ($displayCurrency === null) {
            return number_format($amount, 0, '.', ' ') . ' ' . self::getSymbol($advertisementCurrency);
        }

        // Режим конвертации
        $converted = self::convert($amount, $advertisementCurrency, $displayCurrency);

        if ($converted === null) {
            // Курс неизвестен — fallback на валюту объявления
            return number_format($amount, 0, '.', ' ') . ' ' . self::getSymbol($advertisementCurrency);
        }

        return number_format($converted, 0, '.', ' ') . ' ' . self::getSymbol($displayCurrency);
    }

    /**
     * Получить список валют для выпадающего списка
     *
     * @return array ['RUB' => '₽ RUB', 'USD' => '$ USD', 'EUR' => '€ EUR']
     */
    public static function getCurrencyList()
    {
        return [
            'RUB' => '₽ RUB',
            'USD' => '$ USD',
            'EUR' => '€ EUR',
        ];
    }
}