<?php
// FILE: .\helpers\TextHelper.php

namespace app\helpers;

use yii\helpers\Html;

/**
 * Хелпер для работы с текстом
 */
class TextHelper
{
    /**
     * Преобразовать URL-адреса в тексте в кликабельные ссылки
     *
     * Порядок работы:
     *   1. Экранируем весь текст через Html::encode() — защита от XSS.
     *   2. Ищем URL-паттерны (http://, https://, www.).
     *   3. Оборачиваем найденные URL в <a> с rel="nofollow noopener noreferrer" и target="_blank".
     *
     * @param string|null $text Сырой текст (не экранированный)
     * @return string Безопасный HTML с кликабельными ссылками
     */
    public static function linkify($text)
    {
        if ($text === null || $text === '') {
            return '';
        }

        // 1. Экранируем весь текст
        $escaped = Html::encode($text);

        // 2. Регулярка для URL:
        //    - http:// или https:// или www.
        //    - далее любые символы, кроме пробелов, угловых скобок и кавычек
        $pattern = '~\b(?:(?:https?://)|(?:www\.))[^\s<>"\']+~iu';

        // 3. Замена: каждое совпадение оборачиваем в <a>
        $result = preg_replace_callback($pattern, function ($matches) {
            $url = $matches[0];

            // Обрезаем конечные знаки препинания, которые не являются частью URL
            $url = self::trimTrailingPunctuation($url);

            // Определяем href: если URL начинается с www., добавляем http://
            $href = $url;
            if (stripos($url, 'www.') === 0) {
                $href = 'http://' . $url;
            }

            // Формируем тег <a>
            return Html::a(
                Html::encode($url),  // текст ссылки (URL уже экранирован)
                Html::encode($href), // href (тоже экранируем на случай спецсимволов)
                [
                    'rel' => 'nofollow noopener noreferrer',
                    'target' => '_blank',
                ]
            );
        }, $escaped);

        return $result;
    }

    /**
     * Обрезает конечные знаки препинания, которые не являются частью URL
     *
     * Например, "https://example.com." → "https://example.com"
     *
     * @param string $url
     * @return string
     */
    private static function trimTrailingPunctuation($url)
    {
        // Обрезаем точки, запятые, точки с запятой, восклицательные/вопросительные знаки
        // в конце URL. Скобки закрывающие — обрезаем только если открывающих меньше.
        $url = rtrim($url, '.,;:!?');

        // Баланс скобок: если открывающих '(' больше, чем закрывающих ')',
        // убираем конечные ')'
        while (substr($url, -1) === ')') {
            $openCount = substr_count($url, '(');
            $closeCount = substr_count($url, ')');
            if ($openCount >= $closeCount) {
                break;
            }
            $url = substr($url, 0, -1);
        }

        return $url;
    }
}