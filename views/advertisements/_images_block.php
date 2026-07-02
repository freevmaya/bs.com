<?php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var array $images - массив изображений (для update - реальные, для create - временные)
 * @var string $type - тип: 'create' или 'update'
 * @var int $id - ID объявления (для update) или tempId (для create)
 */

$isCreate = ($type === 'create');
$isUpdate = ($type === 'update');

// Определяем URL для загрузки и удаления
if ($isCreate) {
    $addImageUrl = Url::to(['advertisements/add-temp-image', 'tempId' => $id]);
    $deleteImageUrl = Url::to(['advertisements/delete-temp-image-ajax']);
    $imageDataAttribute = 'data-image-index';
    $imageIdKey = 'index';
} else {
    $addImageUrl = Url::to(['advertisements/add-image', 'id' => $id]);
    $deleteImageUrl = Url::to(['advertisements/delete-image-ajax']);
    $imageDataAttribute = 'data-image-id';
    $imageIdKey = 'imageId';
}
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">Фотографии и видео товара</h3>
    </div>
    <div class="panel-body">
        <!-- Контейнер для изображений -->
        <div id="images-container" class="row" style="margin-bottom: 20px;">
            <?php if (!empty($images)): ?>
                <?php foreach ($images as $index => $image): ?>
                    <div class="col-md-3 col-sm-4 col-xs-6" <?= $imageDataAttribute ?>="<?= $isCreate ? $index : $image->id ?>">
                        <div class="thumbnail" style="position: relative;">
                            <?php
                            $isVideo = false;
                            if ($isCreate) {
                                // Для временных файлов определяем по расширению
                                $ext = pathinfo($image['file_name'], PATHINFO_EXTENSION);
                                $videoExts = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
                                $isVideo = in_array(strtolower($ext), $videoExts);
                            } else {
                                $isVideo = $image->isVideo();
                            }
                            ?>
                            <?php if ($isVideo): ?>
                                <div style="position: relative; width: 100%; height: 100px; overflow: hidden; background: #000;">
                                    <?php
                                    $thumbUrl = $isCreate
                                        ? Url::to('@web/uploads/advertisements/' . $image['thumbnail_path'])
                                        : $image->getThumbnailUrl();
                                    ?>
                                    <img src="<?= $thumbUrl ?>" alt="Video preview" style="width: 100%; height: 100%; object-fit: cover;"
                                         onerror="this.style.display='none'; this.parentElement.style.background='#222';">
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 30px; opacity: 0.8;">
                                        <span class="glyphicon glyphicon-play"></span>
                                    </div>
                                    <div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.7); color: white; font-size: 11px; padding: 2px 6px; border-radius: 3px;">
                                        Видео
                                    </div>
                                </div>
                            <?php else: ?>
                                <img src="<?= $isCreate ? Url::to('@web/uploads/advertisements/' . $image['thumbnail_path']) : $image->getThumbnailUrl() ?>"
                                     alt="Image" class="img-responsive" style="height: 100px; width: 100%; object-fit: cover;"
                                     onerror="this.src=''; this.parentElement.style.background='#f0f0f0';">
                            <?php endif; ?>
                            <div class="caption" style="padding: 5px;">
                                <button type="button" class="btn btn-danger btn-sm btn-block delete-image-btn"
                                        data-id="<?= $isCreate ? $index : $image->id ?>"
                                        data-type="<?= $type ?>">
                                    Удалить
                                </button>
                            </div>
                            <?php if ($isUpdate): ?>
                                <div class="sort-handle" style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); color: white; padding: 2px 5px; border-radius: 3px; cursor: move;">
                                    <span class="glyphicon glyphicon-move"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Сообщение при отсутствии изображений -->
        <div id="no-images-message" class="text-muted text-center" style="<?= !empty($images) ? 'display: none;' : '' ?> margin-bottom: 20px;">
            <p>Нет загруженных файлов</p>
        </div>

        <!-- Drag & Drop зона + выбор файла -->
        <div id="drag-drop-zone" class="drag-drop-zone" style="border: 2px dashed #ccc; border-radius: 10px; padding: 30px; text-align: center; margin-bottom: 20px; cursor: pointer; transition: all 0.3s ease;">
            <div class="drag-drop-content">
                <span class="glyphicon glyphicon-cloud-upload" style="font-size: 48px; color: #999;"></span>
                <h4>Перетащите файлы сюда</h4>
                <p class="text-muted">Поддерживаются изображения (JPG, PNG, GIF, WEBP) и видео (MP4, MOV, AVI, MKV, WEBM)</p>
                <p class="text-muted" style="font-size: 12px;">Максимальный размер файла: 100 MB</p>
                <p class="text-muted">или</p>
                <label class="btn btn-primary btn-file">
                    Выберите файл
                    <input type="file" accept="image/*,video/*" id="image-file-input" data-type="<?= $type ?>" style="display: none;">
                </label>
            </div>
        </div>

        <!-- Прогресс загрузки -->
        <div id="upload-progress" style="display: none; margin-top: 10px;">
            <div class="progress">
                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%">
                    Загрузка...
                </div>
            </div>
        </div>

        <div id="upload-error" class="alert alert-danger" style="display: none; margin-top: 10px;">
            <span class="glyphicon glyphicon-exclamation-sign"></span>
            <span id="error-message"></span>
        </div>

        <?php if ($isUpdate): ?>
            <p class="help-block">* Перетаскивайте файлы мышкой для изменения порядка</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Глобальные переменные для каждого экземпляра
var csrfToken = '<?= Yii::$app->request->csrfToken ?>';
var isCreateMode = <?= $isCreate ? 'true' : 'false' ?>;
var currentType = '<?= $type ?>';
var addImageUrl = '<?= $addImageUrl ?>';
var deleteImageUrl = '<?= $deleteImageUrl ?>';
var reorderUrl = '<?= Url::to(['advertisements/reorder-images']) ?>';
var maxFileSize = 100 * 1024 * 1024; // 100 MB

// DOM элементы
var dragDropZone = document.getElementById('drag-drop-zone');
var fileInput = document.getElementById('image-file-input');
var progress = document.getElementById('upload-progress');
var uploadError = document.getElementById('upload-error');
var errorMessage = document.getElementById('error-message');
var imagesContainer = document.getElementById('images-container');
var noImagesMessage = document.getElementById('no-images-message');

// Функция для скрытия ошибки
function hideError() {
    if (uploadError) {
        uploadError.style.display = 'none';
        if (errorMessage) errorMessage.textContent = '';
    }
}

// Функция для показа ошибки
function showError(message) {
    if (uploadError && errorMessage) {
        errorMessage.textContent = message;
        uploadError.style.display = 'block';
        setTimeout(function() {
            uploadError.style.display = 'none';
        }, 5000);
    }
}

// Функция для показа уведомления
function showNotification(type, message) {
    var alertDiv = document.createElement('div');
    alertDiv.className = 'alert alert-' + type;
    alertDiv.innerHTML = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.style.minWidth = '300px';

    document.body.appendChild(alertDiv);

    setTimeout(function() {
        alertDiv.style.opacity = '0';
        setTimeout(function() {
            if (alertDiv && alertDiv.remove) alertDiv.remove();
        }, 500);
    }, 3000);
}

// Загрузка файла
function uploadFile(file) {
    if (!file) return;

    // Проверяем тип файла
    var allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    var allowedVideoTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-flv', 'video/webm'];
    var allowedTypes = allowedImageTypes.concat(allowedVideoTypes);

    if (!allowedTypes.includes(file.type)) {
        showError('Пожалуйста, выберите изображение (JPG, PNG, GIF, WEBP) или видео (MP4, MOV, AVI, MKV, WEBM)');
        return;
    }

    if (file.size > maxFileSize) {
        showError('Размер файла не должен превышать 100 MB');
        return;
    }

    hideError();
    if (progress) progress.style.display = 'block';

    var formData = new FormData();
    formData.append('imageFile', file);
    formData.append('_csrf', csrfToken);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', addImageUrl, true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            var percent = Math.round((e.loaded / e.total) * 100);
            var progressBar = progress.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = percent + '%';
                progressBar.textContent = 'Загрузка... ' + percent + '%';
            }
        }
    };

    xhr.onload = function() {
        if (progress) {
            progress.style.display = 'none';
            var progressBar = progress.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = '100%';
                progressBar.textContent = 'Загрузка...';
            }
        }

        if (xhr.status === 200) {
            try {
                var response = JSON.parse(xhr.responseText);
                if (response.success) {
                    addFileToContainer(response);
                    showNotification('success', 'Файл успешно загружен');
                } else {
                    showError(response.error || 'Ошибка загрузки');
                }
            } catch(e) {
                console.error('Parse error:', e);
                console.error('Response:', xhr.responseText);
                showError('Ошибка обработки ответа сервера');
            }
        } else {
            showError('Ошибка сервера (статус ' + xhr.status + ')');
        }
    };

    xhr.onerror = function() {
        if (progress) progress.style.display = 'none';
        showError('Ошибка соединения с сервером');
    };

    xhr.send(formData);
}

// Добавление файла в контейнер (изображение или видео)
function addFileToContainer(response) {
    var imageHtml = '';
    var isVideo = response.isVideo || false;
    var displayUrl = response.thumbnailUrl;

    if (isCreateMode) {
        var contentHtml = '';
        if (isVideo) {
            contentHtml = `
                <div style="position: relative; width: 100%; height: 100px; overflow: hidden; background: #000;">
                    <img src="${displayUrl}" alt="Video preview" style="width: 100%; height: 100%; object-fit: cover;"
                         onerror="this.style.display='none'; this.parentElement.style.background='#222';">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 30px; opacity: 0.8;">
                        <span class="glyphicon glyphicon-play"></span>
                    </div>
                    <div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.7); color: white; font-size: 11px; padding: 2px 6px; border-radius: 3px;">
                        Видео
                    </div>
                </div>
            `;
        } else {
            contentHtml = `<img src="${displayUrl}" alt="Image" class="img-responsive" style="height: 100px; width: 100%; object-fit: cover;"
                                 onerror="this.src=''; this.parentElement.style.background='#f0f0f0';">`;
        }

        imageHtml = `
            <div class="col-md-3 col-sm-4 col-xs-6" data-image-index="${response.index}">
                <div class="thumbnail" style="position: relative;">
                    ${contentHtml}
                    <div class="caption" style="padding: 5px;">
                        <button type="button" class="btn btn-danger btn-sm btn-block delete-image-btn"
                                data-id="${response.index}" data-type="create">
                            Удалить
                        </button>
                    </div>
                </div>
            </div>
        `;
    } else {
        var contentHtml = '';
        if (isVideo) {
            contentHtml = `
                <div style="position: relative; width: 100%; height: 100px; overflow: hidden; background: #000;">
                    <img src="${displayUrl}" alt="Video preview" style="width: 100%; height: 100%; object-fit: cover;"
                         onerror="this.style.display='none'; this.parentElement.style.background='#222';">
                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 30px; opacity: 0.8;">
                        <span class="glyphicon glyphicon-play"></span>
                    </div>
                    <div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.7); color: white; font-size: 11px; padding: 2px 6px; border-radius: 3px;">
                        Видео
                    </div>
                </div>
            `;
        } else {
            contentHtml = `<img src="${displayUrl}" alt="Image" class="img-responsive" style="height: 100px; width: 100%; object-fit: cover;"
                                 onerror="this.src=''; this.parentElement.style.background='#f0f0f0';">`;
        }

        imageHtml = `
            <div class="col-md-3 col-sm-4 col-xs-6" data-image-id="${response.imageId}">
                <div class="thumbnail" style="position: relative;">
                    ${contentHtml}
                    <div class="caption" style="padding: 5px;">
                        <button type="button" class="btn btn-danger btn-sm btn-block delete-image-btn"
                                data-id="${response.imageId}" data-type="update">
                            Удалить
                        </button>
                    </div>
                    <div class="sort-handle" style="position: absolute; top: 5px; right: 5px; background: rgba(0,0,0,0.5); color: white; padding: 2px 5px; border-radius: 3px; cursor: move;">
                        <span class="glyphicon glyphicon-move"></span>
                    </div>
                </div>
            </div>
        `;
    }

    if (imagesContainer) {
        imagesContainer.insertAdjacentHTML('beforeend', imageHtml);
    }

    if (noImagesMessage) {
        noImagesMessage.style.display = 'none';
    }

    attachDeleteHandlers();

    if (!isCreateMode) {
        initSortable();
    }
}

// Удаление изображения
function attachDeleteHandlers() {
    var deleteButtons = document.querySelectorAll('.delete-image-btn');
    for (var i = 0; i < deleteButtons.length; i++) {
        var btn = deleteButtons[i];
        btn.removeEventListener('click', deleteImageHandler);
        btn.addEventListener('click', deleteImageHandler);
    }
}

function deleteImageHandler(e) {
    var id = this.getAttribute('data-id');
    var type = this.getAttribute('data-type');
    var url = deleteImageUrl;

    if (type === 'create') {
        url = deleteImageUrl + '?tempId=' + <?= $id ?> + '&index=' + id;
    } else {
        url = deleteImageUrl + '?id=' + id;
    }

    if (confirm('Вы уверены, что хотите удалить этот файл?')) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    var response = JSON.parse(xhr.responseText);
                    if (response.success) {
                        var selector = (type === 'create') ? '[data-image-index="' + id + '"]' : '[data-image-id="' + id + '"]';
                        var imageElement = document.querySelector(selector);
                        if (imageElement) {
                            imageElement.remove();
                        }

                        if (imagesContainer && imagesContainer.children.length === 0 && noImagesMessage) {
                            noImagesMessage.style.display = 'block';
                        }

                        showNotification('success', response.message);
                    } else {
                        showError(response.error || 'Ошибка удаления');
                    }
                } catch(e) {
                    showError('Ошибка обработки ответа');
                }
            }
        };

        xhr.send('_csrf=' + encodeURIComponent(csrfToken));
    }
}

// Сортировка изображений
function initSortable() {
    if (typeof $ !== 'undefined' && $.fn.sortable !== 'undefined' && imagesContainer) {
        $(imagesContainer).sortable({
            handle: '.sort-handle',
            update: function(event, ui) {
                var orders = [];
                $(imagesContainer).children().each(function(index) {
                    orders.push({
                        id: $(this).data('image-id'),
                        position: index
                    });
                });

                $.ajax({
                    url: reorderUrl,
                    type: 'POST',
                    data: {
                        orders: orders,
                        _csrf: csrfToken
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Order updated');
                        }
                    },
                    error: function() {
                        showNotification('warning', 'Ошибка при сохранении порядка');
                    }
                });
            }
        });
    }
}

// Drag & Drop обработчики
var dragCounter = 0;

function handleDragEnter(e) {
    e.preventDefault();
    e.stopPropagation();

    var hasFiles = false;
    if (e.dataTransfer.types) {
        for (var i = 0; i < e.dataTransfer.types.length; i++) {
            if (e.dataTransfer.types[i] === 'Files') {
                hasFiles = true;
                break;
            }
        }
    }

    if (hasFiles) {
        dragCounter++;
        if (dragDropZone) {
            dragDropZone.style.borderColor = '#5bc0de';
            dragDropZone.style.backgroundColor = '#f0f8ff';
            dragDropZone.classList.add('drag-over');
        }
    }
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    if (e.dataTransfer) {
        e.dataTransfer.dropEffect = 'copy';
    }
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();

    dragCounter--;

    if (dragCounter === 0 && dragDropZone) {
        dragDropZone.style.borderColor = '#ccc';
        dragDropZone.style.backgroundColor = 'transparent';
        dragDropZone.classList.remove('drag-over');
    }
}

function handleDrop(e) {
    e.preventDefault();
    e.stopPropagation();

    dragCounter = 0;

    if (dragDropZone) {
        dragDropZone.style.borderColor = '#ccc';
        dragDropZone.style.backgroundColor = 'transparent';
        dragDropZone.classList.remove('drag-over');
    }

    var files = e.dataTransfer.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
}

// Выбор файла через input
function handleFileSelect(e) {
    var files = e.target.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
        e.target.value = '';
    }
}

// Регистрация обработчиков
if (dragDropZone) {
    dragDropZone.addEventListener('dragenter', handleDragEnter);
    dragDropZone.addEventListener('dragover', handleDragOver);
    dragDropZone.addEventListener('dragleave', handleDragLeave);
    dragDropZone.addEventListener('drop', handleDrop);
    dragDropZone.style.cursor = 'default';
}

if (fileInput) {
    fileInput.addEventListener('change', handleFileSelect);
}

// Убираем CSS-эффект наведения
if (dragDropZone) {
    dragDropZone.style.transition = 'border-color 0.3s ease, background-color 0.3s ease';
}

// Инициализация
attachDeleteHandlers();
if (!isCreateMode) {
    initSortable();
}
</script>