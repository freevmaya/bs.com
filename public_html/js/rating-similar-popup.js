/**
 * Rating Similar Popup - всплывающее окно со списком аналогичных объявлений
 */

(function($) {
    'use strict';

    /**
     * Открыть popup со списком аналогов
     */
    function openSimilarPopup(ratingId, similarData) {
        // Удаляем старый popup, если есть
        $('.rating-similar-popup').remove();

        if (!similarData || similarData.length === 0) {
            if (typeof window.showNotification === 'function') {
                window.showNotification('Нет данных об аналогичных объявлениях', 'info');
            }
            return;
        }

        var html = '<div class="rating-similar-popup open">';
        html += '<div class="rating-similar-popup-content">';
        html += '<div class="rating-similar-popup-header">';
        html += '<h4 class="rating-similar-popup-title">📊 Аналогичные объявления (' + similarData.length + ')</h4>';
        html += '<button type="button" class="rating-similar-popup-close" aria-label="Закрыть">&times;</button>';
        html += '</div>';
        html += '<div class="rating-similar-popup-body">';

        for (var i = 0; i < similarData.length; i++) {
            var ad = similarData[i];
            html += '<a href="' + ad.url + '" class="rating-similar-item" target="_blank">';
            html += '<div class="rating-similar-item-title">' + escapeHtml(ad.title) + '</div>';
            html += '<div class="rating-similar-item-meta">';
            
            if (ad.price_formatted) {
                html += '<span class="rating-similar-item-price">' + escapeHtml(ad.price_formatted) + '</span>';
            }
            if (ad.city) {
                html += '<span>📍 ' + escapeHtml(ad.city) + '</span>';
            }
            if (ad.year) {
                html += '<span>📅 ' + escapeHtml(String(ad.year)) + '</span>';
            }
            if (ad.short_info) {
                html += '<span>' + escapeHtml(ad.short_info) + '</span>';
            }
            
            html += '</div>';
            html += '</a>';
        }

        html += '</div>';
        html += '</div>';
        html += '</div>';

        $('body').append(html);

        // Блокируем скролл body
        $('body').css('overflow', 'hidden');

        // Закрытие по крестику
        $('.rating-similar-popup-close').on('click', function() {
            closeSimilarPopup();
        });

        // Закрытие по клику на фон
        $('.rating-similar-popup').on('click', function(e) {
            if (e.target === this) {
                closeSimilarPopup();
            }
        });

        // Закрытие по Escape
        $(document).on('keydown.ratingSimilarPopup', function(e) {
            if (e.key === 'Escape') {
                closeSimilarPopup();
            }
        });
    }

    /**
     * Закрыть popup
     */
    function closeSimilarPopup() {
        $('.rating-similar-popup').remove();
        $('body').css('overflow', '');
        $(document).off('keydown.ratingSimilarPopup');
    }

    /**
     * Экранирование HTML
     */
    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        var div = document.createElement('div');
        div.textContent = String(text);
        return div.innerHTML;
    }

    // Обработчик клика на ссылку "На основе анализа N аналогичных объявлений"
    $(document).on('click', '.rating-similar-link', function(e) {
        e.preventDefault();

        var $link = $(this);
        var ratingId = $link.data('rating-id');
        var similarIds = $link.data('similar-ids');

        if (!similarIds) {
            return;
        }

        // Ищем JSON с данными аналогов
        var $script = $('script.rating-similar-data[data-rating-id="' + ratingId + '"]');

        if ($script.length === 0) {
            if (typeof window.showNotification === 'function') {
                window.showNotification('Данные об аналогах не найдены', 'warning');
            }
            return;
        }

        try {
            var similarData = JSON.parse($script.text());
            openSimilarPopup(ratingId, similarData);
        } catch (err) {
            console.error('Failed to parse similar data:', err);
            if (typeof window.showNotification === 'function') {
                window.showNotification('Ошибка загрузки данных об аналогах', 'danger');
            }
        }
    });

    // Экспортируем для внешнего использования
    window.RatingSimilarPopup = {
        open: openSimilarPopup,
        close: closeSimilarPopup
    };

})(jQuery);