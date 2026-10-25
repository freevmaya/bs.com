<?php
// FILE: components/WeMeasureParser.php

namespace app\components;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;
use app\models\Manufacturer;
use app\models\Wing;
use app\models\WingFile;
use app\models\WingLowerLine;
use app\models\WingUpperLine;
use app\models\ParseMismatch;

/**
 * Парсер сайта we-measure.io.
 *
 * Собирает данные каталога (/database), страниц карт строп (/user/wing/map/{id})
 * и файлов, на которых основана карта (блок Files used), пишет в БД.
 */
class WeMeasureParser extends Component
{
    const BASE_URL          = 'https://we-measure.io';
    const CATALOG_PATH      = '/database';
    const WING_PAGE_PATTERN = '/user/wing/map/%d';

    /**
     * @var int Таймаут HTTP-запроса в секундах
     */
    public $timeout = 30;

    /**
     * @var int Пауза между запросами (микросекунды)
     */
    public $usleepBetweenRequests = 300000;

    /**
     * @var bool Не проверять SSL-сертификат (для OpenServer без CA)
     */
    public $sslVerify = false;

    /**
     * @var string Путь к cookie-файлу в формате Netscape (может содержать Yii-алиас)
     */
    public $cookieFile = '@runtime/we-measure-cookies.txt';

    /**
     * @var array Счётчики для отчёта
     */
    public $stats = [
        'catalog_total' => 0,
        'parsed'        => 0,
        'updated'       => 0,
        'deleted'       => 0,
        'hidden'        => 0,
        'skipped'       => 0,
        'mismatches'    => 0,
        'errors'        => 0,
    ];

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var array<string,int> name => id, кэш производителей
     */
    private $manufacturersCache = [];

    public function init()
    {
        parent::init();

        $this->httpClient = new Client([
            'transport' => 'yii\httpclient\CurlTransport',
        ]);
    }

    /**
     * Основной метод: обход каталога и парсинг всех карт.
     *
     * Логика состояний:
     *  - delete         → физически удалить крыло, stats['deleted']++
     *  - require_update → перепарсить (как при --force), после успеха state=active, stats['updated']++
     *  - hide           → пропустить, stats['hidden']++
     *  - active + --force → перепарсить
     *  - active без --force → пропустить, stats['skipped']++
     *
     * --limit ограничивает количество УСПЕШНО СПАРСЕННЫХ страниц
     * (parsed + updated), а не пройденных по каталогу.
     *
     * maxAttempts считается от количества НОВЫХ (отсутствующих в БД)
     * source_id в каталоге: не больше limit*3, но не больше newCount.
     * Это защищает от обхода всего каталога при системных ошибках,
     * но не мешает пройти skip'ы уже спарсенных крыльев.
     *
     * @param int  $limit  0 = без ограничения
     * @param bool $force  true = перепарсить уже существующие source_id
     * @return array
     */
    public function run($limit = 0, $force = false)
    {
        $this->stats = [
            'catalog_total' => 0,
            'parsed'        => 0,
            'updated'       => 0,
            'deleted'       => 0,
            'hidden'        => 0,
            'skipped'       => 0,
            'mismatches'    => 0,
            'errors'        => 0,
        ];

        // Проверяем наличие cookie-файла
        $cookiePath = $this->getCookiePath();
        if (!is_file($cookiePath) || filesize($cookiePath) === 0) {
            Yii::warning(
                "we-measure: cookie file is missing or empty: {$cookiePath}. "
                . "Wing pages require authentication.",
                'we_measure'
            );
        } else {
            Yii::info("we-measure: using cookie file {$cookiePath}", 'we_measure');
        }

        $catalog = $this->parseCatalog();
        $this->stats['catalog_total'] = count($catalog);

        Yii::info('we-measure: catalog collected, items=' . count($catalog), 'we_measure');

        // Загружаем все существующие source_id одним запросом
        $existingIds = Wing::find()->select('source_id')->column();
        $existingIds = array_flip(array_map('intval', $existingIds));

        // Считаем, сколько новых в каталоге
        $newCount = 0;
        foreach ($catalog as $sourceId => $item) {
            if (!isset($existingIds[$sourceId])) {
                $newCount++;
            }
        }

        // maxAttempts: не больше limit*3, но не больше newCount (если новых мало)
        $maxAttempts = 0;
        if ($limit > 0) {
            $maxAttempts = $newCount > 0 ? min($newCount, $limit * 3) : 0;
        }

        $attempts = 0;

        foreach ($catalog as $sourceId => $item) {
            // Лимит по успешно спарсенным
            if ($limit > 0 && ($this->stats['parsed'] + $this->stats['updated']) >= $limit) {
                break;
            }

            // Защита от «бесконечного» обхода при системных ошибках
            if ($maxAttempts > 0 && $attempts >= $maxAttempts) {
                break;
            }
            $attempts++;

            $existing = Wing::find()->where(['source_id' => $sourceId])->one();

            // --- delete ---
            if ($existing !== null && $existing->state === Wing::STATE_DELETE) {
                try {
                    $existing->delete();
                    $this->stats['deleted']++;
                    Yii::info("we-measure: source_id={$sourceId} deleted (state=delete)", 'we_measure');
                } catch (\Throwable $e) {
                    $this->stats['errors']++;
                    Yii::error(
                        "we-measure: error deleting source_id={$sourceId}: " . $e->getMessage(),
                        'we_measure'
                    );
                }
                continue;
            }

            // --- hide ---
            if ($existing !== null && $existing->state === Wing::STATE_HIDE) {
                $this->stats['hidden']++;
                Yii::info("we-measure: source_id={$sourceId} hidden, skip", 'we_measure');
                continue;
            }

            // --- require_update ---
            $isRequireUpdate = $existing !== null && $existing->state === Wing::STATE_REQUIRE_UPDATE;

            // --- active без force ---
            if ($existing !== null && !$isRequireUpdate && !$force) {
                $this->stats['skipped']++;
                Yii::info("we-measure: source_id={$sourceId} already parsed, skip", 'we_measure');
                continue;
            }

            try {
                $ok = $this->parseAndSaveWingPage($sourceId, $item);

                if ($ok) {
                    if ($isRequireUpdate) {
                        $wing = Wing::find()->where(['source_id' => $sourceId])->one();
                        if ($wing !== null && $wing->state === Wing::STATE_REQUIRE_UPDATE) {
                            $wing->state = Wing::STATE_ACTIVE;
                            $wing->save(false, ['state']);
                        }
                        $this->stats['updated']++;
                    } else {
                        $this->stats['parsed']++;
                    }
                } else {
                    $this->stats['skipped']++;
                }
            } catch (\Throwable $e) {
                $this->stats['errors']++;
                Yii::error(
                    "we-measure: error parsing source_id={$sourceId}: "
                    . $e->getMessage() . "\n" . $e->getTraceAsString(),
                    'we_measure'
                );
            }

            if ($this->usleepBetweenRequests > 0) {
                usleep($this->usleepBetweenRequests);
            }
        }

        return $this->stats;
    }

    /**
     * Возвращает абсолютный путь к cookie-файлу.
     *
     * @return string
     */
    public function getCookiePath()
    {
        return Yii::getAlias($this->cookieFile);
    }

    /**
     * Проверяет, есть ли уже запись с таким source_id.
     *
     * @param int $sourceId
     * @return bool
     */
    protected function isAlreadyParsed($sourceId)
    {
        return Wing::find()->where(['source_id' => $sourceId])->exists();
    }

    /**
     * Обход каталога /database.
     *
     * @return array<int, array{
     *     manufacturer: string|null,
     *     website: string|null,
     *     model: string,
     *     year: int|null,
     *     size: string,
     *     catalog_full: string,
     * }>
     */
    public function parseCatalog()
    {
        $html = $this->fetch(self::BASE_URL . self::CATALOG_PATH);
        if ($html === null) {
            throw new \RuntimeException('Failed to fetch catalog page');
        }

        $result = [];

        // 1. Все <h3>…</h3> — производители
        $manufacturers = [];
        if (preg_match_all('~<h3>(.*?)</h3>~s', $html, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $i => $match) {
                $name = trim(html_entity_decode(strip_tags($match[0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($name === '') {
                    continue;
                }
                $manufacturers[] = [
                    'pos'  => $m[0][$i][1],
                    'name' => $name,
                ];
            }
        }

        // 2. Все <p class="description"><a href="…"> — сайты
        $websites = [];
        if (preg_match_all(
            '~<p class="description"><a href="([^"]+)"~s',
            $html,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            foreach ($m[1] as $i => $match) {
                $websites[] = [
                    'pos' => $m[0][$i][1],
                    'url' => $match[0],
                ];
            }
        }

        // 3. Все <h5 …>…</h5> — потенциальные модели
        $modelHeaders = [];
        if (preg_match_all('~<h5[^>]*>(.*?)</h5>~s', $html, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[1] as $i => $match) {
                $text = trim(html_entity_decode(strip_tags($match[0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($text === '') {
                    continue;
                }
                $modelHeaders[] = [
                    'pos'  => $m[0][$i][1],
                    'text' => $text,
                ];
            }
        }

        // 4. Все tile-ссылки — размеры
        $tiles = [];
        if (preg_match_all(
            '~<a class="tile"[^>]*href="(/user/wing/map/(\d+))"[^>]*>(.*?)</a>~s',
            $html,
            $m,
            PREG_OFFSET_CAPTURE
        )) {
            foreach ($m[2] as $i => $match) {
                $tiles[] = [
                    'pos'       => $m[0][$i][1],
                    'source_id' => (int)$match[0],
                    'size'      => trim(html_entity_decode(
                        strip_tags($m[3][$i][0]),
                        ENT_QUOTES | ENT_HTML5,
                        'UTF-8'
                    )),
                ];
            }
        }

        if (empty($tiles)) {
            Yii::warning('we-measure: no tile links found in catalog', 'we_measure');
            return $result;
        }

        // 5. Собираем результат
        foreach ($tiles as $tile) {
            $pos = $tile['pos'];

            $manufacturer = $this->findNearestBefore($manufacturers, $pos);
            $modelHeader  = $this->findNearestBefore($modelHeaders, $pos);

            if ($modelHeader === null) {
                continue;
            }

            [$model, $year] = $this->splitModelYear($modelHeader['text']);
            if ($year === null && !$this->looksLikeModelHeader($modelHeader['text'])) {
                continue;
            }

            $manufacturerName = $manufacturer['name'] ?? null;

            $website = null;
            if ($manufacturer !== null) {
                $website = $this->findWebsiteBetween(
                    $websites,
                    $manufacturer['pos'],
                    $pos
                );
            }

            $catalogFull = trim($modelHeader['text'] . ' ' . $tile['size']);

            $result[$tile['source_id']] = [
                'manufacturer' => $manufacturerName,
                'website'      => $website,
                'model'        => $model,
                'year'         => $year,
                'size'         => $tile['size'],
                'catalog_full' => $catalogFull,
            ];
        }

        Yii::info(
            'we-measure: catalog parsed, tiles=' . count($tiles)
            . ', manufacturers=' . count($manufacturers)
            . ', models=' . count($modelHeaders)
            . ', result=' . count($result),
            'we_measure'
        );

        return $result;
    }

    /**
     * Находит последний элемент массива, у которого pos < $pos.
     *
     * @param array $items
     * @param int   $pos
     * @return array|null
     */
    protected function findNearestBefore(array $items, $pos)
    {
        $found = null;
        foreach ($items as $item) {
            if ($item['pos'] < $pos) {
                $found = $item;
            } else {
                break;
            }
        }
        return $found;
    }

    /**
     * Находит URL сайта производителя между позициями $fromPos и $toPos.
     *
     * @param array $websites
     * @param int   $fromPos
     * @param int   $toPos
     * @return string|null
     */
    protected function findWebsiteBetween(array $websites, $fromPos, $toPos)
    {
        foreach ($websites as $w) {
            if ($w['pos'] >= $fromPos && $w['pos'] <= $toPos) {
                return $w['url'];
            }
        }
        return null;
    }

    /**
     * Эвристика: похож ли текст <h5> на название модели параплана.
     *
     * @param string $text
     * @return bool
     */
    protected function looksLikeModelHeader($text)
    {
        $serviceWords = [
            'files used', 'overall', 'filter', 'layout', 'validity',
            'status', 'data', 'available paraglider models',
        ];
        $lower = mb_strtolower($text);
        foreach ($serviceWords as $word) {
            if ($lower === $word || mb_strpos($lower, $word) === 0) {
                return false;
            }
        }

        if (!preg_match('~[a-zа-яё]~iu', $text)) {
            return false;
        }

        return true;
    }

    /**
     * Парсит "Eazy (2013)" → ['Eazy', 2013].
     *
     * @param string $modelFull
     * @return array{0: string, 1: int|null}
     */
    protected function splitModelYear($modelFull)
    {
        if (preg_match('~^(.*?)\s*\((\d{4})\)\s*$~u', $modelFull, $m)) {
            return [trim($m[1]), (int)$m[2]];
        }
        return [trim($modelFull), null];
    }

    /**
     * Парсит одну страницу карты строп и сохраняет в БД.
     *
     * @param int   $sourceId
     * @param array $catalogItem
     * @return bool
     */
    public function parseAndSaveWingPage($sourceId, array $catalogItem)
    {
        $url  = self::BASE_URL . sprintf(self::WING_PAGE_PATTERN, $sourceId);
        $html = $this->fetch($url);

        if ($html === null) {
            throw new \RuntimeException("Failed to fetch wing page: {$url}");
        }

        // 1. Название из <h2>
        $pageFullName = null;
        if (preg_match('~<h2>(.*?)</h2>~s', $html, $m)) {
            $pageFullName = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($pageFullName === null || $pageFullName === '') {
            if (mb_strpos($html, "url='/login'") !== false
                || mb_strpos($html, 'url="/login"') !== false
                || mb_strpos($html, 'Redirecting to') !== false
            ) {
                Yii::warning(
                    "we-measure: page {$url} redirects to /login. "
                    . "Cookie file is stale — update runtime/we-measure-cookies.txt",
                    'we_measure'
                );
            } else {
                Yii::warning("we-measure: no <h2> on page {$url}", 'we_measure');
            }
            return false;
        }

        // 1a. Блок Files used
        $filesUsed = $this->parseFilesUsed($html);

        // 2. Блок Data -> <table class="flex">
        $dataTableHtml = $this->extractDataTable($html);
        if ($dataTableHtml === null) {
            Yii::warning("we-measure: no <h3>Data</h3> table on page {$url}", 'we_measure');
            return false;
        }

        // 3. Разбор групп
        $groups = $this->parseGroups($dataTableHtml);
        if (empty($groups)) {
            Yii::warning("we-measure: no groups parsed on page {$url}", 'we_measure');
            return false;
        }

        // 4. Сверка с каталогом
        $catalogFull = trim(($catalogItem['model'] ?? '') . ' ' . ($catalogItem['size'] ?? ''));
        $catalogValueForLog = $catalogItem['catalog_full'] ?? $catalogFull;

        $matches = $this->catalogMatches($pageFullName, $catalogItem);
        if (!$matches) {
            $this->stats['mismatches']++;
            $this->logMismatch($sourceId, $catalogValueForLog, $pageFullName);
        }

        // 5. Сохранение в транзакции
        $transaction = Yii::$app->db->beginTransaction();
        try {
            $manufacturerId = $this->resolveManufacturer(
                $catalogItem['manufacturer'] ?? null,
                $catalogItem['website'] ?? null
            );

            $wing = Wing::find()->where(['source_id' => $sourceId])->one();
            if ($wing === null) {
                $wing = new Wing();
                $wing->source_id = $sourceId;
                $wing->state = Wing::STATE_ACTIVE;
            }

            $wing->manufacturer_id = $manufacturerId;
            $wing->model           = $catalogItem['model'] ?? '';
            $wing->model_year      = $catalogItem['year'] ?? null;
            $wing->size            = $catalogItem['size'] ?? '';
            $wing->full_name       = $pageFullName;
            $wing->source_url      = $url;
            $wing->catalog_match   = $matches ? 1 : 0;
            $wing->parsed_at       = date('Y-m-d H:i:s');

            if (!$wing->save()) {
                throw new \RuntimeException('Failed to save Wing: ' . json_encode($wing->errors));
            }

            // Files used: удаляем старые, пишем новые
            WingFile::deleteAll(['wing_id' => $wing->id]);
            foreach ($filesUsed as $sortOrder => $file) {
                $wingFile = new WingFile();
                $wingFile->wing_id    = $wing->id;
                $wingFile->name       = mb_substr($file['name'], 0, 255);
                $wingFile->url        = mb_substr($file['url'], 0, 500);
                $wingFile->file_date  = $file['date'];
                $wingFile->sort_order = $sortOrder;

                if (!$wingFile->save()) {
                    throw new \RuntimeException(
                        'Failed to save WingFile: ' . json_encode($wingFile->errors)
                    );
                }
            }

            // Пересоздаём строки (idempotent при --force)
            WingUpperLine::deleteAll(['wing_id' => $wing->id]);
            WingLowerLine::deleteAll(['wing_id' => $wing->id]);

            foreach ($groups as $groupIndex => $group) {
                $lower = new WingLowerLine();
                $lower->wing_id     = $wing->id;
                $lower->group_index = $groupIndex;
                $lower->name        = $group['name'];
                $lower->length_mm   = $group['riser_length'];
                $lower->riser_note  = $group['riser_note'];
                $lower->raw_value   = $group['riser_raw'];

                if (!$lower->save()) {
                    throw new \RuntimeException(
                        'Failed to save WingLowerLine: ' . json_encode($lower->errors)
                    );
                }

                foreach ($group['upper_lines'] as $sortOrder => $upper) {
                    $line = new WingUpperLine();
                    $line->wing_id       = $wing->id;
                    $line->lower_line_id = $lower->id;
                    $line->name          = $upper['name'];
                    $line->length_mm     = $upper['length'];
                    $line->sort_order    = $sortOrder;

                    if (!$line->save()) {
                        throw new \RuntimeException(
                            'Failed to save WingUpperLine: ' . json_encode($line->errors)
                        );
                    }
                }
            }

            $transaction->commit();

            Yii::info(
                "we-measure: parsed source_id={$sourceId} ({$pageFullName}), "
                . "groups=" . count($groups) . ", files=" . count($filesUsed),
                'we_measure'
            );

            return true;

        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Парсит блок "Files used".
     *
     * @param string $html
     * @return array<int, array{
     *     name: string,
     *     url: string,
     *     date: string|null,
     * }>
     */
    protected function parseFilesUsed($html)
    {
        $files = [];

        if (!preg_match('~<h5>\s*Files used\s*</h5>(.*?)</div>\s*</div>~s', $html, $blockMatch)) {
            return $files;
        }

        $block = $blockMatch[1];

        if (!preg_match_all(
            '~<div class="super">\s*<sup>(.*?)</sup>\s*<a[^>]*href="([^"]+)"[^>]*>(.*?)</a>\s*</div>~s',
            $block,
            $matches,
            PREG_SET_ORDER
        )) {
            return $files;
        }

        foreach ($matches as $m) {
            $date = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url  = trim($m[2]);
            $name = trim(html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            $name = str_replace(["\xC2\xA0", '&nbsp;'], ' ', $name);
            $name = trim($name);

            if ($name === '' || $url === '') {
                continue;
            }

            if (strpos($url, '/') === 0) {
                $url = self::BASE_URL . $url;
            }

            $dateValue = null;
            if ($date !== '' && preg_match('~^\d{4}-\d{2}-\d{2}$~', $date)) {
                $dateValue = $date;
            }

            $files[] = [
                'name' => $name,
                'url'  => $url,
                'date' => $dateValue,
            ];
        }

        return $files;
    }

    /**
     * Извлекает HTML <table class="flex"> из блока <h3>Data</h3>.
     *
     * @param string $html
     * @return string|null
     */
    protected function extractDataTable($html)
    {
        $pos = mb_strpos($html, '<h3>Data</h3>');
        if ($pos === false) {
            return null;
        }

        $tail = mb_substr($html, $pos);
        $tablePos = mb_strpos($tail, '<table class="flex">');
        if ($tablePos === false) {
            return null;
        }

        $depth = 0;
        $len = mb_strlen($tail);
        $i = $tablePos;
        $end = null;

        while ($i < $len) {
            if (mb_substr($tail, $i, 7) === '<table ') {
                $depth++;
                $i += 7;
                continue;
            }
            if (mb_substr($tail, $i, 8) === '</table>') {
                $depth--;
                $i += 8;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
                continue;
            }
            $i++;
        }

        if ($end === null) {
            return null;
        }

        return mb_substr($tail, $tablePos, $end - $tablePos);
    }

    /**
     * Разбирает HTML таблицы flex на группы.
     *
     * @param string $tableHtml
     * @return array
     */
    protected function parseGroups($tableHtml)
    {
        $groups = [];

        if (!preg_match_all(
            '~<tr\s+class="group"[^>]*>~s',
            $tableHtml,
            $groupOpenMatches,
            PREG_OFFSET_CAPTURE
        )) {
            Yii::warning('we-measure: parseGroups - no <tr class="group"> found', 'we_measure');
            return $groups;
        }

        $groupCount = count($groupOpenMatches[0]);

        for ($i = 0; $i < $groupCount; $i++) {
            $startPos = $groupOpenMatches[0][$i][1];
            $endPos   = $i + 1 < $groupCount
                ? $groupOpenMatches[0][$i + 1][1]
                : mb_strlen($tableHtml);

            $block = mb_substr($tableHtml, $startPos, $endPos - $startPos);

            $groupName = null;
            if (preg_match('~<tr\s+class="group"[^>]*>\s*<th>(.*?)</th>~s', $block, $m)) {
                $groupName = trim(html_entity_decode(
                    strip_tags($m[1]),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8'
                ));
            }

            if ($groupName === null || $groupName === '') {
                continue;
            }

            if (!preg_match('~<table class="rows">(.*?)</table>~s', $block, $rowsMatch)) {
                continue;
            }

            $rowsHtml = $rowsMatch[1];

            if (!preg_match_all(
                '~<tr>\s*<th>(.*?)</th>\s*<td>(.*?)</td>\s*</tr>~s',
                $rowsHtml,
                $rowMatches,
                PREG_SET_ORDER
            )) {
                Yii::warning(
                    "we-measure: parseGroups - group '{$groupName}' has no row matches",
                    'we_measure'
                );
                continue;
            }

            $riserLength = null;
            $riserNote   = null;
            $riserRaw    = null;
            $upperLines  = [];

            foreach ($rowMatches as $rm) {
                $nameRaw  = trim($rm[1]);
                $valueRaw = trim($rm[2]);

                if ($nameRaw === '&nbsp;' || $nameRaw === '') {
                    $riserRaw = $valueRaw;
                    continue;
                }

                $name  = html_entity_decode(strip_tags($nameRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $value = html_entity_decode(strip_tags($valueRaw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                if ($name === 'Riser length') {
                    $riserRaw = $value;
                    [$riserLength, $riserNote] = $this->parseRiserValue($value);
                    continue;
                }

                $upperLines[] = [
                    'name'   => $name,
                    'length' => $this->parseNumber($value),
                ];
            }

            $groups[] = [
                'name'         => $groupName,
                'riser_length' => $riserLength,
                'riser_note'   => $riserNote,
                'riser_raw'    => $riserRaw,
                'upper_lines'  => $upperLines,
            ];
        }

        if (empty($groups)) {
            Yii::warning(
                'we-measure: parseGroups returned 0 groups from table length ' . mb_strlen($tableHtml),
                'we_measure'
            );
        }

        return $groups;
    }

    /**
     * Разбирает значение "Riser length".
     *
     * @param string $value
     * @return array{0: float|null, 1: string|null}
     */
    public function parseRiserValue($value)
    {
        $value = trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($value === '' || $value === '&nbsp;') {
            return [null, null];
        }

        if (preg_match('~^([\d.,]+)\s*\(\s*([^)]+)\s*\)$~u', $value, $m)) {
            $number = $this->parseNumber($m[1]);
            $note   = trim($m[2]);
            return [$number, $note];
        }

        if (mb_strtolower($value) === 'included') {
            return [0.0, 'included'];
        }

        if (preg_match('~^[\d.,]+$~u', $value)) {
            return [$this->parseNumber($value), null];
        }

        return [null, $value];
    }

    /**
     * Парсит число: "5905" → 5905.0, "5905,5" → 5905.5.
     *
     * @param string $value
     * @return float
     */
    protected function parseNumber($value)
    {
        $value = trim($value);
        $value = str_replace([' ', "\xC2\xA0"], '', $value);
        $value = str_replace(',', '.', $value);
        return (float)$value;
    }

    /**
     * Сверяет данные страницы с каталогом.
     *
     * @param string $pageFullName
     * @param array  $catalogItem
     * @return bool
     */
    protected function catalogMatches($pageFullName, array $catalogItem)
    {
        $manufacturer = $catalogItem['manufacturer'] ?? null;
        $model        = $catalogItem['model'] ?? null;
        $year         = $catalogItem['year'] ?? null;
        $size         = $catalogItem['size'] ?? null;

        $pageLower = mb_strtolower($pageFullName);

        if ($manufacturer !== null && $manufacturer !== '') {
            if (mb_strpos($pageLower, mb_strtolower($manufacturer)) === false) {
                return false;
            }
        }

        if ($model !== null && $model !== '') {
            if (mb_strpos($pageLower, mb_strtolower($model)) === false) {
                return false;
            }
        }

        if ($year !== null) {
            if (mb_strpos($pageFullName, '(' . $year . ')') === false
                && !preg_match('~\b' . preg_quote((string)$year, '~') . '\b~', $pageFullName)
            ) {
                return false;
            }
        }

        if ($size !== null && $size !== '') {
            if (!preg_match(
                '~(?:^|[\s(])' . preg_quote($size, '~') . '(?:[\s)]|$)~u',
                $pageFullName
            )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Записывает расхождение в лог.
     *
     * @param int    $sourceId
     * @param string $catalogValue
     * @param string $pageValue
     * @return void
     */
    protected function logMismatch($sourceId, $catalogValue, $pageValue)
    {
        $log = new ParseMismatch();
        $log->source_id     = $sourceId;
        $log->catalog_value = mb_substr((string)$catalogValue, 0, 255);
        $log->page_value    = mb_substr((string)$pageValue, 0, 255);
        $log->detected_at   = date('Y-m-d H:i:s');

        if (!$log->save()) {
            Yii::warning(
                "we-measure: failed to log mismatch for source_id={$sourceId}: "
                . json_encode($log->errors),
                'we_measure'
            );
        }
    }

    /**
     * Возвращает id производителя по имени, создавая его при необходимости.
     *
     * layout_method не задаём — остаётся NULL, заполняется вручную.
     *
     * @param string|null $name
     * @param string|null $website
     * @return int|null
     */
    protected function resolveManufacturer($name, $website = null)
    {
        if ($name === null || $name === '') {
            return null;
        }

        if (isset($this->manufacturersCache[$name])) {
            return $this->manufacturersCache[$name];
        }

        $manufacturer = Manufacturer::find()->where(['name' => $name])->one();
        if ($manufacturer === null) {
            $manufacturer = new Manufacturer();
            $manufacturer->name = $name;
        }

        if ($website !== null && $website !== '' && $manufacturer->website !== $website) {
            $manufacturer->website = $website;
        }

        if ($manufacturer->save()) {
            $this->manufacturersCache[$name] = (int)$manufacturer->id;
            return (int)$manufacturer->id;
        }

        Yii::warning(
            "we-measure: failed to save manufacturer '{$name}': "
            . json_encode($manufacturer->errors),
            'we_measure'
        );

        return null;
    }

    /**
     * Загружает URL, возвращает HTML или null при ошибке.
     *
     * @param string $url
     * @return string|null
     */
    protected function fetch($url)
    {
        try {
            $options = [
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                    . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                    . 'Chrome/124.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER     => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: en-US,en;q=0.9',
                    'Referer: https://we-measure.io/database',
                ],
            ];

            if (!$this->sslVerify) {
                $options[CURLOPT_SSL_VERIFYPEER] = false;
                $options[CURLOPT_SSL_VERIFYHOST] = false;
            }

            $cookiePath = $this->getCookiePath();
            if (is_file($cookiePath) && filesize($cookiePath) > 0) {
                $options[CURLOPT_COOKIEFILE] = $cookiePath;
            }

            $response = $this->httpClient->createRequest()
                ->setMethod('GET')
                ->setUrl($url)
                ->setOptions($options)
                ->send();

            if (!$response->isOk) {
                Yii::warning(
                    "we-measure: HTTP {$response->statusCode} for {$url}",
                    'we_measure'
                );
                return null;
            }

            return $response->content;

        } catch (\Throwable $e) {
            Yii::error(
                "we-measure: fetch error for {$url}: " . $e->getMessage(),
                'we_measure'
            );
            return null;
        }
    }
}