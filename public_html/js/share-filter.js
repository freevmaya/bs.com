/**
 * Share Filter - кнопка "Поделиться" в блоке активных фильтров
 *
 * Логика:
 *   1. Формирует короткую ссылку через AJAX (POST /advertisements/shorten-url).
 *   2. Если доступен navigator.share — открывает системное меню.
 *   3. Иначе — копирует короткую ссылку в буфер обмена.
 *   4. При ошибке AJAX — fallback на полный URL.
 */

(function($) {
    'use strict';

    var ShareFilter = {
        init: function() {
            $(document).on('click', '.search-share-btn', function(e) {
                e.preventDefault();
                ShareFilter.handleClick($(this));
            });
        },

        /**
         * Обработка клика на "Поделиться"
         */
        handleClick: function($btn) {
            var $originalContent = $btn.html();
            $btn.prop('disabled', true).html(
                '<span class="glyphicon glyphicon-refresh glyphicon-spin"></span>'
            );

            var currentUrl = window.location.href;
            var csrfToken = $('meta[name="csrf-token"]').attr('content') || '';
            var shortenUrl = '/advertisements/shorten-url';

            $.ajax({
                url: shortenUrl,
                type: 'POST',
                data: {
                    url: currentUrl,
                    _csrf: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    $btn.prop('disabled', false).html($originalContent);

                    if (response.success && response.short_url) {
                        ShareFilter.share(response.short_url);
                    } else {
                        // Fallback: если сервер вернул ошибку — шарим полный URL
                        console.warn('Short URL error:', response.error);
                        ShareFilter.share(currentUrl);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).html($originalContent);
                    // Fallback: если AJAX упал — шарим полный URL
                    ShareFilter.share(currentUrl);
                }
            });
        },

        /**
         * Поделиться ссылкой
         * На мобильных — через navigator.share, на десктопе — копирование
         */
        share: function(url) {
            // Проверяем поддержку Web Share API
            if (navigator.share && ShareFilter.isMobile()) {
                navigator.share({
                    url: url
                }).catch(function(err) {
                    // Если пользователь отменил или произошла ошибка — просто копируем
                    if (err && err.name !== 'AbortError') {
                        ShareFilter.copyToClipboard(url);
                    }
                });
            } else {
                ShareFilter.copyToClipboard(url);
            }
        },

        /**
         * Копировать ссылку в буфер обмена
         */
        copyToClipboard: function(url) {
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(url).then(function() {
                    ShareFilter.showSuccess();
                }).catch(function() {
                    ShareFilter.fallbackCopy(url);
                });
            } else {
                ShareFilter.fallbackCopy(url);
            }
        },

        /**
         * Fallback-копирование через временный textarea
         */
        fallbackCopy: function(url) {
            var $textarea = $('<textarea>')
                .val(url)
                .css({
                    position: 'fixed',
                    top: '-1000px',
                    left: '-1000px',
                    opacity: 0
                })
                .appendTo('body');

            $textarea[0].select();
            $textarea[0].setSelectionRange(0, url.length);

            try {
                var success = document.execCommand('copy');
                if (success) {
                    ShareFilter.showSuccess();
                } else {
                    ShareFilter.showError();
                }
            } catch (err) {
                ShareFilter.showError();
            }

            $textarea.remove();
        },

        /**
         * Показать сообщение об успешном копировании
         */
        showSuccess: function() {
            if (typeof window.showNotification === 'function') {
                window.showNotification('Короткая ссылка скопирована', 'success');
            } else {
                alert('Ссылка скопирована');
            }
        },

        /**
         * Показать сообщение об ошибке
         */
        showError: function() {
            if (typeof window.showNotification === 'function') {
                window.showNotification('Не удалось скопировать ссылку', 'danger');
            } else {
                alert('Не удалось скопировать ссылку');
            }
        },

        /**
         * Проверка на мобильное устройство
         */
        isMobile: function() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        }
    };

    $(document).ready(function() {
        ShareFilter.init();
    });

    window.ShareFilter = ShareFilter;

})(jQuery);