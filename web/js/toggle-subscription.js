/**
 * Toggle Subscription - управление подписками на уведомления
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var csrfToken = $('meta[name="csrf-token"]').attr('content') || $('[name="_csrf"]').val();

        $(document).on('click', '.toggle-subscription', function() {
            var $button = $(this);
            var event = $button.data('event');
            var channel = $button.data('channel');
            var isSubscribed = $button.hasClass('btn-success');

            var url = isSubscribed
                ? $button.data('unsubscribe-url') || '/notification/unsubscribe'
                : $button.data('subscribe-url') || '/notification/subscribe';

            $button.prop('disabled', true);
            $button.text('Обработка...');

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    event: event,
                    channel: channel,
                    _csrf: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (isSubscribed) {
                            $button.removeClass('btn-success').addClass('btn-default');
                            $button.text('Подписаться');
                        } else {
                            $button.removeClass('btn-default').addClass('btn-success');
                            $button.text('✓ Подписан');
                        }

                        showNotification(
                            isSubscribed ? 'Вы отписались от уведомлений' : 'Вы подписались на уведомления',
                            isSubscribed ? 'info' : 'success'
                        );
                    } else {
                        var errorMsg = response.error || 'Неизвестная ошибка';
                        showNotification('Ошибка: ' + errorMsg, 'danger');
                        $button.text(isSubscribed ? '✓ Подписан' : 'Подписаться');
                    }
                },
                error: function() {
                    showNotification('Произошла ошибка при отправке запроса', 'danger');
                    $button.text(isSubscribed ? '✓ Подписан' : 'Подписаться');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    });

})(jQuery);