<?php
// FILE: controllers/WingsController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use app\models\Wing;
use app\models\WingLowerLine;
use app\models\WingUpperLine;

/**
 * Публичный просмотр сохранённых крыльев.
 */
class WingsController extends Controller
{
    /**
     * Каталог всех сохранённых крыльев, сгруппированных по производителю.
     *
     * @return string
     */
    public function actionIndex()
    {
        $wings = Wing::find()
            ->with(['manufacturer'])
            ->orderBy([
                'manufacturer_id' => SORT_ASC,
                'model'           => SORT_ASC,
                'model_year'      => SORT_ASC,
                'source_id'       => SORT_ASC,
            ])
            ->all();

        $byManufacturer = [];
        $noManufacturer = [];

        foreach ($wings as $wing) {
            $manufacturerId = $wing->manufacturer_id;

            if ($manufacturerId === null) {
                $noManufacturer[] = $wing;
                continue;
            }

            if (!isset($byManufacturer[$manufacturerId])) {
                $byManufacturer[$manufacturerId] = [
                    'manufacturer' => $wing->manufacturer,
                    'models'       => [],
                ];
            }

            $modelKey = $wing->model . '|' . ($wing->model_year ?? '');

            if (!isset($byManufacturer[$manufacturerId]['models'][$modelKey])) {
                $byManufacturer[$manufacturerId]['models'][$modelKey] = [
                    'model' => $wing->model,
                    'year'  => $wing->model_year,
                    'wings' => [],
                ];
            }

            $byManufacturer[$manufacturerId]['models'][$modelKey]['wings'][] = $wing;
        }

        // Сортируем производителей по имени
        uasort($byManufacturer, function ($a, $b) {
            $na = mb_strtolower($a['manufacturer']->name ?? '');
            $nb = mb_strtolower($b['manufacturer']->name ?? '');
            return $na <=> $nb;
        });

        // Сортируем модели и размеры
        foreach ($byManufacturer as &$m) {
            uasort($m['models'], function ($a, $b) {
                $cmp = mb_strtolower($a['model']) <=> mb_strtolower($b['model']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ($a['year'] ?? 0) <=> ($b['year'] ?? 0);
            });

            foreach ($m['models'] as &$model) {
                usort($model['wings'], function ($a, $b) {
                    return $a->source_id <=> $b->source_id;
                });
            }
            unset($model);
        }
        unset($m);

        // «Без производителя» — сортируем по модели и source_id
        usort($noManufacturer, function ($a, $b) {
            $cmp = mb_strtolower($a->model) <=> mb_strtolower($b->model);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a->source_id <=> $b->source_id;
        });

        $diffStats = $this->getDiffStats();

        return $this->render('index', [
            'byManufacturer' => $byManufacturer,
            'noManufacturer' => $noManufacturer,
            'diffStats'      => $diffStats,
        ]);
    }

    /**
     * Просмотр крыла: схема строп.
     *
     * @param int|null $id
     * @param int|null $source_id
     * @return string
     */
    public function actionView($id = null, $source_id = null)
    {
        if ($id === null && $source_id === null) {
            throw new NotFoundHttpException('Не указан идентификатор крыла');
        }

        $query = Wing::find()->with(['manufacturer']);

        if ($id !== null) {
            $wing = $query->where(['id' => (int)$id])->one();
        } else {
            $wing = $query->where(['source_id' => (int)$source_id])->one();
        }

        if (!$wing) {
            throw new NotFoundHttpException('Крыло не найдено');
        }

        $lowerLines = WingLowerLine::find()
            ->where(['wing_id' => $wing->id])
            ->orderBy(['group_index' => SORT_ASC])
            ->all();

        $upperByLower = [];
        if (!empty($lowerLines)) {
            $upperLines = WingUpperLine::find()
                ->where(['wing_id' => $wing->id])
                ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])
                ->all();

            foreach ($upperLines as $line) {
                $upperByLower[(int)$line->lower_line_id][] = $line;
            }
        }

        $sections = $this->buildSections($lowerLines, $upperByLower);
        $csvTable = $this->buildCsvTable($lowerLines, $upperByLower);

        return $this->render('view', [
            'wing'     => $wing,
            'sections' => $sections,
            'csvTable' => $csvTable,
        ]);
    }

    /**
     * Строит структуру секций для рендера.
     *
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @return array
     */
    protected function buildSections(array $lowerLines, array $upperByLower)
    {
        $powerGroups = [];
        $brakeGroups = [];

        foreach ($lowerLines as $lower) {
            $isBrake = ($lower->length_mm === null && $lower->riser_note === null);
            $lines = $upperByLower[(int)$lower->id] ?? [];

            if ($isBrake) {
                $brakeGroups[] = [
                    'lower'   => $lower,
                    'section' => null,
                    'lines'   => $lines,
                ];
            } else {
                $powerGroups[] = [
                    'lower'   => $lower,
                    'section' => $this->extractSectionNumber($lower->name),
                    'lines'   => $lines,
                ];
            }
        }

        $bySection = [];
        $ears = [];
        foreach ($powerGroups as $g) {
            if ($g['section'] === null) {
                $ears[] = $g;
            } else {
                $bySection[$g['section']][] = $g;
            }
        }

        ksort($bySection);

        $sections = [];

        foreach ($bySection as $number => $groups) {
            $sections[] = $this->makeSection($number, false, $groups);
        }

        if (!empty($ears)) {
            $sections[] = $this->makeSection(null, false, $ears);
        }

        $hasBrake = false;
        if (!empty($brakeGroups)) {
            $hasBrake = true;
            $sections[] = $this->makeSection(null, true, $brakeGroups);
        }

        return [
            'sections'  => $sections,
            'has_brake' => $hasBrake,
        ];
    }

    /**
     * Извлекает номер секции из имени группы.
     *
     * @param string $name
     * @return int|null
     */
    protected function extractSectionNumber($name)
    {
        if (preg_match('~(\d+)\s*$~', (string)$name, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Собирает секцию: группирует точки каждой группы по префиксу имени,
     * каждая строка заполняется целиком.
     *
     * @param int|null $number
     * @param bool     $isBrake
     * @param array    $groups
     * @return array
     */
    protected function makeSection($number, $isBrake, array $groups)
    {
        $gapPx = 2;

        $outGroups = [];

        foreach ($groups as $g) {
            /** @var WingLowerLine $lower */
            $lower = $g['lower'];
            /** @var WingUpperLine[] $lines */
            $lines = $g['lines'];

            $rowsByPrefix = [];
            $prefixOrder  = [];

            foreach ($lines as $line) {
                $prefix = $this->extractPrefix($line->name);

                if (!isset($rowsByPrefix[$prefix])) {
                    $rowsByPrefix[$prefix] = [];
                    $prefixOrder[] = $prefix;
                }

                $rowsByPrefix[$prefix][] = [
                    'name'   => $line->name,
                    'length' => $line->length_mm,
                ];
            }

            $rows = [];
            foreach ($prefixOrder as $prefix) {
                $points = $rowsByPrefix[$prefix];
                $count  = count($points);

                if ($count > 1) {
                    $flexBasis = 'calc((100% - ' . (($count - 1) * $gapPx) . 'px) / ' . $count . ')';
                } else {
                    $flexBasis = '100%';
                }

                $rows[] = [
                    'prefix'     => $prefix,
                    'flex_basis' => $flexBasis,
                    'points'     => $points,
                ];
            }

            $outGroups[] = [
                'name'         => $lower->name,
                'riser_length' => $lower->length_mm,
                'riser_note'   => $lower->riser_note,
                'rows'         => $rows,
            ];
        }

        return [
            'number'   => $number,
            'is_brake' => $isBrake,
            'groups'   => $outGroups,
        ];
    }

    /**
     * Извлекает префикс из имени стропы.
     *
     * @param string $name
     * @return string
     */
    protected function extractPrefix($name)
    {
        if (preg_match('~^([^\d]+)~u', (string)$name, $m)) {
            return $m[1];
        }
        return (string)$name;
    }

    /**
     * Строит структуру для CSV-блока внизу страницы.
     *
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @return array
     */
    protected function buildCsvTable(array $lowerLines, array $upperByLower)
    {
        $items = [];
        foreach ($lowerLines as $lower) {
            $lines = $upperByLower[(int)$lower->id] ?? [];
            foreach ($lines as $line) {
                $items[] = [
                    'prefix'     => mb_strtoupper($this->extractPrefix($line->name)),
                    'length'     => $line->length_mm,
                    'group_name' => (string)$lower->name,
                ];
            }
        }

        $prefixes = [];
        foreach ($items as $item) {
            if (!in_array($item['prefix'], $prefixes, true)) {
                $prefixes[] = $item['prefix'];
            }
        }

        $columns = [];
        foreach ($prefixes as $prefix) {
            $columns[$prefix] = [];
            $groupNumbers = [];
            $nextNumber = 1;

            foreach ($items as $item) {
                if ($item['prefix'] !== $prefix) {
                    continue;
                }

                $groupName = $item['group_name'];
                if (!isset($groupNumbers[$groupName])) {
                    $groupNumbers[$groupName] = $nextNumber++;
                }

                $length = $item['length'] !== null
                    ? (string)(int)round($item['length'])
                    : '—';

                $columns[$prefix][] = $length . '/' . $groupNumbers[$groupName];
            }
        }

        $maxRows = 0;
        foreach ($columns as $rows) {
            if (count($rows) > $maxRows) {
                $maxRows = count($rows);
            }
        }

        return [
            'prefixes' => $prefixes,
            'columns'  => $columns,
            'max_rows' => $maxRows,
        ];
    }

    /**
     * Статистика разброса длин строп по группам.
     *
     * diff = MAX(length_mm) − MIN(length_mm) по wing_upper_lines для крыла.
     *
     * @return array<int, array{
     *     label: string,
     *     min: int|null,
     *     max: int|null,
     *     count: int,
     * }>
     */
    public function getDiffStats()
    {
        $sql = <<<SQL
            SELECT
                CASE
                    WHEN diff < 1000              THEN 'до 1 м'
                    WHEN diff < 1500              THEN '1–1.5 м'
                    WHEN diff < 2000              THEN '1.5–2 м'
                    WHEN diff < 2500              THEN '2–2.5 м'
                    WHEN diff < 3000              THEN '2.5–3 м'
                    ELSE '3 м и более'
                END AS diff_group,
                COUNT(*)  AS wings_count,
                MIN(diff) AS min_diff,
                MAX(diff) AS max_diff
            FROM (
                SELECT
                    w.id,
                    (MAX(u.length_mm) - MIN(u.length_mm)) AS diff
                FROM wings w
                INNER JOIN wing_upper_lines u ON u.wing_id = w.id
                GROUP BY w.id
            ) AS d
            GROUP BY diff_group
            ORDER BY MIN(diff)
        SQL;

        $rows = Yii::$app->db->createCommand($sql)->queryAll();

        // Фиксированный порядок групп, чтобы в таблице всегда были все строки
        // (даже если в какой-то группе 0 крыльев)
        $order = [
            'до 1 м'       => null,
            '1–1.5 м'      => null,
            '1.5–2 м'      => null,
            '2–2.5 м'      => null,
            '2.5–3 м'      => null,
            '3 м и более'  => null,
        ];

        $byLabel = [];
        foreach ($rows as $row) {
            $byLabel[$row['diff_group']] = $row;
        }

        $result = [];
        foreach ($order as $label => $_) {
            $row = $byLabel[$label] ?? null;

            $result[] = [
                'label' => $label,
                'min'   => $row['min_diff'] ?? null,
                'max'   => $row['max_diff'] ?? null,
                'count' => $row ? (int)$row['wings_count'] : 0,
            ];
        }

        return $result;
    }
}