/**
 * Notification System - управление уведомлениями
 */

(function($) {
    'use strict';

    /**
     * Показать уведомление
     * @param {string} message - Текст сообщения
     * @param {string} type - Тип: success, danger, warning, info
     * @param {number} duration - Длительность показа (мс)
     */
    window.showNotification = function(message, type, duration) {
        var container = $('#notification-container');
        if (!container.length) {
            container = $('<div id="notification-container"></div>');
            $('body').append(container);
        }

        type = type || 'info';
        duration = duration || 10000;

        var $notification = $('<div>', {
            class: 'notification notification-' + type + ' show'
        });

        var $content = $('<div>', {
            class: 'notification-content'
        });

        var $message = $('<div>', {
            class: 'notification-message',
            text: message
        });

        var $closeBtn = $('<button>', {
            class: 'notification-close',
            html: '&times;'
        });

        var $progress = $('<div>', {
            class: 'notification-progress'
        });

        $content.append($message);
        $content.append($closeBtn);
        $notification.append($content);
        $notification.append($progress);
        container.append($notification);

        var timeout = setTimeout(function() {
            closeNotification($notification);
        }, duration);

        $closeBtn.on('click', function() {
            closeNotification($notification);
            clearTimeout(timeout);
        });

        $notification.on('mouseenter', function() {
            clearTimeout(timeout);
            $progress.css('animation-play-state', 'paused');
        });

        $notification.on('mouseleave', function() {
            $progress.css('animation-play-state', 'running');
            timeout = setTimeout(function() {
                closeNotification($notification);
            }, 3000);
        });
    };

    /**
     * Закрыть уведомление
     */
    function closeNotification($notification) {
        $notification.removeClass('show');
        setTimeout(function() {
            $notification.remove();
        }, 100);
    }

    /**
     * Инициализация существующих уведомлений
     */
    $(document).ready(function() {
        $('.notification').each(function() {
            var $notification = $(this);
            var $closeBtn = $notification.find('.notification-close');
            var $progress = $notification.find('.notification-progress');

            $closeBtn.on('click', function() {
                closeNotification($notification);
            });

            var timeout = setTimeout(function() {
                closeNotification($notification);
            }, 10000);

            $notification.on('mouseenter', function() {
                clearTimeout(timeout);
                $progress.css('animation-play-state', 'paused');
            });

            $notification.on('mouseleave', function() {
                $progress.css('animation-play-state', 'running');
                timeout = setTimeout(function() {
                    closeNotification($notification);
                }, 3000);
            });
        });
    });

})(jQuery);