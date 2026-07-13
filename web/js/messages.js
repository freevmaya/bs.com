/**
 * Messages - управление личной перепиской
 */

(function($) {
    'use strict';

    var Messages = {
        /**
         * Инициализация
         */
        init: function() {
            this.initMessageForm();
            this.initPolling();
            this.initScrollToBottom();
            this.initAutoResizeTextarea();
        },

        /**
         * Инициализация формы отправки сообщения
         */
        initMessageForm: function() {
            var self = this;
            
            $('#message-form').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage($(this));
            });

            // Отправка по Ctrl+Enter
            $('#message-text').on('keydown', function(e) {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    $('#message-form').submit();
                }
            });
        },

        /**
         * Отправка сообщения
         */
        sendMessage: function($form) {
            var $textarea = $form.find('#message-text');
            var message = $textarea.val().trim();
            
            if (!message) {
                return;
            }

            var $sendBtn = $form.find('.btn-send');
            $sendBtn.prop('disabled', true);
            $sendBtn.html('<span class="spinner-border spinner-border-sm" role="status"></span>');

            $.ajax({
                url: $form.attr('action'),
                type: 'POST',
                data: {
                    conversation_id: $form.data('conversation-id'),
                    message: message,
                    _csrf: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Добавляем сообщение в чат
                        $('.messages-body').append(response.message);
                        
                        // Очищаем поле ввода
                        $textarea.val('');
                        
                        // Прокручиваем вниз
                        Messages.scrollToBottom();
                        
                        // Обновляем размер textarea
                        Messages.autoResizeTextarea($textarea);
                    } else {
                        Messages.showNotification(response.error || 'Ошибка отправки', 'danger');
                    }
                },
                error: function() {
                    Messages.showNotification('Ошибка соединения с сервером', 'danger');
                },
                complete: function() {
                    $sendBtn.prop('disabled', false);
                    $sendBtn.html('<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>');
                }
            });
        },

        /**
         * Поллинг новых сообщений
         */
        initPolling: function() {
            var self = this;
            var conversationId = $('#messages-container').data('conversation-id');
            
            if (!conversationId) {
                return;
            }

            // Получаем последний ID сообщения
            var lastMessageId = 0;
            var $lastMessage = $('.message-item:last');
            if ($lastMessage.length) {
                lastMessageId = $lastMessage.data('message-id') || 0;
            }

            // Запускаем поллинг каждые 5 секунд
            setInterval(function() {
                self.pollNewMessages(conversationId, lastMessageId);
            }, 5000);

            // Обновляем счетчик непрочитанных в реальном времени
            setInterval(function() {
                self.updateUnreadCount();
            }, 30000);
        },

        /**
         * Получение новых сообщений
         */
        pollNewMessages: function(conversationId, lastMessageId) {
            var self = this;

            $.ajax({
                url: '/messages/get-new-messages',
                type: 'POST',
                data: {
                    conversation_id: conversationId,
                    last_message_id: lastMessageId,
                    _csrf: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.html) {
                        $('.messages-body').append(response.html);
                        
                        // Обновляем lastMessageId
                        var $newMessages = $(response.html);
                        if ($newMessages.length) {
                            lastMessageId = $newMessages.last().data('message-id') || lastMessageId;
                        }
                        
                        // Прокручиваем вниз
                        self.scrollToBottom();
                    }
                }
            });
        },

        /**
         * Обновление счетчика непрочитанных сообщений
         */
        updateUnreadCount: function() {
            $.ajax({
                url: '/messages/get-unread-count',
                type: 'POST',
                data: {
                    _csrf: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.count > 0) {
                        var $badge = $('#messages-unread-badge');
                        if ($badge.length) {
                            $badge.text(response.count).show();
                        }
                    } else {
                        $('#messages-unread-badge').hide();
                    }
                }
            });
        },

        /**
         * Автоматическая подстройка высоты textarea
         */
        initAutoResizeTextarea: function() {
            var self = this;
            
            $(document).on('input', '#message-text', function() {
                self.autoResizeTextarea($(this));
            });
        },

        /**
         * Подстройка высоты textarea
         */
        autoResizeTextarea: function($textarea) {
            $textarea.css('height', 'auto');
            $textarea.css('height', $textarea[0].scrollHeight + 'px');
        },

        /**
         * Прокрутка вниз
         */
        scrollToBottom: function() {
            var $container = $('.messages-body');
            if ($container.length) {
                $container.scrollTop($container[0].scrollHeight);
            }
        },

        /**
         * Инициализация прокрутки
         */
        initScrollToBottom: function() {
            this.scrollToBottom();
        },

        /**
         * Показать уведомление
         */
        showNotification: function(message, type) {
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, type);
                return;
            }
            alert(message);
        }
    };

    // Инициализация при загрузке
    $(document).ready(function() {
        Messages.init();
    });

    // Экспортируем для использования в других скриптах
    window.Messages = Messages;

})(jQuery);