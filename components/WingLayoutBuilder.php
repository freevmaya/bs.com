<?php
// FILE: components/WingLayoutBuilder.php

namespace app\components;

use Yii;
use yii\base\Component;
use app\models\WingLowerLine;
use app\models\WingUpperLine;

/**
 * Строит структуру разметки стропной системы (секции, группы, строки, точки)
 * по типу написания схемы (layout_method производителя).
 *
 * Методы:
 *  - buildDefault() — универсальная логика (арабские цифры в конце имени
 *    группы → номер секции; группировка точек по префиксу; stabilo последними).
 *  - buildRoman()   — то же, но с поддержкой римских цифр (AI, BII, AIII,
 *    AIII/AM6): часть до '/' берётся как основа, из неё извлекается римская
 *    цифра → номер секции.
 *  - buildPlain()   — то же, что buildDefault() (схема A1/B1/C1/S/F).
 *
 * Выбор метода — в build() по $layoutMethod.
 */
class WingLayoutBuilder extends Component
{
    /**
     * @var int Отступ между точками в строке (должен совпадать с CSS gap)
     */
    public $gapPx = 2;

    /**
     * Точка входа.
     *
     * @param string|null $layoutMethod Значение manufacturers.layout_method
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @return array{
     *     sections: array,
     *     has_brake: bool,
     * }
     */
    public function build($layoutMethod, array $lowerLines, array $upperByLower)
    {
        $method = $this->resolveMethod($layoutMethod);

        return $this->$method($lowerLines, $upperByLower);
    }

    /**
     * Возвращает имя метода по значению layout_method.
     *
     * @param string|null $layoutMethod
     * @return string
     */
    protected function resolveMethod($layoutMethod)
    {
        if ($layoutMethod === null || trim((string)$layoutMethod) === '') {
            return 'buildDefault';
        }

        $map = [
            'default' => 'buildDefault',
            'roman'   => 'buildRoman',
            'plain'   => 'buildPlain',
        ];

        $key = mb_strtolower(trim((string)$layoutMethod));

        if (isset($map[$key])) {
            return $map[$key];
        }

        Yii::warning(
            "WingLayoutBuilder: unknown layout_method '{$layoutMethod}', fallback to buildDefault",
            'wing_layout'
        );

        return 'buildDefault';
    }

    /**
     * Универсальная раскладка.
     *
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @return array
     */
    public function buildDefault(array $lowerLines, array $upperByLower)
    {
        return $this->buildGeneric($lowerLines, $upperByLower, 'arabic');
    }

    /**
     * Раскладка для схем с римскими цифрами (AI, BII, AIII, AIII/AM6).
     *
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @return array
     */
    public function buildRoman(array $lowerLines, array $upperByLower)
    {
        return $this->buildGeneric($lowerLines, $upperByLower, 'roman');
    }

    /**
     * Раскладка для схем вида A1/B1/C1/S/F.
     *
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @return array
     */
    public function buildPlain(array $lowerLines, array $upperByLower)
    {
        return $this->buildGeneric($lowerLines, $upperByLower, 'arabic');
    }

    /**
     * Общая логика раскладки.
     *
     * @param WingLowerLine[] $lowerLines
     * @param array<int, WingUpperLine[]> $upperByLower
     * @param string $numbering 'arabic' | 'roman'
     * @return array
     */
    protected function buildGeneric(array $lowerLines, array $upperByLower, $numbering)
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
                    'section' => $this->extractSectionNumber($lower->name, $numbering),
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
     * @param string $numbering 'arabic' | 'roman'
     * @return int|null
     */
    protected function extractSectionNumber($name, $numbering = 'arabic')
    {
        $name = trim((string)$name);

        // Часть до '/' — основа: "AIII/AM6" → "AIII"
        if (($pos = mb_strpos($name, '/')) !== false) {
            $name = mb_substr($name, 0, $pos);
        }
        $name = trim($name);

        // Арабская цифра в конце: "AR1", "CR4"
        if (preg_match('~(\d+)\s*$~', $name, $m)) {
            return (int)$m[1];
        }

        // Римская цифра в конце (для roman и как fallback для остальных)
        if (preg_match('~([IVXLC]+)\s*$~i', $name, $m)) {
            $roman = mb_strtoupper($m[1]);
            $number = $this->romanToInt($roman);
            if ($number > 0) {
                return $number;
            }
        }

        return null;
    }

    /**
     * Преобразует римское число в арабское.
     *
     * @param string $roman
     * @return int
     */
    protected function romanToInt($roman)
    {
        $roman = mb_strtoupper((string)$roman);
        if ($roman === '' || !preg_match('~^[IVXLC]+$~', $roman)) {
            return 0;
        }

        $map = [
            'I' => 1,
            'V' => 5,
            'X' => 10,
            'L' => 50,
            'C' => 100,
        ];

        $result = 0;
        $len = strlen($roman);

        for ($i = 0; $i < $len; $i++) {
            $current = $map[$roman[$i]] ?? 0;
            $next    = ($i + 1 < $len) ? ($map[$roman[$i + 1]] ?? 0) : 0;

            if ($current < $next) {
                $result -= $current;
            } else {
                $result += $current;
            }
        }

        return $result > 0 ? $result : 0;
    }

    /**
     * Собирает секцию: группирует точки каждой группы по префиксу имени,
     * stabilo — последними, каждая строка заполняется целиком.
     *
     * @param int|null $number
     * @param bool     $isBrake
     * @param array    $groups
     * @return array
     */
    protected function makeSection($number, $isBrake, array $groups)
    {
        $gapPx = (int)$this->gapPx;

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

                // Stabilo — последними, остальные в исходном порядке
                usort($points, function ($a, $b) {
                    $aStab = $this->isStabilo($a['name']);
                    $bStab = $this->isStabilo($b['name']);
                    if ($aStab !== $bStab) {
                        return $aStab ? 1 : -1;
                    }
                    return 0;
                });

                $count = count($points);

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
     * Является ли стропа "галстучной" (stabilo).
     *
     * @param string $name
     * @return bool
     */
    public function isStabilo($name)
    {
        $name = trim((string)$name);
        if ($name === '') {
            return false;
        }
        return (bool)preg_match('~^(stabilo|stb|st\d*)$~iu', $name);
    }
}