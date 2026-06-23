<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\web\UploadedFile;
use yii\imagine\Image;

class AdvertisementImage extends ActiveRecord
{
    const IMAGE_WIDTH = 1200;
    const IMAGE_HEIGHT = 1200;
    const THUMBNAIL_WIDTH = 200;
    const THUMBNAIL_HEIGHT = 200;
    const QUALITY = 85;
    
    public $imageFile;
    
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
            [['file_name', 'file_path', 'thumbnail_path'], 'string', 'max' => 500],
            // imageFile валидируется отдельно, без обязательных полей
            [['imageFile'], 'file', 'skipOnEmpty' => true, 'extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'], 'maxSize' => 5 * 1024 * 1024],
        ];
    }
    
    public function getAdvertisement()
    {
        return $this->hasOne(Advertisement::class, ['id' => 'advertisement_id']);
    }
    
    public function getImageUrl()
    {
        return '/uploads/advertisements/' . $this->file_path;
    }
    
    public function getThumbnailUrl()
    {
        return '/uploads/advertisements/thumbnails/' . $this->thumbnail_path;
    }
    
    public function upload()
    {
        if (!$this->imageFile) {
            return false;
        }
        
        // Проверяем валидацию только для imageFile
        if (!$this->validate(['imageFile'])) {
            return false;
        }
        
        $fileName = $this->generateFileName($this->imageFile->extension);
        
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
        $thumbnailFullPath = $thumbnailPath . $fileName;
        
        // Сохраняем файл
        if (!$this->imageFile->saveAs($fullPath)) {
            Yii::error('Failed to save file: ' . $fullPath);
            return false;
        }
        
        // Оптимизируем и создаем превью
        $this->optimizeImage($fullPath, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, self::QUALITY);
        $this->createThumbnail($fullPath, $thumbnailFullPath, self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
        
        $this->file_name = $fileName;
        $this->file_path = $fileName;
        $this->thumbnail_path = $fileName;
        
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
        
        if (!$this->validate(['imageFile'])) {
            return false;
        }
        
        $fileName = $this->generateFileName($this->imageFile->extension);
        
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
        $thumbnailFullPath = $thumbnailPath . $fileName;
        
        if (!$this->imageFile->saveAs($fullPath)) {
            Yii::error('Failed to save temp file: ' . $fullPath);
            return false;
        }
        
        $this->optimizeImage($fullPath, self::IMAGE_WIDTH, self::IMAGE_HEIGHT, self::QUALITY);
        $this->createThumbnail($fullPath, $thumbnailFullPath, self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
        
        $this->file_name = $fileName;
        $this->file_path = 'temp/' . $tempId . '/' . $fileName;
        $this->thumbnail_path = 'temp/' . $tempId . '/thumbnails/' . $fileName;
        
        return true;
    }
    
    private function generateFileName($extension)
    {
        return uniqid() . '_' . time() . '.' . $extension;
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