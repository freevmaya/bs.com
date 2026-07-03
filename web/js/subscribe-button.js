// FILE: .\web\js\subscribe-button.js

(function($) {
    'use strict';

    /**
     * Добавление кнопки подписки в форму поиска
     */
    function initSubscribeButton() {
        // Проверяем, есть ли уже кнопка подписки
        if ($('.subscribe-btn-wrapper').length) return;

        // Находим контейнер с кнопками в форме параметров
        var $buttonsContainer = $('#search-params-form .text-end, #search-params-form .col-12.text-end');
        
        if (!$buttonsContainer.length) return;

        // Проверяем, не добавлена ли уже кнопка
        if ($buttonsContainer.find('.subscribe-btn-wrapper').length) return;

        // Создаем кнопку подписки
        var $subscribeBtn = $(
            '<button type="button" class="btn btn-success subscribe-button" id="subscribe-button">' +
                '<span class="glyphicon glyphicon-bell"></span> Подписаться' +
            '</button>'
        );

        // Оборачиваем в контейнер
        var $wrapper = $('<span class="subscribe-btn-wrapper" style="margin-left: 8px;"></span>');
        $wrapper.append($subscribeBtn);

        // Добавляем перед кнопкой "Сбросить" или после "Применить"
        var $resetBtn = $buttonsContainer.find('.btn-outline-secondary');
        if ($resetBtn.length) {
            $resetBtn.before(' ');
            $resetBtn.before($wrapper);
        } else {
            $buttonsContainer.append($wrapper);
        }

        // Добавляем подсказку
        var $helpText = $(
            '<div class="help-block" style="margin-top: 8px; color: #999; font-size: 12px;">' +
                '<span class="glyphicon glyphicon-info-sign"></span> ' +
                'Подпишитесь на эти параметры поиска, чтобы получать уведомления о новых объявлениях' +
            '</div>'
        );
        $buttonsContainer.append($helpText);
    }

    // Инициализация при загрузке страницы
    $(document).ready(function() {
        // Добавляем кнопку подписки
        initSubscribeButton();

        // Также добавляем кнопку при открытии панели параметров
        $(document).on('shown.bs.collapse', '#searchParamsCollapse', function() {
            initSubscribeButton();
        });
    });

})(jQuery);