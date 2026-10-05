<?php
// FILE: .\widgets\views\_hidden_params.php

use yii\helpers\Html;

/**
 * Рекурсивный рендер GET-параметров в hidden-поля
 *
 * @var array $params Ассоциативный массив параметров (обычно Yii::$app->request->get())
 * @var array $exclude Список ключей верхнего уровня, которые не нужно рендерить
 * @var string|null $prefix Внутренний префикс для рекурсии
 */

$exclude = $exclude ?? [];
$prefix = $prefix ?? '';

/**
 * Проверяет, является ли массив списком (числовые ключи 0..N-1)
 *
 * @param array $arr
 * @return bool
 */
$isList = function ($arr) {
    if (empty($arr)) {
        return false;
    }
    return array_keys($arr) === range(0, count($arr) - 1);
};

/**
 * Рекурсивно рендерит hidden-поля
 *
 * @param array $data
 * @param string $prefix
 * @return string
 */
$renderHidden = function ($data, $prefix) use (&$renderHidden, $exclude, $isList) {
    $html = '';

    foreach ($data as $key => $value) {
        // Проверка исключений только на верхнем уровне
        if ($prefix === '' && in_array($key, $exclude, true)) {
            continue;
        }

        // Формируем имя поля
        if ($prefix === '') {
            $name = $key;
        } else {
            $name = $prefix . '[' . $key . ']';
        }

        if (is_array($value)) {
            if ($isList($value)) {
                // Числовой массив — используем name[]
                foreach ($value as $item) {
                    if (is_array($item)) {
                        // Массив массивов — рекурсия с префиксом name[]
                        $html .= $renderHidden($item, $name . '[]');
                    } else {
                        $html .= Html::hiddenInput($name . '[]', $item);
                    }
                }
            } else {
                // Ассоциативный массив — рекурсия
                $html .= $renderHidden($value, $name);
            }
        } else {
            $html .= Html::hiddenInput($name, $value);
        }
    }

    return $html;
};

echo $renderHidden($params, $prefix);