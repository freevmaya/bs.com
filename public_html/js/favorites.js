/* FILE: .\public_html\js\favorites.js */

/**
 * Favorites — управление избранным.
 *
 * Всегда ходит на сервер (и гость, и авторизованный) — единообразие:
 * сервер сам ставит/чистит cookie гостя или пишет в БД.
 *
 * Бейдж в тулбаре (#favorites-count-badge) обновляется:
 *   - при загрузке страницы (initBadge);
 *   - после каждого toggle (по response.count).
 */

(function($) {
    'use strict';

    var Favorites = {
        toggleUrl: '/favorites/toggle',
        countUrl: '/favorites/count',

        init: function() {
            var self = this;

            $(document).on('click', '.favorite-btn, .favorite-btn-inline', function(e) {
                e.preventDefault();
                e.stopPropagation();
                self.handleClick($(this));
            });

            self.initBadge();
        },

        /**
         * Загрузить количество избранного и обновить бейдж в тулбаре.
         */
        initBadge: function() {
            if (!$('#favorites-count-badge').length) {
                return;
            }

            $.ajax({
                url: this.countUrl,
                type: 'GET',
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        Favorites.updateBadge(response.count);
                    }
                }
            });
        },

        /**
         * Обновить бейдж.
         *
         * @param {number} count
         */
        updateBadge: function(count) {
            var $badge = $('#favorites-count-badge');
            if (!$badge.length) {
                return;
            }

            count = parseInt(count, 10) || 0;

            if (count > 0) {
                $badge.text(count > 99 ? '99+' : count).show();
            } else {
                $badge.text('').hide();
            }
        },

        handleClick: function($btn) {
            var self = this;
            var id = $btn.data('id');
            if (!id) return;

            var wasFavorite = $btn.hasClass('is-favorite');

            // Оптимистичное обновление
            $btn.toggleClass('is-favorite');
            $btn.addClass('animating');
            setTimeout(function() {
                $btn.removeClass('animating');
            }, 500);

            var csrfMeta = $('meta[name="csrf-token"]').attr('content');
            var csrfInput = $('input[name="_csrf"]').val();
            var csrf = csrfMeta || csrfInput || '';

            $.ajax({
                url: self.toggleUrl,
                type: 'POST',
                data: {
                    advertisement_id: id,
                    _csrf: csrf,
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (response.is_favorite) {
                            $btn.addClass('is-favorite');
                        } else {
                            $btn.removeClass('is-favorite');
                        }

                        if (typeof response.count !== 'undefined') {
                            self.updateBadge(response.count);
                        }

                        if (typeof window.showNotification === 'function') {
                            window.showNotification(response.message, 'success');
                        }
                    } else {
                        // Откат
                        $btn.toggleClass('is-favorite', wasFavorite);
                        if (typeof window.showNotification === 'function') {
                            window.showNotification(response.error || 'Ошибка', 'danger');
                        }
                    }
                },
                error: function() {
                    // Откат
                    $btn.toggleClass('is-favorite', wasFavorite);
                    if (typeof window.showNotification === 'function') {
                        window.showNotification('Ошибка соединения с сервером', 'danger');
                    }
                },
            });
        },
    };

    $(document).ready(function() {
        Favorites.init();
    });

    window.Favorites = Favorites;

})(jQuery);