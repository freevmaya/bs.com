<?php

namespace app\widgets;

use Yii;
use yii\base\Widget;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\View;

class ImageGridPreview extends Widget
{
    /**
     * @var array Список изображений с методом getThumbnailUrl() и getImageUrl()
     */
    public $images = [];
    
    /**
     * @var int Максимальное количество изображений для отображения
     */
    public $maxImages = 5;
    
    /**
     * @var int Размер контейнера в пикселях (квадрат)
     */
    public $containerSize = 120;
    
    /**
     * @var int Зазор между изображениями в пикселях
     */
    public $gap = 3;
    
    /**
     * @var string CSS класс для контейнера
     */
    public $containerClass = 'image-grid-preview';
    
    public function run()
    {
        if (empty($this->images)) {
            return $this->renderPlaceholder();
        }
        
        $images = array_slice($this->images, 0, $this->maxImages);
        $count = count($images);
        
        // Регистрируем JS
        $this->registerAssets();
        
        return $this->renderGrid($images, $count);
    }
    
    /**
     * Регистрирует необходимые assets
     */
    protected function registerAssets()
    {
        $view = $this->getView();
        
        // Регистрируем JS файл
        $view->registerJsFile(
            '@web/js/image-grid-preview.js',
            ['depends' => [\yii\web\JqueryAsset::class], 'position' => View::POS_END]
        );
    }
    
    /**
     * Рендерит сетку изображений
     */
    protected function renderGrid($images, $count)
    {
        $html = Html::beginTag('div', [
            'class' => $this->containerClass,
            'style' => "width: {$this->containerSize}px; height: {$this->containerSize}px;",
        ]);
        
        $html .= $this->renderOverlay($count);
        $html .= $this->renderImages($images, $count);
        $html .= $this->renderCountBadge($count);
        
        $html .= Html::endTag('div');
        
        return $html;
    }
    
    /**
     * Рендерит оверлей
     */
    protected function renderOverlay($count)
    {
        return Html::tag('div', '', [
            'class' => 'image-overlay',
        ]);
    }
    
    /**
     * Рендерит бейдж с количеством изображений (если больше 5)
     */
    protected function renderCountBadge($count)
    {
        $totalCount = count($this->images);
        if ($totalCount <= $this->maxImages) {
            return '';
        }
        
        $moreCount = $totalCount - $this->maxImages;
        return Html::tag('div', '+' . $moreCount, [
            'class' => 'image-count-badge',
        ]);
    }
    
    /**
     * Рендерит изображения в зависимости от количества
     */
    protected function renderImages($images, $count)
    {
        if ($count === 1) {
            return $this->renderOneImage($images[0]);
        } elseif ($count === 2) {
            return $this->renderTwoImages($images);
        } elseif ($count === 3) {
            return $this->renderThreeImages($images);
        } elseif ($count === 4) {
            return $this->renderFourImages($images);
        } else {
            return $this->renderFiveImages($images);
        }
    }
    
    /**
     * 1 изображение - на всю область
     */
    protected function renderOneImage($image)
    {
        $size = $this->containerSize;
        return Html::tag('div', $this->getImageTag($image), [
            'class' => 'grid-item grid-item-full',
            'style' => "width: {$size}px; height: {$size}px;",
        ]);
    }
    
    /**
     * 2 изображения - вертикальный сплит
     */
    protected function renderTwoImages($images)
    {
        $size = ($this->containerSize - $this->gap) / 2;
        $html = Html::beginTag('div', [
            'class' => 'grid-column',
            'style' => "width: {$this->containerSize}px; height: {$this->containerSize}px;",
        ]);
        
        foreach ($images as $image) {
            $html .= Html::tag('div', $this->getImageTag($image), [
                'class' => 'grid-item grid-item-half',
                'style' => "width: {$this->containerSize}px; height: {$size}px;",
            ]);
        }
        
        $html .= Html::endTag('div');
        return $html;
    }
    
    /**
     * 3 изображения - 2 сверху, 1 снизу
     */
    protected function renderThreeImages($images)
    {
        $halfSize = ($this->containerSize - $this->gap) / 2;
        
        $html = Html::beginTag('div', [
            'style' => "width: {$this->containerSize}px; height: {$this->containerSize}px; " .
                       "display: flex; flex-direction: column; gap: {$this->gap}px;",
        ]);
        
        // Верхняя строка - 2 изображения
        $topRow = Html::beginTag('div', [
            'class' => 'grid-row',
            'style' => "height: {$halfSize}px;",
        ]);
        foreach (array_slice($images, 0, 2) as $image) {
            $topRow .= Html::tag('div', $this->getImageTag($image), [
                'class' => 'grid-item grid-item-half',
                'style' => "width: {$halfSize}px; height: {$halfSize}px;",
            ]);
        }
        $topRow .= Html::endTag('div');
        $html .= $topRow;
        
        // Нижняя строка - 1 изображение
        $html .= Html::tag('div', $this->getImageTag($images[2]), [
            'class' => 'grid-item grid-item-full',
            'style' => "width: {$this->containerSize}px; height: {$halfSize}px;",
        ]);
        
        $html .= Html::endTag('div');
        return $html;
    }
    
    /**
     * 4 изображения - квадрат 2x2
     */
    protected function renderFourImages($images)
    {
        $size = ($this->containerSize - $this->gap) / 2;
        $html = Html::beginTag('div', [
            'style' => "width: {$this->containerSize}px; height: {$this->containerSize}px; " .
                       "display: flex; flex-direction: column; gap: {$this->gap}px;",
        ]);
        
        for ($row = 0; $row < 2; $row++) {
            $rowHtml = Html::beginTag('div', [
                'class' => 'grid-row',
                'style' => "height: {$size}px;",
            ]);
            for ($col = 0; $col < 2; $col++) {
                $index = $row * 2 + $col;
                if (isset($images[$index])) {
                    $rowHtml .= Html::tag('div', $this->getImageTag($images[$index]), [
                        'class' => 'grid-item grid-item-quarter',
                        'style' => "width: {$size}px; height: {$size}px;",
                    ]);
                }
            }
            $rowHtml .= Html::endTag('div');
            $html .= $rowHtml;
        }
        
        $html .= Html::endTag('div');
        return $html;
    }
    
    /**
     * 5 изображений - как в Telegram/VK
     * Схема: 1 большое слева, 4 маленьких справа (2x2)
     */
    protected function renderFiveImages($images)
    {
        $size = $this->containerSize;
        $smallSize = ($size - $this->gap) / 2;
        $leftSize = ($size - $this->gap) / 2;
        
        $html = Html::beginTag('div', [
            'class' => 'grid-five',
            'style' => "width: {$size}px; height: {$size}px;",
        ]);
        
        // Левая часть - большое изображение
        $html .= Html::tag('div', $this->getImageTag($images[0]), [
            'class' => 'grid-item grid-item-main',
            'style' => "width: {$leftSize}px; height: {$size}px; flex-shrink: 0;",
        ]);
        
        // Правая часть - 4 маленьких (2x2)
        $rightHtml = Html::beginTag('div', [
            'class' => 'grid-right',
            'style' => "width: {$leftSize}px; height: {$size}px;",
        ]);
        
        for ($row = 0; $row < 2; $row++) {
            $rowHtml = Html::beginTag('div', [
                'class' => 'grid-row',
                'style' => "height: {$smallSize}px;",
            ]);
            for ($col = 0; $col < 2; $col++) {
                $index = 1 + $row * 2 + $col;
                if (isset($images[$index])) {
                    $rowHtml .= Html::tag('div', $this->getImageTag($images[$index]), [
                        'class' => 'grid-item grid-item-small',
                        'style' => "width: {$smallSize}px; height: {$smallSize}px;",
                    ]);
                } else {
                    $rowHtml .= Html::tag('div', '', [
                        'class' => 'grid-item grid-item-small',
                        'style' => "width: {$smallSize}px; height: {$smallSize}px; background: #f0f0f0;",
                    ]);
                }
            }
            $rowHtml .= Html::endTag('div');
            $rightHtml .= $rowHtml;
        }
        
        $rightHtml .= Html::endTag('div');
        $html .= $rightHtml;
        
        $html .= Html::endTag('div');
        return $html;
    }
    
    /**
     * Получить HTML тег изображения
     */
    protected function getImageTag($image)
    {
        if (is_object($image)) {
            if (method_exists($image, 'getThumbnailUrl')) {
                $url = $image->getThumbnailUrl();
            } else {
                $url = '';
            }
            if (method_exists($image, 'getImageUrl')) {
                $fullUrl = $image->getImageUrl();
            } else {
                $fullUrl = $url;
            }
        } elseif (is_string($image)) {
            $url = $image;
            $fullUrl = $image;
        } else {
            $url = '';
            $fullUrl = '';
        }
        
        return Html::img($url, [
            'alt' => 'Изображение',
            'style' => 'width: 100%; height: 100%; object-fit: cover; display: block;',
            'loading' => 'lazy',
            'data-full-image' => $fullUrl,
        ]);
    }
    
    /**
     * Рендерит плейсхолдер при отсутствии изображений
     */
    protected function renderPlaceholder()
    {
        $size = $this->containerSize;
        return Html::tag('div', 
            Html::tag('span', '', [
                'class' => 'glyphicon glyphicon-picture',
                'style' => 'font-size: 24px; color: #ccc;',
            ]),
            [
                'class' => $this->containerClass . ' placeholder',
                'style' => "width: {$size}px; height: {$size}px;",
            ]
        );
    }
}