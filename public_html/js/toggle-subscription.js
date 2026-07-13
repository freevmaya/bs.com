/**
 * Toggle Subscription - управление каналами уведомлений с переключателями (switches)
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var csrfToken = $('meta[name="csrf-token"]').attr('content') || $('[name="_csrf"]').val();

        $(document).on('change', '.toggle-channel', function(event) {
            var $switch = $(this);
            var channel = $switch.data('channel');
            
            // Проверяем, не заблокирован ли переключатель
            if ($switch.prop('disabled')) {
                // Возвращаем предыдущее состояние
                var previousActive = $switch.data('active') === 'true';
                $switch.prop('checked', previousActive);
                return;
            }
            
            // Определяем новое состояние (которое хочет установить пользователь)
            var newActive = $switch.prop('checked');
            
            // Блокируем переключатель
            $switch.prop('disabled', true).addClass('updating');
            
            // Определяем URL в зависимости от действия
            var url = newActive 
                ? $switch.data('enable-url') 
                : $switch.data('disable-url');
            
            var actionText = newActive ? 'включение' : 'выключение';
            
            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    channel: channel,
                    _csrf: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Обновляем data-атрибут
                        $switch.data('active', newActive ? 'true' : 'false');
                        
                        // Обновляем текст статуса
                        var $setting = $switch.closest('.channel-setting');
                        var $statusText = $setting.find('.toggle-status');
                        if ($statusText.length) {
                            if (newActive) {
                                $statusText.text('Включены').css('color', '#28a745');
                            } else {
                                $statusText.text('Выключены').css('color', '#6c757d');
                            }
                        }
                        
                        showNotification(response.message || 'Готово', 'success');
                    } else {
                        // Возвращаем предыдущее состояние
                        var previousActive = !newActive;
                        $switch.prop('checked', previousActive);
                        showNotification(response.error || 'Ошибка при ' + actionText, 'danger');
                    }
                },
                error: function(xhr, status, error) {
                    // Возвращаем предыдущее состояние
                    var previousActive = !newActive;
                    $switch.prop('checked', previousActive);
                    
                    var errorMsg = 'Ошибка соединения с сервером';
                    try {
                        var response = JSON.parse(xhr.responseText);
                        if (response && response.error) {
                            errorMsg = response.error;
                        }
                    } catch(e) {}
                    showNotification(errorMsg, 'danger');
                },
                complete: function() {
                    $switch.prop('disabled', false).removeClass('updating');
                }
            });
        });
    });

    /**
     * Показать уведомление
     */
    function showNotification(message, type) {
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

})(jQuery);