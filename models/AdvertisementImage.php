<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\web\UploadedFile;
use yii\imagine\Image;
use yii\helpers\FileHelper;

class AdvertisementImage extends ActiveRecord
{
    const IMAGE_WIDTH = 1200;
    const IMAGE_HEIGHT = 1200;
    const THUMBNAIL_WIDTH = 200;
    const THUMBNAIL_HEIGHT = 200;
    const QUALITY = 85;
    const MAX_VIDEO_SIZE = 100 * 1024 * 1024; // 100 MB
    
    public $imageFile;
    
    // Типы файлов
    const TYPE_IMAGE = 'image';
    const TYPE_VIDEO = 'video';
    
    public static function tableName()
    {
        return 'advertisement_images';
    }
    
    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false
            ],
        ];
    }
    
    public function rules()
    {
        return [
            [['advertisement_id'], 'required'],
            [['advertisement_id', 'sort_order'], 'integer'],
            [['file_name', 'file_path', 'thumbnail_path', 'file_type'], 'string', 'max' => 500],
            // imageFile валидируется отдельно, без обязательных полей
            [['imageFile'], 'file', 
                'skipOnEmpty' => true, 
                'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'],
                'maxSize' => self::MAX_VIDEO_SIZE,
                'tooBig' => 'Размер файла не должен превышать 100 MB',
                'checkExtensionByMimeType' => false,
            ],
        ];
    }
    
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'advertisement_id' => 'Объявление',
            'file_name' => 'Имя файла',
            'file_path' => 'Путь к файлу',
            'thumbnail_path' => 'Путь к миниатюре',
            'file_type' => 'Тип файла',
            'sort_order' => 'Порядок сортировки',
            'created_at' => 'Создано',
        ];
    }
    
    public function getAdvertisement()
    {
        return $this->hasOne(Advertisement::class, ['id' => 'advertisement_id']);
    }
    
    /**
     * Проверяет, является ли файл видео
     */
    public function isVideo()
    {
        if ($this->file_type === self::TYPE_VIDEO) {
            return true;
        }
        $ext = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
        $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
        return in_array($ext, $videoExtensions);
    }
    
    /**
     * Проверяет, является ли файл изображением
     */
    public function isImage()
    {
        if ($this->file_type === self::TYPE_IMAGE) {
            return true;
        }
        $ext = strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        return in_array($ext, $imageExtensions);
    }
    
    public function getImageUrl()
    {
        return '/uploads/advertisements/' . $this->file_path;
    }
    
    public function getThumbnailUrl()
    {
        if ($this->isVideo() && !empty($this->thumbnail_path)) {
            return '/uploads/advertisements/thumbnails/' . $this->thumbnail_path;
        }
        if ($this->isImage()) {
            return '/uploads/advertisements/thumbnails/' . $this->thumbnail_path;
        }
        return '/uploads/advertisements/thumbnails/' . $this->thumbnail_path;
    }
    
    /**
     * Получить иконку видео
     */
    public function getVideoIcon()
    {
        return '<span class="video-icon"><span class="glyphicon glyphicon-play"></span></span>';
    }
    
    /**
     * Получить длительность видео (если есть)
     */
    public function getVideoDuration()
    {
        // В реальном проекте можно получить через FFmpeg
        return null;
    }
    
    public function upload()
    {
        if (!$this->imageFile) {
            return false;
        }
        
        // Приводим расширение к нижнему регистру
        $extension = strtolower($this->imageFile->extension);
        
        // Проверяем разрешенные расширения
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
        if (!in_array($extension, $allowedExtensions)) {
            $this->addError('imageFile', 'Разрешены только файлы с расширениями: ' . implode(', ', $allowedExtensions));
            return false;
        }
        
        // Проверка размера
        if ($this->imageFile->size > self::MAX_VIDEO_SIZE) {
            $this->addError('imageFile', 'Размер файла не должен превышать 100 MB');
            return false;
        }
        
        // Валидация
        if (!$this->validate(['imageFile'])) {
            return false;
        }
        
        $fileName = $this->generateFileName($extension);
        $thumbFileName = $this->generateFileName('jpg');
        
        $uploadPath = Yii::getAlias('@webroot') . '/uploads/advertisements/';
        $thumbnailPath = Yii::getAlias('@webroot') . '/uploads/advertisements/thumbnails/';
        
        // Создаем директории
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        if (!is_dir($thumbnailPath)) {
            mkdir($thumbnailPath, 0777, true);
        }
        
        $fullPath = $uploadPath . $fileName;
        $thumbnailFullPath = $thumbnailPath . $thumbFileName;
        
        // Определяем тип файла
        if ($this->isVideoFile($extension)) {
            $this->file_type = self::TYPE_VIDEO;
            if (!$this->imageFile->saveAs($fullPath)) {
                Yii::error('Failed to save video file: ' . $fullPath);
                return false;
            }
            // Создаем превью для видео
            $this->createVideoThumbnail($fullPath, $thumbnailFullPath);
            $this->thumbnail_path = $thumbFileName;
        } else {
            $this->file_type = self::TYPE_IMAGE;
            if (!$this->imageFile->saveAs($fullPath)) {
                Yii::error('Failed to save file: ' . $fullPath);
                return false;
            }
            // Оптимизируем и создаем превью
            $this->optimizeImage($fullPath, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, self::QUALITY);
            $this->createThumbnail($fullPath, $thumbnailFullPath, self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
            $this->thumbnail_path = $thumbFileName;
        }
        
        $this->file_name = $fileName;
        $this->file_path = $fileName;
        
        return true;
    }

    /**
     * Загрузка изображения для временного объявления
     */
    public function uploadTemp($tempId)
    {
        if (!$this->imageFile) {
            return false;
        }
        
        // Приводим расширение к нижнему регистру
        $extension = strtolower($this->imageFile->extension);
        
        // Проверяем разрешенные расширения
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
        if (!in_array($extension, $allowedExtensions)) {
            $this->addError('imageFile', 'Разрешены только файлы с расширениями: ' . implode(', ', $allowedExtensions));
            return false;
        }
        
        // Проверка размера
        if ($this->imageFile->size > self::MAX_VIDEO_SIZE) {
            $this->addError('imageFile', 'Размер файла не должен превышать 100 MB');
            return false;
        }
        
        if (!$this->validate(['imageFile'])) {
            return false;
        }
        
        $fileName = $this->generateFileName($extension);
        $thumbFileName = $this->generateFileName('jpg');
        
        // Используем временную директорию
        $uploadPath = Yii::getAlias('@webroot') . '/uploads/advertisements/temp/' . $tempId . '/';
        $thumbnailPath = Yii::getAlias('@webroot') . '/uploads/advertisements/temp/' . $tempId . '/thumbnails/';
        
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }
        if (!is_dir($thumbnailPath)) {
            mkdir($thumbnailPath, 0777, true);
        }
        
        $fullPath = $uploadPath . $fileName;
        $thumbnailFullPath = $thumbnailPath . $thumbFileName;
        
        // Определяем тип файла
        if ($this->isVideoFile($extension)) {
            $this->file_type = self::TYPE_VIDEO;
            if (!$this->imageFile->saveAs($fullPath)) {
                Yii::error('Failed to save temp video file: ' . $fullPath);
                return false;
            }
            // Создаем превью для видео
            $this->createVideoThumbnail($fullPath, $thumbnailFullPath);
        } else {
            $this->file_type = self::TYPE_IMAGE;
            if (!$this->imageFile->saveAs($fullPath)) {
                Yii::error('Failed to save temp file: ' . $fullPath);
                return false;
            }
            $this->optimizeImage($fullPath, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, self::QUALITY);
            $this->createThumbnail($fullPath, $thumbnailFullPath, self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
        }
        
        $this->file_name = $fileName;
        $this->file_path = 'temp/' . $tempId . '/' . $fileName;
        $this->thumbnail_path = 'temp/' . $tempId . '/thumbnails/' . $thumbFileName;
        
        return true;
    }
    
    /**
     * Проверяет, является ли файл видео по расширению
     */
    protected function isVideoFile($extension)
    {
        $videoExtensions = ['mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm'];
        return in_array(strtolower($extension), $videoExtensions);
    }
    
    /**
     * Создает миниатюру для видео (требуется FFmpeg)
     */
    protected function createVideoThumbnail($videoPath, $thumbnailPath)
    {
        // Проверяем, существует ли видео файл
        if (!file_exists($videoPath)) {
            $this->createPlaceholderThumbnail($thumbnailPath);
            return false;
        }

        // Определяем путь к ffmpeg в зависимости от ОС
        $ffmpegPath = $this->findFfmpegPath();
        
        if (!$ffmpegPath) {
            $this->createPlaceholderThumbnail($thumbnailPath);
            return false;
        }

        // Пробуем создать миниатюру через FFmpeg
        $command = $ffmpegPath . ' -i ' . escapeshellarg($videoPath) .
                   ' -ss 00:00:01 -vframes 1 -vf scale=' . self::THUMBNAIL_WIDTH . ':' . self::THUMBNAIL_HEIGHT .
                   ' -f image2 ' . escapeshellarg($thumbnailPath) . ' 2>&1';

        $output = shell_exec($command);

        // Проверяем, создался ли файл
        if (!file_exists($thumbnailPath) || filesize($thumbnailPath) === 0) {
            $this->createPlaceholderThumbnail($thumbnailPath);
            return false;
        }

        return true;
    }

    /**
     * Находит путь к ffmpeg в системе
     * @return string|null
     */
    protected function findFfmpegPath()
    {
        // Для Linux/Mac
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            $output = shell_exec('which ffmpeg 2>/dev/null');
            if (!empty($output)) {
                return trim($output);
            }
            // Проверяем пути из конфигурации
            $paths = Yii::$app->params['ffmpeg_paths'] ?? [];
            foreach ($paths as $path) {
                if (!empty($path) && file_exists($path)) {
                    return $path;
                }
            }
            return null;
        }
        
        // Для Windows - проверяем через where
        $output = shell_exec('where ffmpeg 2>nul');
        if (!empty($output)) {
            $lines = explode("\n", trim($output));
            if (!empty($lines[0]) && file_exists(trim($lines[0]))) {
                return trim($lines[0]);
            }
        }
        
        // Проверяем пути из конфигурации
        $paths = Yii::$app->params['ffmpeg_paths'] ?? [];
        foreach ($paths as $path) {
            if (empty($path)) continue;
            if ($path === 'ffmpeg') continue; // пропускаем, уже проверили через where
            if (file_exists($path)) {
                return $path;
            }
        }
        
        // Проверяем переменную окружения
        $envPath = getenv('FFMPEG_PATH');
        if (!empty($envPath) && file_exists($envPath)) {
            return $envPath;
        }
        
        return null;
    }
    
    /**
     * Создает заглушку для видео
     */
    protected function createPlaceholderThumbnail($thumbnailPath)
    {
        // Проверяем наличие готового файла-заглушки
        $defaultThumbnail = Yii::getAlias('@webroot') . '/images/thumbnail_video_default.jpg';
        
        if (file_exists($defaultThumbnail)) {
            // Копируем готовую заглушку
            if (copy($defaultThumbnail, $thumbnailPath)) {
                return;
            }
            // Если копирование не удалось, продолжаем создавать через GD
        }
        
        // Создаем простую заглушку с помощью GD
        $width = self::THUMBNAIL_WIDTH;
        $height = self::THUMBNAIL_HEIGHT;

        // Проверяем, что GD расширение доступно
        if (!function_exists('imagecreatetruecolor')) {
            // Если GD нет, создаем пустой файл-заглушку
            file_put_contents($thumbnailPath, '');
            return;
        }

        $image = imagecreatetruecolor($width, $height);

        // Темный фон
        $bgColor = imagecolorallocate($image, 40, 40, 50);
        imagefill($image, 0, 0, $bgColor);

        // Рисуем треугольник "Play" (на 10% меньше)
        $playColor = imagecolorallocate($image, 255, 255, 255);
        
        // Уменьшаем размер треугольника на 10%
        $scale = 0.9;
        $centerX = $width / 2;
        $centerY = $height / 2;
        $size = min($width, $height) * 0.35 * $scale;
        
        $points = [
            $centerX - $size * 0.5, $centerY - $size * 0.6,
            $centerX - $size * 0.5, $centerY + $size * 0.6,
            $centerX + $size * 0.7, $centerY
        ];

        // Исправленный вызов imagefilledpolygon для PHP 8+
        if (PHP_VERSION_ID >= 80000) {
            imagefilledpolygon($image, $points, $playColor);
        } else {
            imagefilledpolygon($image, $points, 3, $playColor);
        }

        imagepng($image, $thumbnailPath);
    }
    
    /**
     * Генерирует имя файла в нижнем регистре
     */
    private function generateFileName($extension)
    {
        $name = strtolower(uniqid() . '_' . time());
        $ext = strtolower($extension);
        return $name . '.' . $ext;
    }
    
    private function optimizeImage($path, $maxWidth, $maxHeight, $quality)
    {
        try {
            if (!file_exists($path)) {
                return;
            }
            
            $image = Image::getImagine()->open($path);
            $size = $image->getSize();
            
            $width = $size->getWidth();
            $height = $size->getHeight();
            
            if ($width > $maxWidth || $height > $maxHeight) {
                $ratio = min($maxWidth / $width, $maxHeight / $height);
                $newWidth = round($width * $ratio);
                $newHeight = round($height * $ratio);
                $image->resize(new \Imagine\Image\Box($newWidth, $newHeight));
            }
            
            $options = [
                'jpeg_quality' => $quality,
                'png_compression_level' => 9
            ];
            
            $image->save($path, $options);
        } catch (\Exception $e) {
            Yii::error('Image optimization failed: ' . $e->getMessage());
        }
    }
    
    private function createThumbnail($sourcePath, $targetPath, $thumbWidth, $thumbHeight)
    {
        try {
            if (!file_exists($sourcePath)) {
                return;
            }
            
            Image::thumbnail($sourcePath, $thumbWidth, $thumbHeight)
                ->save($targetPath, ['quality' => 80]);
        } catch (\Exception $e) {
            Yii::error('Thumbnail creation failed: ' . $e->getMessage());
        }
    }
    
    public function beforeDelete()
    {
        if (parent::beforeDelete()) {
            $fullPath = Yii::getAlias('@webroot') . '/uploads/advertisements/' . $this->file_path;
            $thumbnailFullPath = Yii::getAlias('@webroot') . '/uploads/advertisements/thumbnails/' . $this->thumbnail_path;
            
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            if (file_exists($thumbnailFullPath)) {
                unlink($thumbnailFullPath);
            }
            return true;
        }
        return false;
    }
}