<?php

namespace app\components;

use Yii;
use yii\base\Component;

class TempAdStorage extends Component
{
    const SESSION_KEY = 'temp_advertisements';
    
    /**
     * Генерация временного ID
     */
    public function generateTempId()
    {
        // Получаем максимальный реальный ID из таблицы объявлений
        $maxRealId = \app\models\Advertisement::find()->max('id') ?? 0;
        
        // Временный ID = максимальный реальный ID + случайное число от 100 до 10000
        $tempId = $maxRealId + rand(100, 10000);
        
        // Убеждаемся, что такого временного ID еще нет в сессии
        $tempIds = $this->getAllTempIds();
        while (in_array($tempId, $tempIds)) {
            $tempId = $maxRealId + rand(100, 10000);
        }
        
        return $tempId;
    }
    
    /**
     * Сохраняет временное объявление с изображениями
     */
    public function saveTempAd($tempId, $data)
    {
        $session = Yii::$app->session;
        $tempAds = $session->get(self::SESSION_KEY, []);
        
        if (!isset($tempAds[$tempId])) {
            $tempAds[$tempId] = [];
        }
        
        $tempAds[$tempId] = array_merge($tempAds[$tempId], $data);
        $session->set(self::SESSION_KEY, $tempAds);
    }
    
    /**
     * Добавляет временное изображение
     */
    public function addTempImage($tempId, $imageData)
    {
        $session = Yii::$app->session;
        $tempAds = $session->get(self::SESSION_KEY, []);
        
        if (!isset($tempAds[$tempId]['images'])) {
            $tempAds[$tempId]['images'] = [];
        }
        
        $tempAds[$tempId]['images'][] = $imageData;
        $session->set(self::SESSION_KEY, $tempAds);
    }
    
    /**
     * Получает данные временного объявления
     */
    public function getTempAd($tempId)
    {
        $session = Yii::$app->session;
        $tempAds = $session->get(self::SESSION_KEY, []);
        
        return $tempAds[$tempId] ?? null;
    }
    
    /**
     * Получает изображения временного объявления
     */
    public function getTempImages($tempId)
    {
        $tempAd = $this->getTempAd($tempId);
        return $tempAd['images'] ?? [];
    }
    
    /**
     * Переносит изображения из временного объявления в реальное (с перемещением)
     */
    public function migrateImages($tempId, $realId)
    {
        $images = $this->getTempImages($tempId);
        $migratedCount = 0;
        
        foreach ($images as $imageData) {
            // Путь к временным файлам
            $tempFilePath = Yii::getAlias('@webroot') . '/uploads/advertisements/' . $imageData['file_path'];
            $tempThumbPath = Yii::getAlias('@webroot') . '/uploads/advertisements/' . $imageData['thumbnail_path'];
            
            // Путь к постоянным файлам
            $fileName = basename($imageData['file_path']);
            $permanentFilePath = Yii::getAlias('@webroot') . '/uploads/advertisements/' . $fileName;
            $permanentThumbPath = Yii::getAlias('@webroot') . '/uploads/advertisements/thumbnails/' . $fileName;
            
            // Создаем директории если не существуют
            $permanentDir = Yii::getAlias('@webroot') . '/uploads/advertisements/';
            $permanentThumbDir = Yii::getAlias('@webroot') . '/uploads/advertisements/thumbnails/';
            
            if (!is_dir($permanentDir)) {
                mkdir($permanentDir, 0777, true);
            }
            if (!is_dir($permanentThumbDir)) {
                mkdir($permanentThumbDir, 0777, true);
            }
            
            // ПЕРЕМЕЩАЕМ файлы из временной папки в постоянную
            if (file_exists($tempFilePath) && file_exists($tempThumbPath)) {
                rename($tempFilePath, $permanentFilePath);
                rename($tempThumbPath, $permanentThumbPath);
                
                // Создаем запись в базе данных
                $image = new \app\models\AdvertisementImage();
                $image->advertisement_id = $realId;
                $image->file_name = $fileName;
                $image->file_path = $fileName;
                $image->thumbnail_path = $fileName;
                $image->sort_order = $imageData['sort_order'] ?? 0;
                
                if ($image->save(false)) {
                    $migratedCount++;
                }
            }
        }
        
        // Очищаем временную директорию (удаляем пустые папки)
        $this->cleanupTempDirectory($tempId);
        
        // Очищаем данные сессии
        $session = Yii::$app->session;
        $tempAds = $session->get(self::SESSION_KEY, []);
        unset($tempAds[$tempId]);
        $session->set(self::SESSION_KEY, $tempAds);
        
        return $migratedCount;
    }

    /**
     * Очищает временную директорию после переноса файлов
     */
    private function cleanupTempDirectory($tempId)
    {
        $tempDir = Yii::getAlias('@webroot') . '/uploads/advertisements/temp/' . $tempId;
        
        if (is_dir($tempDir)) {
            // Удаляем все файлы в подпапках
            $files = glob($tempDir . '/thumbnails/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            
            // Удаляем подпапки
            if (is_dir($tempDir . '/thumbnails')) {
                rmdir($tempDir . '/thumbnails');
            }
            
            // Удаляем основную папку
            rmdir($tempDir);
        }
    }
    
    /**
     * Очищает временное объявление
     */
    public function clearTempAd($tempId)
    {
        $session = Yii::$app->session;
        $tempAds = $session->get(self::SESSION_KEY, []);
        
        // Удаляем временные файлы (после того как они скопированы)
        $images = $this->getTempImages($tempId);
        foreach ($images as $imageData) {
            $this->deleteTempFiles($imageData);
        }
        
        unset($tempAds[$tempId]);
        $session->set(self::SESSION_KEY, $tempAds);
    }
    
    /**
     * Удаляет временные файлы изображений (ПУБЛИЧНЫЙ МЕТОД)
     */
    public function deleteTempFiles($imageData)
    {
        $fullPath = Yii::getAlias('@webroot') . '/uploads/advertisements/' . $imageData['file_path'];
        $thumbPath = Yii::getAlias('@webroot') . '/uploads/advertisements/' . $imageData['thumbnail_path'];
        
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
        if (file_exists($thumbPath)) {
            unlink($thumbPath);
        }
    }
    
    /**
     * Получает все временные ID
     */
    public function getAllTempIds()
    {
        $session = Yii::$app->session;
        $tempAds = $session->get(self::SESSION_KEY, []);
        return array_keys($tempAds);
    }
}