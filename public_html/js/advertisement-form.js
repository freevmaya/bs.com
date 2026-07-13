/**
 * Advertisement Form - управление формой объявлений
 * Включает: динамические поля, валидацию, загрузку изображений
 */

(function($) {
    'use strict';

    /**
     * Инициализация формы объявления
     */
    function initAdvertisementForm() {
        var $form = $('#advertisement-form');
        if (!$form.length) return;

        // Инициализация переключения полей типа
        initTypeFields();

        // Инициализация переключения раздела
        initSectionToggle();

        // Инициализация AJAX валидации
        initAjaxValidation($form);

        // Инициализация загрузки изображений
        initImageUpload();
    }

    /**
     * Инициализация динамических полей в зависимости от типа
     */
    function initTypeFields() {
        var $typeSelect = $('#type-select');
        if (!$typeSelect.length) return;

        var $gliderFields = $('#glider-fields');
        var $harnessFields = $('#harness-fields');
        var $deviceFields = $('#device-fields');

        function toggleTypeFields() {
            var selectedType = $typeSelect.val();

            $gliderFields.hide();
            $harnessFields.hide();
            $deviceFields.hide();

            if (selectedType === 'glider') {
                $gliderFields.show();
            } else if (selectedType === 'harness') {
                $harnessFields.show();
            } else if (selectedType === 'device') {
                $deviceFields.show();
            }
        }

        $typeSelect.on('change', toggleTypeFields);
        toggleTypeFields();
    }

    /**
     * Инициализация переключения блока изображений
     */
    function initSectionToggle() {
        var $sectionSelect = $('#section-select');
        if (!$sectionSelect.length) return;

        var $imagesBlock = $('#images-block');
        if (!$imagesBlock.length) return;

        function toggleImagesBlock() {
            if ($sectionSelect.val() === 'sell') {
                $imagesBlock.show();
            } else {
                $imagesBlock.hide();
            }
        }

        $sectionSelect.on('change', toggleImagesBlock);
        toggleImagesBlock();
    }

    /**
     * Инициализация AJAX валидации
     */
    function initAjaxValidation($form) {
        var $submitBtn = $form.find('[type="submit"]');
        var originalText = $submitBtn.text();

        $form.on('submit', function(e) {
            e.preventDefault();

            var formData = new FormData(this);

            $submitBtn.prop('disabled', true);
            $submitBtn.text('Проверка...');

            $.ajax({
                url: window.location.href + '?validate=1',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.success) {
                        $form.off('submit').submit();
                        return;
                    }

                    // Показываем ошибки
                    if (response.errors) {
                        $.each(response.errors, function(field, message) {
                            showNotification(message, 'danger');
                        });
                    } else if (response.message) {
                        showNotification(response.message, 'danger');
                    } else {
                        showNotification('Пожалуйста, заполните все обязательные поля', 'danger');
                    }

                    // Подсвечиваем поля с ошибками
                    if (response.invalidFields) {
                        $.each(response.invalidFields, function(index, fieldName) {
                            var $field = $('[name="' + fieldName + '"]');
                            if ($field.length) {
                                $field.css({
                                    borderColor: '#dc3545',
                                    backgroundColor: '#fff8f8'
                                }).one('focus', function() {
                                    $(this).css({
                                        borderColor: '',
                                        backgroundColor: ''
                                    });
                                });
                            }
                        });

                        // Прокручиваем к первому полю с ошибкой
                        var $firstError = $('[style*="border-color: rgb(220, 53, 69)"]').first();
                        if ($firstError.length) {
                            $('html, body').animate({
                                scrollTop: $firstError.offset().top - 100
                            }, 300);
                        }
                    }

                    $submitBtn.prop('disabled', false);
                    $submitBtn.text(originalText);
                },
                error: function() {
                    $form.off('submit').submit();
                }
            });
        });
    }

    /**
     * Инициализация загрузки изображений
     */
    function initImageUpload() {
        var $dragDropZone = $('#drag-drop-zone');
        var $fileInput = $('#image-file-input');
        var $progress = $('#upload-progress');
        var $uploadError = $('#upload-error');
        var $errorMessage = $('#error-message');
        var $imagesContainer = $('#images-container');
        var $noImagesMessage = $('#no-images-message');

        if (!$dragDropZone.length) return;

        var csrfToken = $('meta[name="csrf-token"]').attr('content') || $('[name="_csrf"]').val();
        var isCreateMode = $fileInput.data('type') === 'create';
        var addImageUrl = $dragDropZone.data('upload-url') || $fileInput.data('upload-url');

        // Проверяем, определен ли ImageSortable
        var hasImageSortable = typeof ImageSortable !== 'undefined';

        // Drag & Drop обработчики
        var dragCounter = 0;

        function handleDragEnter(e) {
            e.preventDefault();
            e.stopPropagation();

            var hasFiles = false;
            if (e.originalEvent.dataTransfer.types) {
                for (var i = 0; i < e.originalEvent.dataTransfer.types.length; i++) {
                    if (e.originalEvent.dataTransfer.types[i] === 'Files') {
                        hasFiles = true;
                        break;
                    }
                }
            }

            if (hasFiles) {
                dragCounter++;
                $dragDropZone.addClass('drag-over');
            }
        }

        function handleDragOver(e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.originalEvent.dataTransfer) {
                e.originalEvent.dataTransfer.dropEffect = 'copy';
            }
        }

        function handleDragLeave(e) {
            e.preventDefault();
            e.stopPropagation();
            dragCounter--;
            if (dragCounter === 0) {
                $dragDropZone.removeClass('drag-over');
            }
        }

        function handleDrop(e) {
            e.preventDefault();
            e.stopPropagation();

            dragCounter = 0;
            $dragDropZone.removeClass('drag-over');

            var files = e.originalEvent.dataTransfer.files;
            if (files && files.length > 0) {
                for (var i = 0; i < files.length; i++) {
                    uploadFile(files[i]);
                }
            }
        }

        $dragDropZone.on('dragenter', handleDragEnter);
        $dragDropZone.on('dragover', handleDragOver);
        $dragDropZone.on('dragleave', handleDragLeave);
        $dragDropZone.on('drop', handleDrop);

        // Выбор файлов через input
        $fileInput.on('change', function(e) {
            var files = e.target.files;
            if (files && files.length > 0) {
                for (var i = 0; i < files.length; i++) {
                    uploadFile(files[i]);
                }
                $(this).val('');
            }
        });

        // Удаление изображений
        $(document).on('click', '.delete-image-btn', function(e) {
            var $btn = $(this);
            var id = $btn.data('id');
            var type = $btn.data('type');
            var url = $btn.closest('[data-delete-url]').data('delete-url');

            if (type === 'create') {
                url = url + '?tempId=' + window.tempId + '&index=' + id;
            } else {
                url = url + '?id=' + id;
            }

            if (!confirm('Вы уверены, что хотите удалить этот файл?')) return;

            $.ajax({
                url: url,
                type: 'POST',
                data: { _csrf: csrfToken },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        var selector = (type === 'create')
                            ? '[data-image-index="' + id + '"]'
                            : '[data-image-id="' + id + '"]';
                        var $item = $(selector);

                        if ($item.length) {
                            $item.remove();

                            // Обновляем номера
                            if (hasImageSortable) {
                                ImageSortable.updateNumbers($imagesContainer);
                                if (!isCreateMode) {
                                    ImageSortable.saveOrder($imagesContainer, '', csrfToken);
                                }
                            } else {
                                updateOrderNumbersLegacy();
                            }
                        }

                        if ($imagesContainer.children().length === 0) {
                            $noImagesMessage.show();
                        }

                        showNotification(response.message || 'Файл удален', 'success');
                    } else {
                        showError(response.error || 'Ошибка удаления');
                    }
                },
                error: function() {
                    showError('Ошибка соединения с сервером');
                }
            });
        });

        /**
         * Загрузка файла
         */
        function uploadFile(file) {
            var allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            var allowedVideoTypes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/x-flv', 'video/webm'];
            var allowedTypes = allowedImageTypes.concat(allowedVideoTypes);

            if (!allowedTypes.includes(file.type)) {
                showError('Пожалуйста, выберите изображение или видео');
                return;
            }

            var maxFileSize = $fileInput.data('max-size') || 100 * 1024 * 1024;
            if (file.size > maxFileSize) {
                showError('Размер файла не должен превышать ' + (maxFileSize / (1024 * 1024)) + ' MB');
                return;
            }

            hideError();
            $progress.show();

            var formData = new FormData();
            formData.append('imageFile', file);
            formData.append('_csrf', csrfToken);

            $.ajax({
                url: addImageUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = Math.round((e.loaded / e.total) * 100);
                            var $progressBar = $progress.find('.progress-bar');
                            $progressBar.css('width', percent + '%');
                            $progressBar.text('Загрузка... ' + percent + '%');
                        }
                    });
                    return xhr;
                },
                success: function(response) {
                    $progress.hide();
                    var $progressBar = $progress.find('.progress-bar');
                    $progressBar.css('width', '100%');
                    $progressBar.text('Загрузка...');

                    if (response.success) {
                        addFileToContainer(response);
                        showNotification('Файл успешно загружен', 'success');
                    } else {
                        showError(response.error || 'Ошибка загрузки');
                    }
                },
                error: function() {
                    $progress.hide();
                    showError('Ошибка соединения с сервером');
                }
            });
        }

        /**
         * Добавление файла в контейнер
         */
        function addFileToContainer(response) {
            var isVideo = response.isVideo || false;
            var displayUrl = response.thumbnailUrl;
            var currentItems = $imagesContainer.find('.sortable-item');
            var newOrder = currentItems.length;

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

            var extraAttrs = '';
            var dataIdAttr = '';
            var moveIndicator = '';

            if (isCreateMode) {
                dataIdAttr = 'data-image-index="' + response.index + '"';
            } else {
                dataIdAttr = 'data-image-id="' + response.imageId + '"';
                moveIndicator = `
                    <div style="position: absolute; bottom: 35px; right: 5px; background: rgba(0,0,0,0.5); color: white; padding: 2px 8px; border-radius: 10px; font-size: 9px; pointer-events: none;">
                        <span class="glyphicon glyphicon-move"></span>
                    </div>
                `;
            }

            var itemHtml = `
                <div class="col-md-3 col-sm-4 col-xs-6 sortable-item" ${dataIdAttr} data-sort-order="${newOrder}">
                    <div class="thumbnail" style="position: relative;">
                        ${contentHtml}
                        <div class="caption" style="padding: 5px;">
                            <button type="button" class="btn btn-danger btn-sm btn-block delete-image-btn"
                                    data-id="${isCreateMode ? response.index : response.imageId}"
                                    data-type="${isCreateMode ? 'create' : 'update'}">
                                <span class="glyphicon glyphicon-trash"></span> Удалить
                            </button>
                        </div>
                        <div class="sort-order-badge">
                            #<span class="order-number">${newOrder + 1}</span>
                        </div>
                        ${moveIndicator}
                    </div>
                </div>
            `;

            $imagesContainer.append(itemHtml);
            $noImagesMessage.hide();

            // Обновляем номера
            if (hasImageSortable) {
                ImageSortable.updateNumbers($imagesContainer);
            } else {
                updateOrderNumbersLegacy();
            }
        }

        /**
         * Обновление номеров (legacy)
         */
        function updateOrderNumbersLegacy() {
            $imagesContainer.find('.sortable-item').each(function(index) {
                var $item = $(this);
                var $orderNumber = $item.find('.order-number');
                if ($orderNumber.length) {
                    $orderNumber.text(index + 1);
                }
                $item.attr('data-sort-order', index);
            });
        }

        function showError(message) {
            $errorMessage.text(message);
            $uploadError.show();
            setTimeout(function() {
                $uploadError.hide();
            }, 5000);
        }

        function hideError() {
            $uploadError.hide();
            $errorMessage.text('');
        }
    }

    /**
     * Показать уведомление
     */
    function showNotification(message, type) {
        if (typeof window.showNotification === 'function') {
            window.showNotification(message, type);
            return;
        }

        // Fallback - используем alert если showNotification не доступен
        alert(message);
    }

    // Инициализация при загрузке
    $(document).ready(function() {
        initAdvertisementForm();
    });

})(jQuery);