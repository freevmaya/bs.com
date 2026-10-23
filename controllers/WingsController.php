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
     * Просмотр крыла: схема строп.
     *
     * @param int|null $id внутренний id (wings.id)
     * @param int|null $source_id id источника (если передан, ищем по нему)
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

        // Нижние стропы (группы) по порядку
        $lowerLines = WingLowerLine::find()
            ->where(['wing_id' => $wing->id])
            ->orderBy(['group_index' => SORT_ASC])
            ->all();

        // Верхние стропы, сгруппированные по lower_line_id, по sort_order
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

        // Раскладываем группы по секциям
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
     * Правила:
     *  - .brake — группы без riser length (length_mm IS NULL и riser_note IS NULL);
     *  - силовые группы: если в имени в конце есть цифра — секция = эта цифра;
     *    если цифры нет — секция «уши» (null), визуально последняя;
     *  - внутри секции N_max = максимум верхних строп среди групп;
     *    каждая группа дополняется пустыми .point.empty до N_max;
     *    flex_basis = 100% / N_max.
     *
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @return array{
     *     sections: array<int, array{
     *         number: int|null,
     *         is_brake: bool,
     *         flex_basis: string,
     *         groups: array<int, array{
     *             name: string,
     *             riser_length: float|null,
     *             riser_note: string|null,
     *             points: array<int, array{
     *                 name: string|null,
     *                 length: float|null,
     *                 is_empty: bool,
     *             }>,
     *         }>,
     *     }>,
     *     has_brake: bool,
     * }
     */
    protected function buildSections(array $lowerLines, array $upperByLower)
    {
        // Разделяем на brake / power
        $powerGroups = [];   // list of ['lower' => WingLowerLine, 'section' => int|null, 'lines' => WingUpperLine[]]
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

        // Группируем силовые по номеру секции
        $bySection = []; // int => list
        $ears = [];      // list (section === null)
        foreach ($powerGroups as $g) {
            if ($g['section'] === null) {
                $ears[] = $g;
            } else {
                $bySection[$g['section']][] = $g;
            }
        }

        ksort($bySection);

        // Собираем секции в нужном порядке
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
     * Строит структуру для CSV-блока внизу страницы:
     * колонки — по префиксу верхних строп (A, B, C, …),
     * строки — стропы этого префикса в порядке появления,
     * значение — "<длина>/<номер_группы_в_колонке>".
     *
     * Номер группы — не сквозной, а порядковый внутри колонки:
     * для префикса A: AR1 → 1, AR2 → 2, AR3 → 3, CR4 → 4.
     *
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @return array{
     *     prefixes: string[],
     *     columns: array<string, array<int, string>>,
     *     max_rows: int,
     * }
     */
    protected function buildCsvTable(array $lowerLines, array $upperByLower)
    {
        // 1. Собираем все точки с их префиксом, длиной и именем группы
        //    в порядке появления (по group_index, затем по sort_order).
        $items = []; // list of ['prefix' => 'A', 'length' => 5905.0, 'group_name' => 'AR1']
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

        // 2. Порядок колонок — по первому появлению префикса
        $prefixes = [];
        foreach ($items as $item) {
            if (!in_array($item['prefix'], $prefixes, true)) {
                $prefixes[] = $item['prefix'];
            }
        }

        // 3. Для каждой колонки — массив строк.
        //    Номер группы внутри колонки — порядковый номер появления
        //    имени группы среди точек этого префикса.
        $columns = [];
        foreach ($prefixes as $prefix) {
            $columns[$prefix] = [];

            $groupNumbers = []; // group_name => number внутри этой колонки
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
     * Извлекает номер секции из имени группы.
     * "AR1" → 1, "CR4" → 4, "S" → null, "F" → null.
     */
    protected function extractSectionNumber($name)
    {
        if (preg_match('~(\d+)\s*$~', (string)$name, $m)) {
            return (int)$m[1];
        }
        return null;
    }

    /**
     * Собирает секцию: группирует точки каждой группы по префиксу имени
     * (C1 → C, DG1 → DG, SA → SA), каждая строка заполняется целиком.
     *
     * @param int|null $number
     * @param bool     $isBrake
     * @param array    $groups  list of ['lower' => WingLowerLine, 'lines' => WingUpperLine[]]
     * @return array
     */
    protected function makeSection($number, $isBrake, array $groups)
    {
        $gapPx = 2; // должно совпадать с .row { gap: 2px }

        $outGroups = [];

        foreach ($groups as $g) {
            /** @var WingLowerLine $lower */
            $lower = $g['lower'];
            /** @var WingUpperLine[] $lines */
            $lines = $g['lines'];

            // Группируем точки по префиксу, сохраняя порядок появления
            $rowsByPrefix = []; // prefix => list of points
            $prefixOrder  = []; // порядок появления префиксов

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

            // Собираем строки с flex_basis от количества точек в строке
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
            'number'     => $number,
            'is_brake'   => $isBrake,
            'groups'     => $outGroups,
        ];
    }

    /**
     * Извлекает префикс из имени стропы.
     *
     * "C1"  → "C"
     * "DG1" → "DG"
     * "A10" → "A"
     * "SA"  → "SA"
     * "a1"  → "a"
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
     * Каталог всех сохранённых крыльев, сгруппированных по производителю.
     *
     * @return string
     */
    public function actionIndex()
    {
        // Все крылья с производителями, отсортированные по производителю, модели, source_id
        $wings = Wing::find()
            ->with(['manufacturer'])
            ->orderBy([
                'manufacturer_id' => SORT_ASC,
                'model'           => SORT_ASC,
                'model_year'      => SORT_ASC,
                'source_id'       => SORT_ASC,
            ])
            ->all();

        // Группируем: manufacturer_id => model_key => list of wings
        // model_key = model + '|' + year (чтобы Eazy и Eazy 2 не склеивались)
        $byManufacturer = []; // manufacturer_id => ['manufacturer' => Manufacturer|null, 'models' => [...]]
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
                    'model'  => $wing->model,
                    'year'   => $wing->model_year,
                    'wings'  => [],
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

        // Внутри каждого производителя сортируем модели по model + year
        foreach ($byManufacturer as &$m) {
            uasort($m['models'], function ($a, $b) {
                $cmp = mb_strtolower($a['model']) <=> mb_strtolower($b['model']);
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ($a['year'] ?? 0) <=> ($b['year'] ?? 0);
            });
            // и размеры внутри модели — по source_id
            foreach ($m['models'] as &$model) {
                usort($model['wings'], function ($a, $b) {
                    return $a->source_id <=> $b->source_id;
                });
            }
            unset($model);
        }
        unset($m);

        // «Без производителя» — тоже сортируем
        usort($noManufacturer, function ($a, $b) {
            $cmp = mb_strtolower($a->model) <=> mb_strtolower($b->model);
            if ($cmp !== 0) {
                return $cmp;
            }
            return $a->source_id <=> $b->source_id;
        });

        return $this->render('index', [
            'byManufacturer' => $byManufacturer,
            'noManufacturer' => $noManufacturer,
        ]);
    }
}