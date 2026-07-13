// FILE: .\web\js\search-subscription.js

(function($) {
    'use strict';

    /**
     * Инициализация управления подписками
     */
    var SearchSubscription = {
        /**
         * Инициализация всех компонентов
         */
        init: function() {
            this.initDeleteButtons();
            this.initSubscribeButton();
            this.initNotificationContainer();
        },

        /**
         * Инициализация кнопок удаления подписки
         */
        initDeleteButtons: function() {
            $(document).on('click', '.delete-subscription', function() {
                var $button = $(this);
                var id = $button.data('id');

                if (!confirm('Вы уверены, что хотите отписаться?')) {
                    return;
                }

                $button.prop('disabled', true).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Удаление...');

                $.ajax({
                    url: '/search-subscription/delete?id=' + id,
                    type: 'POST',
                    data: {
                        _csrf: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            $button.closest('.card').fadeOut(300, function() {
                                $(this).remove();
                                SearchSubscription.showNotification(response.message, 'success');
                                if ($('.card').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            SearchSubscription.showNotification(response.error || 'Ошибка при удалении', 'danger');
                            $button.prop('disabled', false).html('<span class="glyphicon glyphicon-trash"></span> Отписаться');
                        }
                    },
                    error: function() {
                        SearchSubscription.showNotification('Ошибка соединения с сервером', 'danger');
                        $button.prop('disabled', false).html('<span class="glyphicon glyphicon-trash"></span> Отписаться');
                    }
                });
            });
        },

        /**
         * Инициализация кнопки подписки
         */
        initSubscribeButton: function() {
            $(document).on('click', '#subscribe-button, .subscribe-button', function() {
                var $button = $(this);
                
                // Собираем все параметры из всех форм на странице
                var params = {};
                var hasAnyValue = false;
                
                // Ищем все поля с именем AdvertisementSearch[...]
                $('input[name^="AdvertisementSearch["], select[name^="AdvertisementSearch["]').each(function() {
                    var $field = $(this);
                    var name = $field.attr('name');
                    var value = $field.val();
                    
                    // Пропускаем пустые значения
                    if (value === '' || value === null || value === undefined) {
                        return;
                    }
                    
                    // Обрабатываем множественные значения (select multiple)
                    if ($field.is('select') && $field.prop('multiple')) {
                        var selectedValues = $field.val() || [];
                        if (selectedValues.length > 0) {
                            var key = name.replace('[]', '');
                            params[key] = selectedValues;
                            hasAnyValue = true;
                        }
                        return;
                    }
                    
                    // Пропускаем пустые строки и нулевые значения для числовых полей
                    if (value === '' || value === '0') {
                        return;
                    }
                    
                    // Сохраняем значение
                    var cleanName = name.replace('AdvertisementSearch[', '').replace(']', '');
                    params[cleanName] = value;
                    hasAnyValue = true;
                });
                
                // Также проверяем скрытые поля
                $('input[type="hidden"][name^="AdvertisementSearch["]').each(function() {
                    var name = $(this).attr('name');
                    var value = $(this).val();
                    
                    if (value && value !== '') {
                        var cleanName = name.replace('AdvertisementSearch[', '').replace(']', '');
                        if (!params[cleanName]) {
                            params[cleanName] = value;
                            hasAnyValue = true;
                        }
                    }
                });
                
                // Получаем секцию
                var section = $button.data('section') || 
                              $('input[name="AdvertisementSearch[section]"]').val() || 
                              '';

                // Логируем для отладки
                console.log('Collected params:', params);
                console.log('Has any value:', hasAnyValue);
                
                if (!hasAnyValue) {
                    SearchSubscription.showNotification('Укажите хотя бы один параметр для подписки (поиск, цена, город и т.д.)', 'warning');
                    return;
                }

                var originalText = $button.html();
                $button.prop('disabled', true).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> Сохранение...');

                $.ajax({
                    url: '/search-subscription/create',
                    type: 'POST',
                    data: {
                        params: params,
                        section: section,
                        _csrf: $('meta[name="csrf-token"]').attr('content')
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            SearchSubscription.showNotification(response.message || 'Подписка создана!', 'success');
                            $button.html('<span class="glyphicon glyphicon-ok"></span> Подписано');
                            setTimeout(function() {
                                $button.html(originalText);
                                $button.prop('disabled', false);
                            }, 2000);
                        } else {
                            SearchSubscription.showNotification(response.error || 'Ошибка при создании подписки', 'danger');
                            $button.html(originalText);
                            $button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        SearchSubscription.showNotification('Ошибка соединения с сервером', 'danger');
                        $button.html(originalText);
                        $button.prop('disabled', false);
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
        },

        /**
         * Инициализация контейнера уведомлений
         */
        initNotificationContainer: function() {
            if (!$('#notification-container').length) {
                $('body').append('<div id="notification-container"></div>');
            }
        }
    };

    // Инициализация при загрузке страницы
    $(document).ready(function() {
        SearchSubscription.init();
    });

    // Экспортируем объект для использования в других скриптах
    window.SearchSubscription = SearchSubscription;

})(jQuery);