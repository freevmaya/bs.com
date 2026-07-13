/**
 * Bottom Navigation - активация пунктов меню
 */

(function($) {
    'use strict';

    /**
     * Инициализация нижнего меню
     */
    function initBottomNav() {
        var currentPath = window.location.pathname;
        
        // Находим все пункты меню
        $('.bottom-nav .nav-item').each(function() {
            var $item = $(this);
            var $link = $item.find('.nav-link');
            var href = $link.attr('href');
            
            // Проверяем, активен ли пункт
            if (href && currentPath.indexOf(href) !== -1) {
                // Проверяем, не является ли это профилем (особый случай)
                if (href.indexOf('/user/profile') !== -1) {
                    // Для профиля проверяем точное совпадение
                    if (currentPath === href || currentPath === href + '/') {
                        $item.addClass('active');
                        $link.addClass('active');
                    }
                } else {
                    $item.addClass('active');
                    $link.addClass('active');
                }
            }
        });
        
        // Добавляем data-атрибут темы для всех кнопок в нижнем меню
        $('.bottom-nav .nav-link, .bottom-nav .btn-link').each(function() {
            var theme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            $(this).attr('data-bs-theme', theme);
        });
    }

    // Инициализация при загрузке страницы
    $(document).ready(function() {
        initBottomNav();
    });

    // Следим за сменой темы
    $(document).on('themeChanged', function() {
        var theme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
        $('.bottom-nav .nav-link, .bottom-nav .btn-link').attr('data-bs-theme', theme);
    });

})(jQuery);