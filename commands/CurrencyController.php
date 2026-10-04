<?php
// FILE: .\commands\CurrencyController.php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use app\components\CurrencyRateFetcher;
use app\components\CurrencyRateStorage;

/**
 * Управление курсами валют
 *
 * Использование:
 *   php yii currency/update
 *   php yii currency/show
 */
class CurrencyController extends Controller
{
    /**
     * Обновить курсы валют из API ЦБ РФ
     *
     * @return int Exit code (0 — успех, 1 — ошибка)
     */
    public function actionUpdate()
    {
        $this->stdout("=== ОБНОВЛЕНИЕ КУРСОВ ВАЛЮТ ===\n", Console::FG_YELLOW);
        $this->stdout('Дата: ' . date('Y-m-d H:i:s') . "\n\n");

        $fetcher = new CurrencyRateFetcher();
        $result = $fetcher->updateAll();

        if ($result['success']) {
            $this->stdout("✅ Курсы успешно обновлены\n", Console::FG_GREEN);
            foreach ($result['rates'] as $currency => $rate) {
                $this->stdout("   {$currency}: {$rate}\n", Console::FG_CYAN);
            }
            $this->stdout("Источник: {$result['source']}\n", Console::FG_CYAN);

            return ExitCode::OK;
        }

        $this->stderr("❌ Не удалось обновить курсы\n", Console::FG_RED);
        $this->stderr("Причина: {$result['error']}\n", Console::FG_RED);

        if (!empty($result['rates'])) {
            $this->stdout("\nИспользуются прежние курсы:\n", Console::FG_YELLOW);
            foreach ($result['rates'] as $currency => $rate) {
                $this->stdout("   {$currency}: {$rate}\n", Console::FG_YELLOW);
            }
        } else {
            $this->stdout("\nПрежние курсы отсутствуют. Будет использован fallback из params.php\n", Console::FG_YELLOW);
        }

        return ExitCode::UNSPECIFIED_ERROR;
    }

    /**
     * Показать текущие курсы из файла
     *
     * @return int
     */
    public function actionShow()
    {
        $this->stdout("=== ТЕКУЩИЕ КУРСЫ ВАЛЮТ ===\n", Console::FG_YELLOW);

        $storage = new CurrencyRateStorage();
        $path = $storage->getPath();

        $this->stdout("Файл: {$path}\n", Console::FG_CYAN);

        if (!$storage->exists()) {
            $this->stdout("Файл не найден. Используется fallback из params.php\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        $fetchedAt = $storage->getFetchedAt();
        if ($fetchedAt) {
            $this->stdout('Обновлено: ' . date('Y-m-d H:i:s', $fetchedAt), Console::FG_CYAN);
            $age = time() - $fetchedAt;
            $this->stdout(' (' . $this->formatAge($age) . " назад)\n", Console::FG_CYAN);
        }

        $data = $storage->load();
        if ($data === null) {
            $this->stderr("Не удалось прочитать файл\n", Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $this->stdout("\nИсточник: " . ($data['source'] ?? 'неизвестен') . "\n", Console::FG_CYAN);
        $this->stdout("Базовая валюта: " . ($data['base'] ?? 'RUB') . "\n\n", Console::FG_CYAN);

        foreach ($data['rates'] as $currency => $rate) {
            $this->stdout(sprintf("   %-4s : %s\n", $currency, $rate), Console::FG_GREEN);
        }

        return ExitCode::OK;
    }

    /**
     * Форматирование возраста
     *
     * @param int $seconds
     * @return string
     */
    private function formatAge($seconds)
    {
        if ($seconds < 60) {
            return $seconds . ' сек.';
        }
        if ($seconds < 3600) {
            return round($seconds / 60) . ' мин.';
        }
        if ($seconds < 86400) {
            return round($seconds / 3600, 1) . ' ч.';
        }
        return round($seconds / 86400, 1) . ' дн.';
    }
}