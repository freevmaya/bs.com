<?php
// FILE: controllers/WingsController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;
use app\models\Wing;
use app\models\WingLowerLine;
use app\models\WingUpperLine;
use app\components\WingLayoutBuilder;

/**
 * Публичный просмотр сохранённых крыльев.
 */
class WingsController extends Controller
{
    /**
     * @return bool
     */
    protected function isAdmin()
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        $identity = Yii::$app->user->identity;
        return $identity && method_exists($identity, 'isAdmin') && $identity->isAdmin();
    }

    /**
     * Каталог всех сохранённых крыльев, сгруппированных по производителю.
     *
     * Для админа — все состояния с пометкой; для остальных — только active.
     *
     * @return string
     */
    public function actionIndex()
    {
        $isAdmin = $this->isAdmin();

        $query = Wing::find()->with(['manufacturer']);

        if (!$isAdmin) {
            $query->andWhere(['state' => Wing::STATE_ACTIVE]);
        }

        $wings = $query
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

        uasort($byManufacturer, function ($a, $b) {
            $na = mb_strtolower($a['manufacturer']->name ?? '');
            $nb = mb_strtolower($b['manufacturer']->name ?? '');
            return $na <=> $nb;
        });

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
            'diffStats'      => $this->getDiffStats(),
            'isAdmin'        => $isAdmin,
        ]);
    }

    /**
     * AJAX: смена состояния крыла. Только для админа.
     *
     * @return array
     */
    public function actionSetState()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!$this->isAdmin()) {
            throw new ForbiddenHttpException('Доступ запрещён');
        }

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'error' => 'Только POST'];
        }

        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }

        $id = (int)Yii::$app->request->post('id', 0);
        $state = (string)Yii::$app->request->post('state', '');

        if ($id <= 0) {
            return ['success' => false, 'error' => 'Не указан id'];
        }

        if (!in_array($state, array_keys(Wing::getStateList()), true)) {
            return ['success' => false, 'error' => 'Некорректное состояние'];
        }

        $wing = Wing::findOne($id);
        if (!$wing) {
            return ['success' => false, 'error' => 'Крыло не найдено'];
        }

        $wing->state = $state;
        if (!$wing->save(false, ['state'])) {
            return ['success' => false, 'error' => 'Ошибка сохранения'];
        }

        return [
            'success'     => true,
            'state'       => $wing->state,
            'state_label' => $wing->getStateLabel(),
            'state_class' => $wing->getStateColorClass(),
        ];
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

        $isAdmin = $this->isAdmin();

        $query = Wing::find()->with(['manufacturer']);

        if ($id !== null) {
            $query->where(['id' => (int)$id]);
        } else {
            $query->where(['source_id' => (int)$source_id]);
        }

        if (!$isAdmin) {
            $query->andWhere(['state' => Wing::STATE_ACTIVE]);
        }

        $wing = $query->one();

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

        // Раскладка стропной системы по типу написания схемы производителя
        /** @var WingLayoutBuilder $layoutBuilder */
        $layoutBuilder = new WingLayoutBuilder();

        $layoutMethod = null;
        if ($wing->manufacturer !== null) {
            $layoutMethod = $wing->manufacturer->layout_method;
        }

        $sections = $layoutBuilder->build($layoutMethod, $lowerLines, $upperByLower);

        $csvTable = $this->buildCsvTable($lowerLines, $upperByLower);

        return $this->render('view', [
            'wing'     => $wing,
            'sections' => $sections,
            'csvTable' => $csvTable,
            'isAdmin'  => $isAdmin,
        ]);
    }

    /**
     * Статистика разброса длин строп по группам (только active).
     *
     * @return array
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
    WHERE w.state = 'active'
    GROUP BY w.id
) AS d
GROUP BY diff_group
ORDER BY MIN(diff)
SQL;

        $rows = Yii::$app->db->createCommand($sql)->queryAll();

        $order = [
            'до 1 м'      => null,
            '1–1.5 м'     => null,
            '1.5–2 м'     => null,
            '2–2.5 м'     => null,
            '2.5–3 м'     => null,
            '3 м и более' => null,
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

    /**
     * Строит структуру для CSV-блока внизу страницы.
     *
     * Колонки — по префиксу верхних строп (A, B, C, …),
     * строки — стропы этого префикса в порядке появления,
     * stabilo — последними в колонке.
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
        $items = [];
        foreach ($lowerLines as $lower) {
            $lines = $upperByLower[(int)$lower->id] ?? [];
            foreach ($lines as $line) {
                $items[] = [
                    'prefix'     => mb_strtoupper($this->extractPrefix($line->name)),
                    'name'       => $line->name,
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

            $columnItems = [];
            foreach ($items as $item) {
                if ($item['prefix'] !== $prefix) {
                    continue;
                }
                $columnItems[] = $item;
            }

            usort($columnItems, function ($a, $b) {
                $aStab = $this->isStabilo($a['name'] ?? '');
                $bStab = $this->isStabilo($b['name'] ?? '');
                if ($aStab !== $bStab) {
                    return $aStab ? 1 : -1;
                }
                return 0;
            });

            foreach ($columnItems as $item) {
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
     * Извлекает префикс из имени стропы (для CSV-блока).
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
     * Является ли стропа stabilo (для CSV-блока).
     *
     * @param string $name
     * @return bool
     */
    protected function isStabilo($name)
    {
        $name = trim((string)$name);
        if ($name === '') {
            return false;
        }
        return (bool)preg_match('~^(stabilo|stabi|stab|stb|st\d*)$~iu', $name);
    }
}