<?php
// FILE: .\components\CurrencyRateStorage.php

namespace app\components;

use Yii;
use yii\base\Component;

/**
 * Хранилище курсов валют в файле runtime/currency_rates.json
 *
 * Отвечает только за чтение/запись файла. Ничего не знает про API.
 */
class CurrencyRateStorage extends Component
{
    const DEFAULT_FILE_ALIAS = '@runtime/currency_rates.json';

    /**
     * @var string Путь к файлу (может содержать Yii-алиас)
     */
    public $filePath = self::DEFAULT_FILE_ALIAS;

    /**
     * Получить абсолютный путь к файлу
     *
     * @return string
     */
    public function getPath()
    {
        return Yii::getAlias($this->filePath);
    }

    /**
     * Проверить, существует ли файл
     *
     * @return bool
     */
    public function exists()
    {
        return is_file($this->getPath());
    }

    /**
     * Прочитать файл и вернуть массив
     *
     * @return array|null null если файла нет или он битый
     */
    public function load()
    {
        $path = $this->getPath();

        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            Yii::warning("Currency rates file is empty or unreadable: {$path}", 'currency');
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            Yii::warning("Currency rates file contains invalid JSON: {$path}", 'currency');
            return null;
        }

        if (!isset($data['rates']) || !is_array($data['rates'])) {
            Yii::warning("Currency rates file has no 'rates' key: {$path}", 'currency');
            return null;
        }

        return $data;
    }

    /**
     * Сохранить данные в файл (атомарно)
     *
     * @param array $data
     * @return bool
     */
    public function save(array $data)
    {
        $path = $this->getPath();
        $dir = dirname($path);

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                Yii::error("Cannot create directory for currency rates: {$dir}", 'currency');
                return false;
            }
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            Yii::error('Failed to encode currency rates to JSON: ' . json_last_error_msg(), 'currency');
            return false;
        }

        // Атомарная запись: пишем во временный файл, затем rename
        $tmpPath = $path . '.tmp';

        if (@file_put_contents($tmpPath, $json, LOCK_EX) === false) {
            Yii::error("Failed to write temporary currency rates file: {$tmpPath}", 'currency');
            return false;
        }

        if (!@rename($tmpPath, $path)) {
            @unlink($tmpPath);
            Yii::error("Failed to rename temporary currency rates file to: {$path}", 'currency');
            return false;
        }

        return true;
    }

    /**
     * Получить курсы валют
     *
     * @return array|null Например ['RUB' => 1, 'USD' => 85.5, 'EUR' => 92.3]
     */
    public function getRates()
    {
        $data = $this->load();
        if ($data === null) {
            return null;
        }

        return $data['rates'];
    }

    /**
     * Получить timestamp последнего обновления
     *
     * @return int|null
     */
    public function getFetchedAt()
    {
        $data = $this->load();
        if ($data === null || !isset($data['fetched_at'])) {
            return null;
        }

        return (int)$data['fetched_at'];
    }

    /**
     * Проверить, устарел ли файл
     *
     * @param int $ttl Время жизни в секундах (по умолчанию сутки)
     * @return bool
     */
    public function isStale($ttl = 86400)
    {
        $fetchedAt = $this->getFetchedAt();
        if ($fetchedAt === null) {
            return true;
        }

        return (time() - $fetchedAt) > $ttl;
    }
}