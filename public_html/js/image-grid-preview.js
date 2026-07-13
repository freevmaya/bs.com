/**
 * Image Grid Preview - полноэкранный просмотр
 */

(function() {
    'use strict';

    /**
     * Открытие полноэкранного режима из превью
     */
    window.openFullscreenFromPreview = function(element) {
        var fullImage = element.getAttribute('data-full-image');
        var isVideo = element.getAttribute('data-is-video') === 'true';
        
        if (!fullImage) {
            return;
        }
        
        // Находим родительский контейнер объявления
        var $container = $(element).closest('.media, .advertisement-item, .item, .grid-preview-wrapper');
        
        // Если контейнер не найден, ищем ближайший общий родитель
        if (!$container.length) {
            $container = $(element).closest('[data-advertisement-id], .list-view .media, .advertisements-index .item');
        }
        
        // Если контейнер все еще не найден, берем родителя с классом grid-preview-item
        if (!$container.length) {
            $container = $(element).closest('.grid-preview-item').parent();
        }
        
        // Если контейнер найден, ищем элементы только внутри него
        var previewItems;
        if ($container.length) {
            previewItems = $container.find('.grid-preview-item');
        } else {
            // Fallback: используем все элементы на странице
            previewItems = document.querySelectorAll('.grid-preview-item');
        }
        
        var images = [];
        var currentIndex = 0;
        
        previewItems.each(function(index) {
            var item = this;
            var imgUrl = item.getAttribute('data-full-image');
            if (imgUrl) {
                images.push({
                    url: imgUrl,
                    isVideo: item.getAttribute('data-is-video') === 'true'
                });
                if (item === element) {
                    currentIndex = images.length - 1;
                }
            }
        });
        
        // Если не нашли изображения, пробуем другой метод поиска
        if (images.length === 0) {
            // Ищем все .grid-preview-item в том же родительском контейнере
            var parentContainer = $(element).closest('.media-body, .advertisements-view, .item');
            if (parentContainer.length) {
                previewItems = parentContainer.find('.grid-preview-item');
                previewItems.each(function(index) {
                    var item = this;
                    var imgUrl = item.getAttribute('data-full-image');
                    if (imgUrl) {
                        images.push({
                            url: imgUrl,
                            isVideo: item.getAttribute('data-is-video') === 'true'
                        });
                        if (item === element) {
                            currentIndex = images.length - 1;
                        }
                    }
                });
            }
        }
        
        // Если все еще нет изображений, используем только текущий элемент
        if (images.length === 0) {
            images = [{
                url: fullImage,
                isVideo: isVideo
            }];
            currentIndex = 0;
        }
        
        // Открываем модальное окно
        if (typeof window.openFullscreenFromGallery === 'function') {
            window.openFullscreenFromGallery(images, currentIndex);
        } else {
            createFullscreenModal(images, currentIndex);
        }
    };

    /**
     * Создает модальное окно для полноэкранного просмотра
     */
    function createFullscreenModal(images, startIndex) {
        // Проверяем, не существует ли уже модальное окно
        var existingModal = document.getElementById('preview-fullscreen-modal');
        if (existingModal) {
            existingModal.remove();
        }
        
        var modal = document.createElement('div');
        modal.id = 'preview-fullscreen-modal';
        modal.className = 'fullscreen-modal open';
        modal.style.cssText = 'display: flex; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.95); z-index: 10000; justify-content: center; align-items: center; cursor: pointer;';
        
        var closeBtn = document.createElement('span');
        closeBtn.className = 'fullscreen-close';
        closeBtn.textContent = '×';
        closeBtn.style.cssText = 'position: absolute; top: 20px; right: 40px; color: #fff; font-size: 40px; cursor: pointer; z-index: 10001; transition: transform 0.3s ease; font-weight: 300; line-height: 1;';
        closeBtn.onclick = function(e) {
            e.stopPropagation();
            closeFullscreenModal();
        };
        
        var content = document.createElement('div');
        content.className = 'fullscreen-content';
        content.style.cssText = 'display: flex; justify-content: center; align-items: center; width: 100%; height: 100%;';
        
        var image = document.createElement('img');
        image.className = 'fullscreen-image';
        image.style.cssText = 'max-width: 90%; max-height: 90%; object-fit: contain; box-shadow: 0 0 40px rgba(0,0,0,0.5);';
        
        var video = document.createElement('video');
        video.className = 'fullscreen-video';
        video.style.cssText = 'max-width: 90%; max-height: 90%; background: #000; border-radius: 8px; display: none;';
        video.controls = true;
        video.autoplay = true;
        
        var counter = document.createElement('div');
        counter.className = 'fullscreen-counter';
        counter.style.cssText = 'position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); color: rgba(255,255,255,0.7); font-size: 16px; font-family: Arial, sans-serif;';
        
        var nav = document.createElement('div');
        nav.className = 'fullscreen-nav';
        nav.style.cssText = 'position: absolute; bottom: 50%; width: 100%; display: flex; justify-content: space-between; padding: 0 20px; pointer-events: none;';
        
        var prevBtn = document.createElement('button');
        prevBtn.className = 'fullscreen-prev';
        prevBtn.textContent = '‹';
        prevBtn.style.cssText = 'background: rgba(255,255,255,0.2); border: none; color: #fff; font-size: 30px; padding: 10px 15px; border-radius: 4px; cursor: pointer; pointer-events: auto; transition: background 0.3s ease; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;';
        prevBtn.onclick = function(e) {
            e.stopPropagation();
            navigateFullscreen(-1);
        };
        
        var nextBtn = document.createElement('button');
        nextBtn.className = 'fullscreen-next';
        nextBtn.textContent = '›';
        nextBtn.style.cssText = 'background: rgba(255,255,255,0.2); border: none; color: #fff; font-size: 30px; padding: 10px 15px; border-radius: 4px; cursor: pointer; pointer-events: auto; transition: background 0.3s ease; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center;';
        nextBtn.onclick = function(e) {
            e.stopPropagation();
            navigateFullscreen(1);
        };
        
        nav.appendChild(prevBtn);
        nav.appendChild(nextBtn);
        content.appendChild(image);
        content.appendChild(video);
        modal.appendChild(closeBtn);
        modal.appendChild(content);
        modal.appendChild(counter);
        modal.appendChild(nav);
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        
        var currentIndex = startIndex || 0;
        
        function updateContent() {
            var item = images[currentIndex];
            if (!item) return;
            
            if (item.isVideo) {
                image.style.display = 'none';
                video.style.display = 'block';
                video.src = item.url;
                video.load();
                video.play().catch(function() {});
            } else {
                video.style.display = 'none';
                video.pause();
                video.src = '';
                image.style.display = 'block';
                image.src = item.url;
            }
            
            counter.textContent = (currentIndex + 1) + ' / ' + images.length;
        }
        
        function navigateFullscreen(direction) {
            if (images.length <= 1) return;
            
            currentIndex += direction;
            if (currentIndex < 0) {
                currentIndex = images.length - 1;
            } else if (currentIndex >= images.length) {
                currentIndex = 0;
            }
            
            updateContent();
        }
        
        function closeFullscreenModal() {
            var modal = document.getElementById('preview-fullscreen-modal');
            if (modal) {
                modal.remove();
                document.body.style.overflow = '';
            }
        }
        
        // Обработчики клавиш
        var keydownHandler = function(e) {
            var modal = document.getElementById('preview-fullscreen-modal');
            if (!modal) return;
            
            if (e.key === 'Escape') {
                closeFullscreenModal();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                navigateFullscreen(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                navigateFullscreen(1);
            }
        };
        
        document.addEventListener('keydown', keydownHandler);
        
        // Закрытие по клику на фон
        modal.addEventListener('click', function(e) {
            if (e.target === modal || e.target === content) {
                closeFullscreenModal();
            }
        });
        
        // Инициализация
        updateContent();
        
        // Сохраняем функции для навигации
        window._previewNavigate = navigateFullscreen;
        window._previewClose = closeFullscreenModal;
        
        // Очищаем обработчики при закрытии
        var originalClose = closeFullscreenModal;
        closeFullscreenModal = function() {
            document.removeEventListener('keydown', keydownHandler);
            originalClose();
        };
    }
    
    // Переопределяем функцию из _carousel.php если она существует
    window.openFullscreenFromGallery = function(images, startIndex) {
        createFullscreenModal(images, startIndex);
    };
    
    // Обработка кликов на превью в списке
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.grid-preview-item').forEach(function(item) {
            // Убеждаемся что обработчик onclick не переопределен
            if (!item.hasAttribute('onclick')) {
                item.addEventListener('click', function() {
                    window.openFullscreenFromPreview(this);
                });
            }
        });
    });
})();