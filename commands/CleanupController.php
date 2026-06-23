<?php

namespace app\commands;

use Yii;
use yii\console\Controller;

class CleanupController extends Controller
{
    /**
     * Очистка старых временных файлов (выполнять через cron)
     */
    public function actionTempFiles()
    {
        $tempDir = Yii::getAlias('@webroot/uploads/advertisements/temp');
        if (!is_dir($tempDir)) {
            return;
        }
        
        $files = glob($tempDir . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_dir($file)) {
                // Удаляем папки старше 24 часов
                if ($now - filemtime($file) > 86400) {
                    $this->deleteDirectory($file);
                    echo "Deleted: " . $file . "\n";
                }
            }
        }
    }
    
    private function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        return rmdir($dir);
    }
}