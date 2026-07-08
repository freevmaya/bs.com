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
    $reorderUrl = Url::to(['advertisements/reorder-temp-images', 'tempId' => $id]);
    $containerData = 'data-image-sortable="true" data-reorder-url="' . $reorderUrl . '" data-csrf-token="' . Yii::$app->request->csrfToken . '"';
} else {
    $addImageUrl = Url::to(['advertisements/add-image', 'id' => $id]);
    $reorderUrl = Url::to(['advertisements/reorder-images']);
    $containerData = 'data-image-sortable="true" data-reorder-url="' . $reorderUrl . '" data-csrf-token="' . Yii::$app->request->csrfToken . '"';
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
        <div id="images-container" class="row sortable-container" <?= $containerData ?>>
            <?php if (!empty($images)): ?>
                <?php foreach ($images as $index => $image): ?>
                    <div class="col-md-3 col-sm-4 col-xs-6 sortable-item" <?= $isCreate ? 'data-image-index="' . $index . '"' : 'data-image-id="' . $image->id . '"' ?> data-sort-order="<?= $index ?>">
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
                            
                            <!-- Бейдж с номером -->
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

        <!-- Drag & Drop зона + выбор файла -->
        <div id="drag-drop-zone" class="drag-drop-zone" data-upload-url="<?= $addImageUrl ?>">
            <div class="drag-drop-content">
                <span class="glyphicon glyphicon-cloud-upload" style="font-size: 52px; color: #999; display: block; margin-bottom: 10px; transition: color 0.3s ease;"></span>
                <h4 style="margin-bottom: 5px;">Перетащите файлы сюда</h4>
                <p class="text-muted" style="margin-bottom: 5px;">Поддерживаются изображения (JPG, PNG, GIF, WEBP) и видео (MP4, MOV, AVI, MKV, WEBM)</p>
                <p class="text-muted" style="font-size: 12px; margin-bottom: 10px;">Максимальный размер файла: <?= AdvertisementImage::MAX_VIDEO_SIZE_MGB ?> MB</p>
                <p class="text-muted" style="margin-bottom: 10px;">или</p>
                <label class="btn btn-primary btn-file">
                    <span class="glyphicon glyphicon-folder-open"></span> Выберите файлы
                    <input type="file" accept="image/*,video/*" id="image-file-input" data-type="<?= $type ?>" data-max-size="<?= AdvertisementImage::MAX_VIDEO_SIZE ?>" style="display: none;" multiple>
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