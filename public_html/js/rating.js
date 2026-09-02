/**
 * Rating - оценка снаряжения через AI
 */

(function($) {
    'use strict';

    var Rating = {
        /**
         * Инициализация
         */
        init: function() {
            this.initRateButtons();
            this.initRefreshButtons();
        },

        /**
         * Инициализация кнопок "Оценить"
         */
        initRateButtons: function() {
            var self = this;
            
            $(document).on('click', '.rate-button:not(.rate-refresh)', function() {
                var button = $(this);
                var id = button.data('id');
                var container = button.closest('#rating-container');
                
                if (!id) return;
                
                // Сохраняем оригинальный текст
                var originalText = button.html();
                
                // Блокируем кнопку
                button.prop('disabled', true).html('<span class="spinner"></span> Оценка...');
                
                // Показываем индикатор загрузки в контейнере
                if (container.length) {
                    var loadingHtml = '<div class="rating-loading">' +
                        '<div class="spinner-large"></div>' +
                        '<p class="text-muted">Анализируем объявление и сравниваем с другими...</p>' +
                        '</div>';
                    container.html(loadingHtml);
                }
                
                // Отправляем запрос
                $.ajax({
                    url: '/advertisements/rate?id=' + id,
                    type: 'POST',
                    data: {
                        _csrf: $('meta[name="csrf-token"]').attr('content') || $('[name="_csrf"]').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            // Обновляем контейнер
                            if (container.length) {
                                container.html(response.html);
                            }
                            
                            // Добавляем кнопку обновления
                            if (container.length) {
                                container.append(
                                    '<button type="button" class="btn btn-outline-secondary btn-sm rate-button rate-refresh" data-id="' + id + '" style="margin-top: 10px;">' +
                                        '<span class="glyphicon glyphicon-refresh"></span> Обновить оценку' +
                                    '</button>'
                                );
                            }
                            
                            self.showNotification(response.message || 'Оценка успешно сгенерирована!', 'success');
                        } else {
                            self.showNotification(response.error || 'Ошибка при генерации оценки', 'danger');
                            // Восстанавливаем кнопку
                            if (container.length) {
                                container.html(
                                    '<p class="text-muted">Не удалось получить оценку. Попробуйте позже.</p>' +
                                    '<button type="button" class="btn btn-primary rate-button" data-id="' + id + '">' +
                                        '<span class="glyphicon glyphicon-stats"></span> Попробовать снова' +
                                    '</button>'
                                );
                            }
                        }
                    },
                    error: function(xhr) {
                        var errorMsg = 'Ошибка соединения с сервером';
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response && response.error) {
                                errorMsg = response.error;
                            }
                        } catch(e) {}
                        
                        self.showNotification(errorMsg, 'danger');
                        
                        if (container.length) {
                            container.html(
                                '<p class="text-muted">Ошибка: ' + errorMsg + '</p>' +
                                '<button type="button" class="btn btn-primary rate-button" data-id="' + id + '">' +
                                    '<span class="glyphicon glyphicon-stats"></span> Попробовать снова' +
                                '</button>'
                            );
                        }
                    }
                });
            });
        },

        /**
         * Инициализация кнопок "Обновить оценку"
         */
        initRefreshButtons: function() {
            var self = this;
            
            $(document).on('click', '.rate-button.rate-refresh', function() {
                var button = $(this);
                var id = button.data('id');
                var container = button.closest('#rating-container');
                
                if (!id) return;
                
                button.prop('disabled', true).html('<span class="spinner"></span> Обновление...');
                
                $.ajax({
                    url: '/advertisements/rate?id=' + id,
                    type: 'POST',
                    data: {
                        _csrf: $('meta[name="csrf-token"]').attr('content') || $('[name="_csrf"]').val()
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            if (container.length) {
                                container.html(response.html);
                                container.append(
                                    '<button type="button" class="btn btn-outline-secondary btn-sm rate-button rate-refresh" data-id="' + id + '" style="margin-top: 10px;">' +
                                        '<span class="glyphicon glyphicon-refresh"></span> Обновить оценку' +
                                    '</button>'
                                );
                            }
                            self.showNotification('Оценка обновлена!', 'success');
                        } else {
                            self.showNotification(response.error || 'Ошибка при обновлении', 'danger');
                            button.prop('disabled', false).html('<span class="glyphicon glyphicon-refresh"></span> Обновить оценку');
                        }
                    },
                    error: function() {
                        self.showNotification('Ошибка соединения с сервером', 'danger');
                        button.prop('disabled', false).html('<span class="glyphicon glyphicon-refresh"></span> Обновить оценку');
                    }
                });
            });
        },

        /**
         * Показать уведомление
         */
        showNotification: function(message, type) {
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, type);
                return;
            }

            var container = $('#notification-container');
            if (!container.length) {
                container = $('<div id="notification-container"></div>');
                $('body').append(container);
            }

            var $notification = $('<div>', {
                class: 'notification notification-' + type + ' show'
            });

            $notification.html(
                '<div class="notification-content">' +
                    '<div class="notification-message">' + message + '</div>' +
                    '<button class="notification-close">&times;</button>' +
                '</div>' +
                '<div class="notification-progress"></div>'
            );

            container.append($notification);

            var timeout = setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            $notification.on('mouseenter', function() {
                clearTimeout(timeout);
                $(this).find('.notification-progress').css('animation-play-state', 'paused');
            });

            $notification.on('mouseleave', function() {
                $(this).find('.notification-progress').css('animation-play-state', 'running');
                timeout = setTimeout(function() {
                    $notification.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, 3000);
            });
        }
    };

    // Инициализация при загрузке
    $(document).ready(function() {
        Rating.init();
    });

    // Экспортируем для использования в других скриптах
    window.Rating = Rating;

})(jQuery);