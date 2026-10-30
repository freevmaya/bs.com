// FILE: .\public_html\js\messages-badge.js

/**
 * Messages Badge — счётчик непрочитанных сообщений в нижнем меню.
 *
 * Подключается в layouts/main.php на всех страницах.
 * Обновляет #messages-unread-badge:
 *   - при загрузке страницы;
 *   - каждые 30 секунд.
 *
 * Пункт «Сообщения» (#messages-nav-item) всегда виден,
 * скрывается/показывается только сам бейдж.
 */

(function($) {
    'use strict';

    var MessagesBadge = {
        refreshInterval: 30000,
        timer: null,

        init: function() {
            if (!$('#messages-unread-badge').length) {
                return;
            }

            this.update();

            var self = this;
            this.timer = setInterval(function() {
                self.update();
            }, this.refreshInterval);
        },

        update: function() {
            $.ajax({
                url: '/messages/get-unread-count',
                type: 'POST',
                dataType: 'json',
                data: {
                    _csrf: $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    if (!response || typeof response.count === 'undefined') {
                        return;
                    }
                    MessagesBadge.apply(response.count);
                }
            });
        },

        apply: function(count) {
            count = parseInt(count, 10) || 0;

            var $badge = $('#messages-unread-badge');
            if (!$badge.length) {
                return;
            }

            if (count > 0) {
                $badge.text(count > 99 ? '99+' : count).show();
            } else {
                $badge.text('').hide();
            }
        }
    };

    $(document).ready(function() {
        MessagesBadge.init();
    });

    window.MessagesBadge = MessagesBadge;

})(jQuery);