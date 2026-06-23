<?php
$flashes = Yii::$app->session->getAllFlashes();
?>

<div id="notification-container">
    <?php foreach ($flashes as $key => $message): ?>
        <div class="notification notification-<?= $key ?> show" style="margin-bottom: 10px;">
            <div class="notification-content">
                <div class="notification-message"><?= $message ?></div>
                <button class="notification-close">&times;</button>
            </div>
            <div class="notification-progress"></div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// Функция для показа уведомления
function showNotification(message, type = 'info') {
    var container = document.getElementById('notification-container');
    if (!container) return;
    
    var notification = document.createElement('div');
    notification.className = 'notification notification-' + type + ' show';
    notification.innerHTML = `
        <div class="notification-content">
            <div class="notification-message">${escapeHtml(message)}</div>
            <button class="notification-close">&times;</button>
        </div>
        <div class="notification-progress"></div>
    `;
    
    container.appendChild(notification);
    
    var closeBtn = notification.querySelector('.notification-close');
    closeBtn.addEventListener('click', function() {
        closeNotification(notification);
    });
    
    var timeout = setTimeout(function() {
        closeNotification(notification);
    }, 10000);
    
    notification.addEventListener('mouseenter', function() {
        clearTimeout(timeout);
        var progress = notification.querySelector('.notification-progress');
        if (progress) {
            progress.style.animationPlayState = 'paused';
        }
    });
    
    notification.addEventListener('mouseleave', function() {
        var progress = notification.querySelector('.notification-progress');
        if (progress) {
            progress.style.animationPlayState = 'running';
        }
        timeout = setTimeout(function() {
            closeNotification(notification);
        }, 3000);
    });
}

function closeNotification(notification) {
    notification.classList.remove('show');
    setTimeout(function() {
        if (notification && notification.remove) {
            notification.remove();
        }
    }, 300);
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.appendChild(document.createTextNode(text));
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', function() {
    var existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(function(notification) {
        var closeBtn = notification.querySelector('.notification-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function() {
                closeNotification(notification);
            });
        }
        
        var timeout = setTimeout(function() {
            closeNotification(notification);
        }, 10000);
        
        notification.addEventListener('mouseenter', function() {
            clearTimeout(timeout);
            var progress = notification.querySelector('.notification-progress');
            if (progress) {
                progress.style.animationPlayState = 'paused';
            }
        });
        
        notification.addEventListener('mouseleave', function() {
            var progress = notification.querySelector('.notification-progress');
            if (progress) {
                progress.style.animationPlayState = 'running';
            }
            timeout = setTimeout(function() {
                closeNotification(notification);
            }, 3000);
        });
    });
});
</script>