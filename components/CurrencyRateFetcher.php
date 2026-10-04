<?php
// FILE: .\components\CurrencyRateFetcher.php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * Загрузчик курсов валют из внешнего API (cbr-xml-daily.ru)
 *
 * Отвечает за получение курсов и сохранение через CurrencyRateStorage.
 */
class CurrencyRateFetcher extends Component
{
    const SOURCE_CBR = 'cbr-xml-daily';
    const SOURCE_FALLBACK = 'fallback';

    /**
     * @var string URL API
     */
    public $sourceUrl = 'https://www.cbr-xml-daily.ru/daily_json.js';

    /**
     * @var int Таймаут HTTP-запроса в секундах
     */
    public $timeout = 10;

    /**
     * @var array Список поддерживаемых валют
     */
    public $supportedCurrencies = ['RUB', 'USD', 'EUR'];

    /**
     * @var string Базовая валюта
     */
    public $baseCurrency = 'RUB';

    /**
     * @var CurrencyRateStorage
     */
    private $storage;

    /**
     * @param CurrencyRateStorage|null $storage
     * @param array $config
     */
    public function __construct(CurrencyRateStorage $storage = null, $config = [])
    {
        $this->storage = $storage ?: new CurrencyRateStorage();
        parent::__construct($config);
    }

    /**
     * Получить курсы из API ЦБ РФ
     *
     * @return array|null ['RUB' => 1, 'USD' => 85.5, 'EUR' => 92.3] или null при ошибке
     */
    public function fetchFromCbr()
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->sourceUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'BS.com CurrencyRateFetcher/1.0',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            Yii::warning(
                "CBR API request failed: HTTP={$httpCode}, error={$curlError}",
                'currency'
            );
            return null;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['Valute']) || !is_array($data['Valute'])) {
            Yii::error(
                'Invalid CBR API response: ' . substr($response, 0, 200),
                'currency'
            );
            return null;
        }

        $rates = [$this->baseCurrency => 1];

        foreach ($this->supportedCurrencies as $currency) {
            if ($currency === $this->baseCurrency) {
                continue;
            }

            if (!isset($data['Valute'][$currency])) {
                Yii::warning("Currency {$currency} not found in CBR response", 'currency');
                continue;
            }

            $item = $data['Valute'][$currency];
            $nominal = isset($item['Nominal']) ? (int)$item['Nominal'] : 1;
            $value = isset($item['Value']) ? (float)$item['Value'] : 0;

            if ($nominal <= 0 || $value <= 0) {
                Yii::warning("Invalid rate for {$currency}: Value={$value}, Nominal={$nominal}", 'currency');
                continue;
            }

            $rate = $value / $nominal;

            // Валидация: разумные пределы
            if ($rate < 0.01 || $rate > 10000) {
                Yii::warning("Rate for {$currency} is out of reasonable range: {$rate}", 'currency');
                continue;
            }

            $rates[$currency] = round($rate, 4);
        }

        // Проверяем, что получили все валюты из whitelist
        foreach ($this->supportedCurrencies as $currency) {
            if (!isset($rates[$currency])) {
                Yii::error("Failed to fetch rate for {$currency}", 'currency');
                return null;
            }
        }

        return $rates;
    }

    /**
     * Обновить курсы валют (основной метод)
     *
     * @return array Отчёт: ['success' => bool, 'rates' => array|null, 'source' => string, 'error' => string|null]
     */
    public function updateAll()
    {
        $rates = $this->fetchFromCbr();

        if ($rates === null) {
            // API недоступен — не трогаем файл
            $existingRates = $this->storage->getRates();

            return [
                'success' => false,
                'rates' => $existingRates,
                'source' => $existingRates ? 'existing' : 'none',
                'error' => 'CBR API is unavailable',
            ];
        }

        $data = [
            'base' => $this->baseCurrency,
            'fetched_at' => time(),
            'source' => self::SOURCE_CBR,
            'rates' => $rates,
        ];

        if (!$this->storage->save($data)) {
            return [
                'success' => false,
                'rates' => $rates,
                'source' => self::SOURCE_CBR,
                'error' => 'Failed to save currency rates to file',
            ];
        }

        Yii::info(
            'Currency rates updated: ' . json_encode($rates, JSON_UNESCAPED_UNICODE),
            'currency'
        );

        return [
            'success' => true,
            'rates' => $rates,
            'source' => self::SOURCE_CBR,
            'error' => null,
        ];
    }
}