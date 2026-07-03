/**
 * Search Active Subscribe - управление кнопкой подписки в блоке активных параметров
 */

(function($) {
    'use strict';

    var SearchActiveSubscribe = {
        /**
         * Инициализация
         */
        init: function() {
            this.initSubscribeButton();
        },

        /**
         * Инициализация кнопки подписки в блоке активных параметров
         */
        initSubscribeButton: function() {
            $(document).on('click', '.search-subscribe-btn', function() {
                var $button = $(this);
                
                // Собираем все параметры из формы поиска
                var params = {};
                var hasAnyValue = false;
                var section = '';
                
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
                section = $button.data('section') || 
                          $('input[name="AdvertisementSearch[section]"]').val() || 
                          '';
                
                // Также ищем секцию в URL
                if (!section) {
                    var urlParts = window.location.pathname.split('/');
                    if (urlParts.indexOf('sell') > -1) {
                        section = 'sell';
                    } else if (urlParts.indexOf('buy') > -1) {
                        section = 'buy';
                    }
                }
                
                // Если параметров нет, но есть текст поиска - берем его
                if (!hasAnyValue) {
                    var searchText = $('input[name="AdvertisementSearch[search_text]"]').val();
                    if (searchText && searchText.trim() !== '') {
                        params['search_text'] = searchText.trim();
                        hasAnyValue = true;
                    }
                }
                
                if (!hasAnyValue) {
                    SearchActiveSubscribe.showNotification('Укажите хотя бы один параметр для подписки (поиск, цена, город и т.д.)', 'warning');
                    return;
                }
                
                var originalText = $button.html();
                $button.prop('disabled', true).html('<span class="glyphicon glyphicon-refresh glyphicon-spin"></span> <span class="btn-text">Сохранение...</span>');
                
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
                            SearchActiveSubscribe.showNotification(response.message || 'Подписка создана!', 'success');
                            $button.html('<span class="glyphicon glyphicon-ok"></span> <span class="btn-text">Подписано</span>');
                            setTimeout(function() {
                                $button.html(originalText);
                                $button.prop('disabled', false);
                            }, 3000);
                        } else {
                            SearchActiveSubscribe.showNotification(response.error || 'Ошибка при создании подписки', 'danger');
                            $button.html(originalText);
                            $button.prop('disabled', false);
                        }
                    },
                    error: function() {
                        SearchActiveSubscribe.showNotification('Ошибка соединения с сервером', 'danger');
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
        }
    };

    // Инициализация при загрузке страницы
    $(document).ready(function() {
        SearchActiveSubscribe.init();
    });

    // Экспортируем объект для использования в других скриптах
    window.SearchActiveSubscribe = SearchActiveSubscribe;

})(jQuery);