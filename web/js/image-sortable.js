/**
 * Image Sortable - сортировка изображений и видео
 * 
 * @requires jQuery UI Sortable
 * @requires jQuery
 */

(function($) {
    'use strict';

    /**
     * Инициализация сортировки для контейнера
     * 
     * @param {string|Element} container - Селектор или DOM элемент контейнера
     * @param {Object} options - Настройки
     * @param {string} options.reorderUrl - URL для сохранения порядка
     * @param {string} options.csrfToken - CSRF токен
     * @param {string} options.handle - Селектор рукоятки (по умолчанию '.sort-handle')
     * @param {string} options.items - Селектор элементов (по умолчанию '.sortable-item')
     * @param {Function} options.onUpdate - Колбэк после обновления
     */
    function initImageSortable(container, options) {
        var defaults = {
            handle: '.sort-handle',
            items: '.sortable-item',
            placeholder: 'sortable-placeholder',
            cursor: 'grabbing',
            tolerance: 'pointer',
            forcePlaceholderSize: true,
            onUpdate: null
        };

        var settings = $.extend({}, defaults, options);
        var $container = $(container);
        
        if (!$container.length) {
            console.warn('ImageSortable: Container not found:', container);
            return null;
        }

        // Проверяем наличие jQuery UI Sortable
        if (typeof $.fn.sortable === 'undefined') {
            console.warn('ImageSortable: jQuery UI Sortable не загружен. Подключите jquery-ui.');
            return null;
        }

        // Уничтожаем предыдущий сортабл
        if ($container.data('ui-sortable')) {
            $container.sortable('destroy');
        }

        $container.sortable({
            handle: settings.handle,
            items: settings.items,
            placeholder: settings.placeholder,
            cursor: settings.cursor,
            tolerance: settings.tolerance,
            forcePlaceholderSize: settings.forcePlaceholderSize,
            start: function(event, ui) {
                ui.item.addClass('sorting');
                ui.item.data('startIndex', ui.item.index());
                
                // Сохраняем высоту плейсхолдера
                var height = ui.item.outerHeight();
                ui.placeholder.height(height);
            },
            update: function(event, ui) {
                ui.item.removeClass('sorting');
                
                // Обновляем номера
                updateOrderNumbers($container);
                
                // Сохраняем порядок
                saveImageOrder($container, settings.reorderUrl, settings.csrfToken);
                
                // Вызываем колбэк
                if (typeof settings.onUpdate === 'function') {
                    settings.onUpdate($container, ui);
                }
            },
            stop: function(event, ui) {
                ui.item.removeClass('sorting');
            }
        });

        // Добавляем класс для стилизации
        $container.addClass('sortable-container-initialized');

        return $container;
    }

    /**
     * Обновление номеров порядка
     * 
     * @param {jQuery} $container - Контейнер с элементами
     */
    function updateOrderNumbers($container) {
        $container.find('.sortable-item').each(function(index) {
            var $item = $(this);
            var $orderNumber = $item.find('.order-number');
            
            if ($orderNumber.length) {
                $orderNumber.text(index + 1);
            }
            
            // Обновляем data-атрибуты
            $item.attr('data-sort-order', index);
        });
    }

    /**
     * Сохранение порядка изображений
     * 
     * @param {jQuery} $container - Контейнер с элементами
     * @param {string} reorderUrl - URL для сохранения
     * @param {string} csrfToken - CSRF токен
     */
    function saveImageOrder($container, reorderUrl, csrfToken) {
        var orders = [];
        
        $container.find('.sortable-item').each(function(index) {
            var $item = $(this);
            var id = $item.data('image-id') || $item.data('image-index');
            
            if (id !== undefined && id !== null) {
                orders.push({
                    id: parseInt(id),
                    position: index
                });
            }
        });

        if (orders.length === 0) {
            showIndicator('Нет элементов для сортировки', 'info');
            return;
        }

        // Показываем индикатор
        showIndicator('Сохранение порядка...', 'loading');

        $.ajax({
            url: reorderUrl,
            type: 'POST',
            data: {
                orders: orders,
                _csrf: csrfToken
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showIndicator('✓ Порядок сохранен', 'success');
                } else {
                    showIndicator('✗ Ошибка: ' + (response.error || 'неизвестная ошибка'), 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('Save order error:', status, error);
                showIndicator('✗ Ошибка сохранения: ' + status, 'error');
            }
        });
    }

    /**
     * Показ индикатора сохранения
     * 
     * @param {string} message - Текст сообщения
     * @param {string} type - Тип: 'loading', 'success', 'error', 'info'
     */
    function showIndicator(message, type) {
        var $indicator = $('#sort-save-indicator');
        
        if (!$indicator.length) {
            $indicator = $('<div id="sort-save-indicator"></div>');
            $('body').append($indicator);
        }
        
        // Сбрасываем классы
        $indicator.removeClass('success error info loading');
        
        // Формируем HTML
        var html = '';
        if (type === 'loading') {
            html = '<span class="spinner"></span> ' + message;
            $indicator.addClass('loading');
        } else if (type === 'success') {
            html = message;
            $indicator.addClass('success');
        } else if (type === 'error') {
            html = message;
            $indicator.addClass('error');
        } else {
            html = message;
            $indicator.addClass('info');
        }
        
        $indicator.html(html);
        $indicator.fadeIn(200);
        
        // Автоматическое скрытие через 2-3 секунды
        clearTimeout($indicator.data('timeout'));
        
        var timeout = 3000;
        if (type === 'loading') {
            timeout = 60000; // Долго для загрузки
        } else if (type === 'success') {
            timeout = 2000;
        } else if (type === 'error') {
            timeout = 3500;
        }
        
        $indicator.data('timeout', setTimeout(function() {
            $indicator.fadeOut(300);
        }, timeout));
    }

    /**
     * Скрытие индикатора
     */
    function hideIndicator() {
        var $indicator = $('#sort-save-indicator');
        if ($indicator.length) {
            clearTimeout($indicator.data('timeout'));
            $indicator.fadeOut(300);
        }
    }

    /**
     * Инициализация всех сортируемых контейнеров на странице
     * 
     * @param {Object} options - Настройки для всех контейнеров
     */
    function initAllImageSortables(options) {
        var defaults = {
            container: '.sortable-container',
            reorderUrl: null,
            csrfToken: null
        };
        
        var settings = $.extend({}, defaults, options);
        
        if (!settings.reorderUrl) {
            console.warn('ImageSortable: reorderUrl не указан');
            return;
        }
        
        if (!settings.csrfToken) {
            console.warn('ImageSortable: csrfToken не указан');
            return;
        }
        
        $(settings.container).each(function() {
            initImageSortable(this, {
                reorderUrl: settings.reorderUrl,
                csrfToken: settings.csrfToken
            });
        });
    }

    // Экспортируем публичные методы
    window.ImageSortable = {
        init: initImageSortable,
        initAll: initAllImageSortables,
        updateNumbers: updateOrderNumbers,
        saveOrder: saveImageOrder,
        showIndicator: showIndicator,
        hideIndicator: hideIndicator
    };

})(jQuery);

/**
 * Автоматическая инициализация при загрузке страницы (если есть data-атрибуты)
 */
$(document).ready(function() {
    // Ищем контейнеры с data-image-sortable
    $('[data-image-sortable]').each(function() {
        var $container = $(this);
        var reorderUrl = $container.data('reorder-url') || $container.attr('data-reorder-url');
        var csrfToken = $container.data('csrf-token') || $container.attr('data-csrf-token');
        var handle = $container.data('handle') || '.sort-handle';
        
        if (reorderUrl && csrfToken) {
            ImageSortable.init($container, {
                reorderUrl: reorderUrl,
                csrfToken: csrfToken,
                handle: handle
            });
        }
    });
});