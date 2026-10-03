/**
 * Messages - управление личной перепиской
 */

(function($) {
    'use strict';

    var Messages = {
        lastMessageId: 0, // выносим в объект

        init: function() {
            this.initMessageForm();
            this.initPolling();
            this.initScrollToBottom();
            this.initAutoResizeTextarea();
        },

        initMessageForm: function() {
            var self = this;
            
            $('#message-form').on('submit', function(e) {
                e.preventDefault();
                self.sendMessage($(this));
            });

            $('#message-text').on('keydown', function(e) {
                if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                    e.preventDefault();
                    $('#message-form').submit();
                }
            });
        },

        sendMessage: function($form) {
            var self = this;
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
                        $('.messages-body').append(response.message);
                        
                        // Обновляем lastMessageId
                        if (response.messageId) {
                            self.lastMessageId = response.messageId;
                        }
                        
                        $textarea.val('');
                        Messages.scrollToBottom();
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
                    $sendBtn.html('<svg ...></svg>');
                }
            });
        },

        initPolling: function() {
            var self = this;
            var conversationId = $('#messages-container').data('conversation-id');
            
            if (!conversationId) {
                return;
            }

            // Инициализируем lastMessageId из DOM
            var $lastMessage = $('.message-item:last');
            if ($lastMessage.length) {
                self.lastMessageId = $lastMessage.data('message-id') || 0;
            }

            setInterval(function() {
                self.pollNewMessages(conversationId);
            }, 5000);

            setInterval(function() {
                self.updateUnreadCount();
            }, 30000);
        },

        pollNewMessages: function(conversationId) {
            var self = this;

            $.ajax({
                url: '/messages/get-new-messages',
                type: 'POST',
                data: {
                    conversation_id: conversationId,
                    last_message_id: self.lastMessageId,
                    _csrf: $('meta[name="csrf-token"]').attr('content')
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success && response.html) {
                        $('.messages-body').append(response.html);
                        
                        // Обновляем lastMessageId
                        var $newMessages = $(response.html);
                        if ($newMessages.length) {
                            var newLastId = $newMessages.last().data('message-id');
                            if (newLastId) {
                                self.lastMessageId = newLastId;
                            }
                        }
                        
                        self.scrollToBottom();
                    }
                }
            });
        },

        updateUnreadCount: function() {
            // без изменений
        },

        initAutoResizeTextarea: function() {
            var self = this;
            $(document).on('input', '#message-text', function() {
                self.autoResizeTextarea($(this));
            });
        },

        autoResizeTextarea: function($textarea) {
            $textarea.css('height', 'auto');
            $textarea.css('height', $textarea[0].scrollHeight + 'px');
        },

        scrollToBottom: function() {
            var $container = $('.messages-body');
            if ($container.length) {
                $container.scrollTop($container[0].scrollHeight);
            }
        },

        initScrollToBottom: function() {
            this.scrollToBottom();
        },

        showNotification: function(message, type) {
            if (typeof window.showNotification === 'function') {
                window.showNotification(message, type);
                return;
            }
            alert(message);
        }
    };

    $(document).ready(function() {
        Messages.init();
    });

    window.Messages = Messages;

})(jQuery);