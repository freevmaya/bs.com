<?php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var array $images - массив изображений
 * @var string $id - уникальный ID для галереи
 */

if (empty($images)) {
    return;
}

$id = $id ?? 'gallery-' . uniqid();
?>

<div class="gallery-container">
    <div class="gallery-grid" id="<?= $id ?>">
        <?php foreach ($images as $index => $image): ?>
            <div class="gallery-item">
                <img src="<?= $image->getThumbnailUrl() ?>" 
                     alt="Фото <?= $index + 1 ?>" 
                     class="gallery-thumb"
                     data-full-image="<?= $image->getImageUrl() ?>"
                     onclick="openFullscreen(this)">
                <div class="gallery-overlay">
                    <span class="glyphicon glyphicon-search"></span>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Модальное окно для полноэкранного просмотра -->
<div id="fullscreen-modal" class="fullscreen-modal" onclick="closeFullscreen()">
    <span class="fullscreen-close" onclick="event.stopPropagation(); closeFullscreen();">&times;</span>
    <img class="fullscreen-image" id="fullscreen-image" src="" alt="Fullscreen">
    <div class="fullscreen-nav">
        <button class="fullscreen-prev" onclick="event.stopPropagation(); navigateFullscreen(-1);">&#10094;</button>
        <button class="fullscreen-next" onclick="event.stopPropagation(); navigateFullscreen(1);">&#10095;</button>
    </div>
    <div class="fullscreen-counter" id="fullscreen-counter"></div>
</div>

<script>
var fullscreenImages = [];
var currentFullscreenIndex = 0;

// Открытие полноэкранного режима
function openFullscreen(element) {
    // Собираем все изображения из галереи
    var thumbElements = document.querySelectorAll('.gallery-thumb');
    fullscreenImages = [];
    thumbElements.forEach(function(img) {
        fullscreenImages.push(img.getAttribute('data-full-image'));
    });
    
    var src = element.getAttribute('data-full-image');
    currentFullscreenIndex = fullscreenImages.indexOf(src);
    if (currentFullscreenIndex === -1) {
        currentFullscreenIndex = 0;
    }
    
    var modal = document.getElementById('fullscreen-modal');
    var image = document.getElementById('fullscreen-image');
    var counter = document.getElementById('fullscreen-counter');
    
    image.src = src;
    if (fullscreenImages.length > 1) {
        counter.textContent = (currentFullscreenIndex + 1) + ' / ' + fullscreenImages.length;
    } else {
        counter.textContent = '';
    }
    
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
}

// Закрытие полноэкранного режима
function closeFullscreen() {
    var modal = document.getElementById('fullscreen-modal');
    modal.classList.remove('open');
    document.body.style.overflow = '';
}

// Навигация в полноэкранном режиме
function navigateFullscreen(direction) {
    if (fullscreenImages.length <= 1) return;
    
    currentFullscreenIndex += direction;
    if (currentFullscreenIndex < 0) {
        currentFullscreenIndex = fullscreenImages.length - 1;
    } else if (currentFullscreenIndex >= fullscreenImages.length) {
        currentFullscreenIndex = 0;
    }
    
    var image = document.getElementById('fullscreen-image');
    var counter = document.getElementById('fullscreen-counter');
    
    image.src = fullscreenImages[currentFullscreenIndex];
    counter.textContent = (currentFullscreenIndex + 1) + ' / ' + fullscreenImages.length;
}

// Закрытие по клавише ESC
document.addEventListener('keydown', function(e) {
    var modal = document.getElementById('fullscreen-modal');
    if (e.key === 'Escape') {
        closeFullscreen();
    }
    if (modal.classList.contains('open')) {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            navigateFullscreen(-1);
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            navigateFullscreen(1);
        }
    }
});
</script>