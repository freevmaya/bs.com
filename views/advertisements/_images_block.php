<?php

use yii\helpers\Html;
use yii\helpers\Url;
use app\models\AdvertisementImage;

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
    $reorderUrl = Url::to(['advertisements/reorder-temp-images', 'tempId' => $id]);
    $containerData = 'data-image-sortable="true" data-reorder-url="' . $reorderUrl . '"';
} else {
    $addImageUrl = Url::to(['advertisements/add-image', 'id' => $id]);
    $deleteImageUrl = Url::to(['advertisements/delete-image-ajax']);
    $imageDataAttribute = 'data-image-id';
    $imageIdKey = 'imageId';
    $reorderUrl = Url::to(['advertisements/reorder-images']);
    $containerData = 'data-image-sortable="true" data-reorder-url="' . $reorderUrl . '"';
}

// Регистрируем CSS и JS
$this->registerCssFile('@web/css/image-sortable.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/image-sortable.js', [
    'depends' => [\yii\web\JqueryAsset::class, \yii\jui\JuiAsset::class],
    'position' => \yii\web\View::POS_END
]);

// Передаем параметры в JS
$jsOptions = [
    'csrfToken' => Yii::$app->request->csrfToken,
    'reorderUrl' => $reorderUrl,
];
$this->registerJs(
    'if (typeof ImageSortable !== "undefined") { ' .
    'ImageSortable.initAll(' . json_encode($jsOptions) . '); ' .
    '}',
    \yii\web\View::POS_READY
);

?>
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">
            <span class="glyphicon glyphicon-picture"></span> 
            Фотографии и видео товара
        </h3>
        <?php if ($isUpdate): ?>
            <p class="help-block" style="margin-top: 12px; font-size: 12px; color: #888;">
                <span class="glyphicon glyphicon-info-sign"></span> 
                <strong>Перетащите</strong> превьюшку мышкой для изменения порядка.
            </p>
        <?php endif; ?>
    </div>
    <div class="panel-body">
        <!-- Контейнер для изображений -->
        <div id="images-container" class="row sortable-container" <?= $containerData ?> data-csrf-token="<?= Yii::$app->request->csrfToken ?>">
            <?php if (!empty($images)): ?>
                <?php foreach ($images as $index => $image): ?>
                    <div class="col-md-3 col-sm-4 col-xs-6 sortable-item" <?= $imageDataAttribute ?>="<?= $isCreate ? $index : $image->id ?>" data-sort-order="<?= $index ?>">
                        <div class="thumbnail" style="position: relative;">
                            <?php
                            $isVideo = false;
                            if ($isCreate) {
                                $ext = pathinfo($image['file_name'], PATHINFO_EXTENSION);
                                $videoExts = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
                                $isVideo = in_array(strtolower($ext), $videoExts);
                            } else {
                                $isVideo = $image->isVideo();
                            }
                            ?>
                            <?php if ($isVideo): ?>
                                <div style="position: relative; width: 100%; height: 120px; overflow: hidden; background: #000;">
                                    <?php
                                    $thumbUrl = $isCreate
                                        ? Url::to('@web/uploads/advertisements/' . $image['thumbnail_path'])
                                        : $image->getThumbnailUrl();
                                    ?>
                                    <img src="<?= $thumbUrl ?>" alt="Video preview" style="width: 100%; height: 100%; object-fit: cover; cursor: grab;"
                                         onerror="this.style.display='none'; this.parentElement.style.background='#222';">
                                    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 36px; opacity: 0.85; text-shadow: 0 0 20px rgba(0,0,0,0.8); pointer-events: none;">
                                        <span class="glyphicon glyphicon-play"></span>
                                    </div>
                                    <div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.75); color: white; font-size: 10px; padding: 2px 10px; border-radius: 10px; font-weight: 600; pointer-events: none;">
                                        <span class="glyphicon glyphicon-film"></span> Видео
                                    </div>
                                </div>
                            <?php else: ?>
                                <img src="<?= $isCreate ? Url::to('@web/uploads/advertisements/' . $image['thumbnail_path']) : $image->getThumbnailUrl() ?>"
                                     alt="Image" class="img-responsive" style="height: 120px; width: 100%; object-fit: cover; cursor: grab;"
                                     onerror="this.src=''; this.parentElement.style.background='#f0f0f0';">
                            <?php endif; ?>
                            <div class="caption" style="padding: 5px;">
                                <button type="button" class="btn btn-danger btn-sm btn-block delete-image-btn"
                                        data-id="<?= $isCreate ? $index : $image->id ?>"
                                        data-type="<?= $type ?>">
                                    <span class="glyphicon glyphicon-trash"></span> Удалить
                                </button>
                            </div>
                            
                            <!-- Бейдж с номером (без отдельной рукоятки) -->
                            <div class="sort-order-badge">
                                #<span class="order-number"><?= $index + 1 ?></span>
                            </div>
                            
                            <?php if ($isUpdate): ?>
                                <div style="position: absolute; bottom: 35px; right: 5px; background: rgba(0,0,0,0.5); color: white; padding: 2px 8px; border-radius: 10px; font-size: 9px; pointer-events: none;">
                                    <span class="glyphicon glyphicon-move"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Сообщение при отсутствии изображений -->
        <div id="no-images-message" class="text-muted text-center" style="<?= !empty($images) ? 'display: none;' : '' ?> padding: 40px 20px; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
            <p><span class="glyphicon glyphicon-picture" style="font-size: 40px; color: #ddd; display: block; margin-bottom: 10px;"></span></p>
            <p style="font-size: 16px;">Нет загруженных файлов</p>
            <p style="font-size: 13px; color: #999;">Загрузите изображения или видео, используя форму ниже</p>
        </div>

        <!-- Drag & Drop зона + выбор файла -->
        <div id="drag-drop-zone" class="drag-drop-zone">
            <div class="drag-drop-content">
                <span class="glyphicon glyphicon-cloud-upload" style="font-size: 52px; color: #999; display: block; margin-bottom: 10px; transition: color 0.3s ease;"></span>
                <h4 style="margin-bottom: 5px;">Перетащите файлы сюда</h4>
                <p class="text-muted" style="margin-bottom: 5px;">Поддерживаются изображения (JPG, PNG, GIF, WEBP) и видео (MP4, MOV, AVI, MKV, WEBM)</p>
                <p class="text-muted" style="font-size: 12px; margin-bottom: 10px;">Максимальный размер файла: <?= AdvertisementImage::MAX_VIDEO_SIZE_MGB ?> MB</p>
                <p class="text-muted" style="margin-bottom: 10px;">или</p>
                <label class="btn btn-primary btn-file">
                    <span class="glyphicon glyphicon-folder-open"></span> Выберите файлы
                    <input type="file" accept="image/*,video/*" id="image-file-input" data-type="<?= $type ?>" style="display: none;" multiple>
                </label>
            </div>
        </div>

        <!-- Прогресс загрузки -->
        <div id="upload-progress" style="display: none; margin-top: 15px;">
            <div class="progress" style="height: 30px; border-radius: 6px;">
                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%; font-weight: 600; font-size: 14px;">
                    <span class="glyphicon glyphicon-upload"></span> Загрузка...
                </div>
            </div>
        </div>

        <!-- Ошибка загрузки -->
        <div id="upload-error" class="alert alert-danger" style="display: none; margin-top: 10px; border-radius: 8px;">
            <span class="glyphicon glyphicon-exclamation-sign"></span>
            <span id="error-message"></span>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var csrfToken = '<?= Yii::$app->request->csrfToken ?>';
    var isCreateMode = <?= $isCreate ? 'true' : 'false' ?>;
    var addImageUrl = '<?= $addImageUrl ?>';
    var deleteImageUrl = '<?= $deleteImageUrl ?>';
    var maxFileSize = <?= AdvertisementImage::MAX_VIDEO_SIZE ?>;

    // DOM элементы
    var dragDropZone = document.getElementById('drag-drop-zone');
    var fileInput = document.getElementById('image-file-input');
    var progress = document.getElementById('upload-progress');
    var uploadError = document.getElementById('upload-error');
    var errorMessage = document.getElementById('error-message');
    var imagesContainer = document.getElementById('images-container');
    var noImagesMessage = document.getElementById('no-images-message');

    // Вспомогательные функции
    function hideError() {
        if (uploadError) {
            uploadError.style.display = 'none';
            if (errorMessage) errorMessage.textContent = '';
        }
    }

    function showError(message) {
        if (uploadError && errorMessage) {
            errorMessage.textContent = message;
            uploadError.style.display = 'block';
            setTimeout(function() {
                uploadError.style.display = 'none';
            }, 5000);
        }
    }

    function showNotification(type, message) {
        var alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-' + type;
        alertDiv.innerHTML = message;
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.minWidth = '300px';
        alertDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        alertDiv.style.borderRadius = '8px';

        document.body.appendChild(alertDiv);

        setTimeout(function() {
            alertDiv.style.opacity = '0';
            alertDiv.style.transition = 'opacity 0.5s ease';
            setTimeout(function() {
                if (alertDiv && alertDiv.remove) alertDiv.remove();
            }, 500);
        }, 3000);
    }

    // Загрузка файла
    function uploadFile(file) {
        if (!file) return;

        var allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        var allowedVideoTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-flv', 'video/webm'];
        var allowedTypes = allowedImageTypes.concat(allowedVideoTypes);

        if (!allowedTypes.includes(file.type)) {
            showError('Пожалуйста, выберите изображение (JPG, PNG, GIF, WEBP) или видео (MP4, MOV, AVI, MKV, WEBM)');
            return;
        }

        if (file.size > maxFileSize) {
            showError('Размер файла не должен превышать <?= AdvertisementImage::MAX_VIDEO_SIZE_MGB ?> MB');
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

    // Добавление файла в контейнер
    function addFileToContainer(response) {
        var imageHtml = '';
        var isVideo = response.isVideo || false;
        var displayUrl = response.thumbnailUrl;

        if (isCreateMode) {
            var contentHtml = '';
            if (isVideo) {
                contentHtml = `
                    <div style="position: relative; width: 100%; height: 120px; overflow: hidden; background: #000;">
                        <img src="${displayUrl}" alt="Video preview" style="width: 100%; height: 100%; object-fit: cover; cursor: grab;"
                             onerror="this.style.display='none'; this.parentElement.style.background='#222';">
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 36px; opacity: 0.85; text-shadow: 0 0 20px rgba(0,0,0,0.8); pointer-events: none;">
                            <span class="glyphicon glyphicon-play"></span>
                        </div>
                        <div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.75); color: white; font-size: 10px; padding: 2px 10px; border-radius: 10px; font-weight: 600; pointer-events: none;">
                            <span class="glyphicon glyphicon-film"></span> Видео
                        </div>
                    </div>
                `;
            } else {
                contentHtml = `<img src="${displayUrl}" alt="Image" class="img-responsive" style="height: 120px; width: 100%; object-fit: cover; cursor: grab;"
                                     onerror="this.src=''; this.parentElement.style.background='#f0f0f0';">`;
            }

            var currentItems = document.querySelectorAll('.sortable-item');
            var newOrder = currentItems.length;

            imageHtml = `
                <div class="col-md-3 col-sm-4 col-xs-6 sortable-item" data-image-index="${response.index}" data-sort-order="${newOrder}">
                    <div class="thumbnail" style="position: relative;">
                        ${contentHtml}
                        <div class="caption" style="padding: 5px;">
                            <button type="button" class="btn btn-danger btn-sm btn-block delete-image-btn"
                                    data-id="${response.index}" data-type="create">
                                <span class="glyphicon glyphicon-trash"></span> Удалить
                            </button>
                        </div>
                        <div class="sort-order-badge">
                            #${newOrder + 1}
                        </div>
                    </div>
                </div>
            `;
        } else {
            var contentHtml = '';
            if (isVideo) {
                contentHtml = `
                    <div style="position: relative; width: 100%; height: 120px; overflow: hidden; background: #000;">
                        <img src="${displayUrl}" alt="Video preview" style="width: 100%; height: 100%; object-fit: cover; cursor: grab;"
                             onerror="this.style.display='none'; this.parentElement.style.background='#222';">
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 36px; opacity: 0.85; text-shadow: 0 0 20px rgba(0,0,0,0.8); pointer-events: none;">
                            <span class="glyphicon glyphicon-play"></span>
                        </div>
                        <div style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.75); color: white; font-size: 10px; padding: 2px 10px; border-radius: 10px; font-weight: 600; pointer-events: none;">
                            <span class="glyphicon glyphicon-film"></span> Видео
                        </div>
                    </div>
                `;
            } else {
                contentHtml = `<img src="${displayUrl}" alt="Image" class="img-responsive" style="height: 120px; width: 100%; object-fit: cover; cursor: grab;"
                                     onerror="this.src=''; this.parentElement.style.background='#f0f0f0';">`;
            }

            var currentItems = document.querySelectorAll('.sortable-item');
            var newOrder = currentItems.length;

            imageHtml = `
                <div class="col-md-3 col-sm-4 col-xs-6 sortable-item" data-image-id="${response.imageId}" data-sort-order="${newOrder}">
                    <div class="thumbnail" style="position: relative;">
                        ${contentHtml}
                        <div class="caption" style="padding: 5px;">
                            <button type="button" class="btn btn-danger btn-sm btn-block delete-image-btn"
                                    data-id="${response.imageId}" data-type="update">
                                <span class="glyphicon glyphicon-trash"></span> Удалить
                            </button>
                        </div>
                        <div class="sort-order-badge">
                            #<span class="order-number">${newOrder + 1}</span>
                        </div>
                        <div style="position: absolute; bottom: 35px; right: 5px; background: rgba(0,0,0,0.5); color: white; padding: 2px 8px; border-radius: 10px; font-size: 9px; pointer-events: none;">
                            <span class="glyphicon glyphicon-move"></span>
                        </div>
                    </div>
                </div>
            `;
        }

        if (imagesContainer) {
            imagesContainer.insertAdjacentHTML('beforeend', imageHtml);
            
            // Обновляем номера через внешнюю функцию, если доступна
            if (typeof ImageSortable !== 'undefined') {
                ImageSortable.updateNumbers($(imagesContainer));
            } else {
                updateOrderNumbersLegacy();
            }
        }

        if (noImagesMessage) {
            noImagesMessage.style.display = 'none';
        }

        attachDeleteHandlers();
    }

    // Legacy обновление номеров (если ImageSortable не загружен)
    function updateOrderNumbersLegacy() {
        var items = document.querySelectorAll('.sortable-item');
        items.forEach(function(item, index) {
            var orderNumber = item.querySelector('.order-number');
            if (orderNumber) {
                orderNumber.textContent = index + 1;
            }
            item.dataset.sortOrder = index;
        });
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
                                
                                // Обновляем номера
                                if (typeof ImageSortable !== 'undefined') {
                                    ImageSortable.updateNumbers($(imagesContainer));
                                    // Сохраняем порядок после удаления
                                    if (!isCreateMode) {
                                        ImageSortable.saveOrder(
                                            $(imagesContainer),
                                            '<?= $reorderUrl ?>',
                                            csrfToken
                                        );
                                    }
                                } else {
                                    updateOrderNumbersLegacy();
                                }
                            }

                            if (imagesContainer && imagesContainer.children.length === 0 && noImagesMessage) {
                                noImagesMessage.style.display = 'block';
                            }

                            showNotification('success', response.message || 'Файл удален');
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
            dragDropZone.classList.remove('drag-over');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();

        dragCounter = 0;

        if (dragDropZone) {
            dragDropZone.classList.remove('drag-over');
        }

        var files = e.dataTransfer.files;
        if (files && files.length > 0) {
            for (var i = 0; i < files.length; i++) {
                uploadFile(files[i]);
            }
        }
    }

    // Выбор файла через input
    function handleFileSelect(e) {
        var files = e.target.files;
        if (files && files.length > 0) {
            for (var i = 0; i < files.length; i++) {
                uploadFile(files[i]);
            }
            e.target.value = '';
        }
    }

    // Регистрация обработчиков
    if (dragDropZone) {
        dragDropZone.addEventListener('dragenter', handleDragEnter);
        dragDropZone.addEventListener('dragover', handleDragOver);
        dragDropZone.addEventListener('dragleave', handleDragLeave);
        dragDropZone.addEventListener('drop', handleDrop);
    }

    if (fileInput) {
        fileInput.addEventListener('change', handleFileSelect);
    }

    // Инициализация
    attachDeleteHandlers();

})();
</script>