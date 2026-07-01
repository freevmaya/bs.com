/**
 * Image Grid Preview Widget
 * Открытие полноэкранного просмотра изображений
 */

(function() {
    'use strict';

    // Функции для работы с галереей
    window.openFullscreenGallery = function(images, startIndex) {
        // Проверяем, существует ли уже модальное окно
        var modal = document.getElementById('fullscreen-modal-gallery');
        
        if (!modal) {
            // Создаем модальное окно
            modal = document.createElement('div');
            modal.id = 'fullscreen-modal-gallery';
            modal.className = 'fullscreen-modal';
            modal.style.display = 'none';
            modal.innerHTML = `
                <span class="fullscreen-close" onclick="window.closeFullscreenGallery()">&times;</span>
                <img class="fullscreen-image" id="fullscreen-image-gallery" src="" alt="Fullscreen">
                <div class="fullscreen-nav">
                    <button class="fullscreen-prev" onclick="window.navigateFullscreenGallery(-1)">&#10094;</button>
                    <button class="fullscreen-next" onclick="window.navigateFullscreenGallery(1)">&#10095;</button>
                </div>
                <div class="fullscreen-counter" id="fullscreen-counter-gallery"></div>
            `;
            document.body.appendChild(modal);
            
            // Закрытие по клику на фон
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    window.closeFullscreenGallery();
                }
            });
            
            // Закрытие по ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    window.closeFullscreenGallery();
                }
                if (e.key === 'ArrowLeft') {
                    window.navigateFullscreenGallery(-1);
                }
                if (e.key === 'ArrowRight') {
                    window.navigateFullscreenGallery(1);
                }
            });
        }
        
        // Сохраняем массив изображений
        window.fullscreenGalleryImages = images;
        window.fullscreenGalleryIndex = startIndex || 0;
        
        // Обновляем изображение
        var image = document.getElementById('fullscreen-image-gallery');
        var counter = document.getElementById('fullscreen-counter-gallery');
        
        if (image && images.length > 0) {
            image.src = images[window.fullscreenGalleryIndex];
        }
        
        if (counter && images.length > 1) {
            counter.textContent = (window.fullscreenGalleryIndex + 1) + ' / ' + images.length;
        } else if (counter) {
            counter.textContent = '';
        }
        
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    window.closeFullscreenGallery = function() {
        var modal = document.getElementById('fullscreen-modal-gallery');
        if (modal) {
            modal.style.display = 'none';
        }
        document.body.style.overflow = '';
    };

    window.navigateFullscreenGallery = function(direction) {
        var images = window.fullscreenGalleryImages;
        if (!images || images.length <= 1) return;
        
        window.fullscreenGalleryIndex += direction;
        if (window.fullscreenGalleryIndex < 0) {
            window.fullscreenGalleryIndex = images.length - 1;
        } else if (window.fullscreenGalleryIndex >= images.length) {
            window.fullscreenGalleryIndex = 0;
        }
        
        var image = document.getElementById('fullscreen-image-gallery');
        var counter = document.getElementById('fullscreen-counter-gallery');
        
        if (image && images.length > 0) {
            image.src = images[window.fullscreenGalleryIndex];
        }
        
        if (counter && images.length > 1) {
            counter.textContent = (window.fullscreenGalleryIndex + 1) + ' / ' + images.length;
        }
    };

    // Инициализация обработчиков для всех превью
    function initPreviewHandlers() {
        document.querySelectorAll('.image-grid-preview').forEach(function(container) {
            // Удаляем старый обработчик, если есть
            if (container._clickHandler) {
                container.removeEventListener('click', container._clickHandler);
            }
            
            // Создаем новый обработчик
            container._clickHandler = function(e) {
                // Проверяем, что клик не по ссылке внутри
                if (e.target.closest('a')) {
                    return;
                }
                
                var images = this.querySelectorAll('.grid-item img');
                var fullscreenImages = [];
                
                images.forEach(function(img) {
                    var fullImage = img.getAttribute('data-full-image');
                    if (fullImage) {
                        fullscreenImages.push(fullImage);
                    }
                });
                
                if (fullscreenImages.length === 0) {
                    return;
                }
                
                // Открываем полноэкранный просмотр
                window.openFullscreenGallery(fullscreenImages, 0);
            };
            
            container.addEventListener('click', container._clickHandler);
        });
    }

    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPreviewHandlers);
    } else {
        initPreviewHandlers();
    }

    // MutationObserver для динамически добавляемых элементов
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            var hasNewNodes = false;
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length > 0) {
                    hasNewNodes = true;
                }
            });
            if (hasNewNodes) {
                initPreviewHandlers();
            }
        });
        observer.observe(document.body, { childList: true, subtree: true });
    }

})();