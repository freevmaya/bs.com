<?php
// FILE: commands/ParseWeMeasureController.php

namespace app\commands;

use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use app\components\WeMeasureParser;

/**
 * Парсер сайта we-measure.io.
 *
 * Использование:
 *   php yii parse-we-measure/run
 *   php yii parse-we-measure/run --limit=10
 *   php yii parse-we-measure/run --force=1
 *
 * Вывод пишется напрямую в STDOUT/STDERR через fwrite(),
 * чтобы не задействовать yii\helpers\Console и не получать
 * предупреждение "stdout is not a tty" при перенаправлении в файл.
 */
class ParseWeMeasureController extends Controller
{
    /**
     * @var int Ограничение количества обрабатываемых страниц (0 = без ограничения)
     */
    public $limit = 0;

    /**
     * @var int Перепарсить уже существующие записи (1 = да)
     */
    public $force = 0;

    /**
     * {@inheritdoc}
     */
    public function options($actionID)
    {
        return array_merge(parent::options($actionID), [
            'limit',
            'force',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'l' => 'limit',
            'f' => 'force',
        ]);
    }

    /**
     * Запуск парсера.
     *
     * @return int
     */
    public function actionRun()
    {
        $startTime = microtime(true);

        $this->out('=== ПАРСЕР WE-MEASURE.IO ===');
        $this->out('Дата: ' . date('Y-m-d H:i:s'));
        $this->out('Лимит: ' . ($this->limit > 0 ? $this->limit : 'без ограничения'));
        $this->out('Force: ' . ($this->force ? 'ДА' : 'НЕТ'));
        $this->out('');

        /** @var WeMeasureParser $parser */
        $parser = new WeMeasureParser();

        try {
            $stats = $parser->run((int)$this->limit, (bool)$this->force);
        } catch (\Throwable $e) {
            $this->err('Ошибка: ' . $e->getMessage());
            Yii::error(
                'parse-we-measure fatal: ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
                'we_measure'
            );
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->out('');
        $this->out('=== ИТОГИ ===');
        $this->out('Найдено в каталоге: ' . $stats['catalog_total']);
        $this->out('Спарсено:          ' . $stats['parsed']);
        $this->out('Пропущено:         ' . $stats['skipped']);
        $this->out('Расхождений:       ' . $stats['mismatches']);
        $this->out('Ошибок:            ' . $stats['errors']);
        $this->out('Время выполнения:  ' . $elapsed . ' сек.');

        return $stats['errors'] > 0 ? ExitCode::UNSPECIFIED_ERROR : ExitCode::OK;
    }

    /**
     * Пишет строку в STDOUT напрямую, минуя yii\helpers\Console.
     *
     * @param string $text
     * @return void
     */
    private function out(string $text): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    /**
     * Пишет строку в STDERR напрямую, минуя yii\helpers\Console.
     *
     * @param string $text
     * @return void
     */
    private function err(string $text): void
    {
        fwrite(STDERR, $text . PHP_EOL);
    }
}