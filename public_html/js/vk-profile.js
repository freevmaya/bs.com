/**
 * VK Profile - автоматическое определение ID по ссылке на профиль
 */

(function($) {
    'use strict';

    var VkProfile = {
        // Загружен ли VK API
        vkApiLoaded: false,
        // Таймер для debounce
        debounceTimer: null,
        // Текущий обрабатываемый URL
        currentUrl: null,

        /**
         * Инициализация
         */
        init: function() {
            this.loadVkApi();
            this.bindEvents();
        },

        /**
         * Загрузка VK API
         */
        loadVkApi: function() {
            // Проверяем, загружен ли уже VK API
            if (typeof VK !== 'undefined') {
                this.vkApiLoaded = true;
                return;
            }

            // Загружаем VK API
            var script = document.createElement('script');
            script.src = 'https://vk.com/js/api/openapi.js?169';
            script.async = true;
            script.onload = function() {
                VkProfile.vkApiLoaded = true;
                console.log('VK API loaded');
            };
            document.head.appendChild(script);
        },

        /**
         * Привязка событий
         */
        bindEvents: function() {
            var $input = $('#vk-profile-url');
            var $result = $('#vk-id-result');
            var $display = $('#vk-id-display');
            var $cancelBtn = $('#vk-id-cancel');

            // Ввод текста с debounce
            $input.on('input', function() {
                var url = $(this).val().trim();
                
                // Скрываем предыдущий результат
                $result.hide();
                $(this).removeClass('vk-success vk-error');
                
                // Очищаем таймер
                clearTimeout(this.debounceTimer);
                
                // Если URL пустой или не похож на VK, ничего не делаем
                if (!url || !url.match(/vk\.com/)) {
                    return;
                }
                
                // Ждем паузу перед запросом
                this.debounceTimer = setTimeout(function() {
                    VkProfile.resolveProfile(url);
                }, 500);
            });

            // Отмена определения ID
            $cancelBtn.on('click', function() {
                $input.val('').removeClass('vk-success vk-error');
                $result.hide();
                $input.focus();
            });
        },

        /**
         * Получение ID профиля
         */
        resolveProfile: function(url) {
            var $input = $('#vk-profile-url');
            var $result = $('#vk-id-result');
            var $display = $('#vk-id-display');

            // Проверяем, что VK API загружен
            if (!this.vkApiLoaded) {
                this.showError('VK API еще не загружен. Попробуйте еще раз через секунду.');
                return;
            }

            // Извлекаем screen_name из URL
            var screenName = this.extractScreenName(url);
            if (!screenName) {
                this.showError('Не удалось определить пользователя по ссылке');
                return;
            }

            // Показываем индикатор загрузки
            $input.addClass('vk-resolving').removeClass('vk-success vk-error');

            // Вызываем VK API
            VK.Api.call('users.get', {
                user_ids: screenName,
                v: '5.131'
            }, function(response) {
                $input.removeClass('vk-resolving');
                
                if (response && response.response && response.response.length > 0) {
                    var user = response.response[0];
                    var userId = user.id;
                    var userName = user.first_name + ' ' + user.last_name;
                    
                    // Показываем результат
                    $display.text(userId + ' (' + userName + ')');
                    $result.show();
                    $input.addClass('vk-success');
                    
                    // Добавляем скрытое поле с ID
                    // Если поле vk_id существует, заполняем его
                    var $vkIdField = $('input[name="User[vk_id]"]');
                    if ($vkIdField.length) {
                        $vkIdField.val(userId);
                    }
                    
                    // Сохраняем в data-атрибут для отправки
                    $input.data('vk-id', userId);
                    
                    // Показываем уведомление
                    VkProfile.showNotification('VK ID определен: ' + userId, 'success');
                } else {
                    var errorMsg = 'Пользователь не найден';
                    if (response && response.error) {
                        errorMsg = response.error.error_msg || errorMsg;
                    }
                    VkProfile.showError(errorMsg);
                }
            });
        },

        /**
         * Извлечение screen_name из URL
         */
        extractScreenName: function(url) {
            // Парсим URL
            try {
                var urlObj = new URL(url);
                var hostname = urlObj.hostname.toLowerCase();
                
                // Проверяем, что это VK
                if (!hostname.includes('vk.com') && !hostname.includes('vkontakte.ru')) {
                    return null;
                }
                
                // Получаем путь
                var path = urlObj.pathname.replace(/^\//, '').replace(/\/$/, '');
                if (!path) {
                    return null;
                }
                
                // Если путь начинается с id, берем все после id
                if (path.match(/^id\d+$/)) {
                    return path; // id123456789
                }
                
                // Иначе берем имя пользователя
                // Убираем возможные параметры после /
                var parts = path.split('/');
                return parts[0];
            } catch (e) {
                return null;
            }
        },

        /**
         * Показать ошибку
         */
        showError: function(message) {
            var $input = $('#vk-profile-url');
            var $result = $('#vk-id-result');
            
            $input.removeClass('vk-resolving').addClass('vk-error');
            $result.hide();
            
            VkProfile.showNotification(message, 'danger');
        },

        /**
         * Показать уведомление
         */
        showNotification: function(message, type) {
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, type);
                return;
            }
            
            // Fallback
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

            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Инициализация при загрузке
    $(document).ready(function() {
        VkProfile.init();
    });

    // Экспортируем для использования в других скриптах
    window.VkProfile = VkProfile;

})(jQuery);