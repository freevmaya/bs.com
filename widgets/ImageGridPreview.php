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
     * @var array Список изображений/видео с методом getThumbnailUrl() и getImageUrl()
     */
    public $images = [];
    
    /**
     * @var int Максимальное количество элементов для отображения
     */
    public $maxImages = 5;
    
    /**
     * @var string CSS класс для контейнера
     */
    public $containerClass = 'image-grid-preview';
    
    public function run()
    {
        if (empty($this->images)) {
            return $this->renderPlaceholder();
        }
        
        // Если передан массив объектов, используем их напрямую
        // Если передан Query, то выполняем запрос
        if ($this->images instanceof \yii\db\ActiveQuery) {
            $this->images = $this->images->limit($this->maxImages)->all();
        } elseif (is_array($this->images)) {
            // Уже массив, используем как есть
            $this->images = array_slice($this->images, 0, $this->maxImages);
        }
        
        $items = $this->images;
        $count = count($items);
        
        $this->registerJs();
        
        return $this->renderGrid($items, $count);
    }
    
    /**
     * Регистрирует JavaScript
     */
    protected function registerJs()
    {
        $view = $this->getView();
        
        // Регистрируем JS файл
        $view->registerJsFile(
            '@web/js/image-grid-preview.js',
            ['depends' => [\yii\web\JqueryAsset::class], 'position' => View::POS_END]
        );
    }
    
    /**
     * Рендерит сетку элементов
     */
    protected function renderGrid($items, $count)
    {
        $html = Html::beginTag('div', [
            'class' => $this->containerClass,
        ]);
        
        $html .= $this->renderOverlay($count);
        $html .= $this->renderItems($items, $count);
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
     * Рендерит бейдж с количеством элементов (если больше 5)
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
     * Рендерит элементы в зависимости от количества
     */
    protected function renderItems($items, $count)
    {
        if ($count === 1) {
            return $this->renderOneItem($items[0]);
        } elseif ($count === 2) {
            return $this->renderTwoItems($items);
        } elseif ($count === 3) {
            return $this->renderThreeItems($items);
        } elseif ($count === 4) {
            return $this->renderFourItems($items);
        } else {
            return $this->renderFiveItems($items);
        }
    }
    
    /**
     * 1 элемент - на всю область
     */
    protected function renderOneItem($item)
    {
        return Html::tag('div', $this->getItemTag($item), [
            'class' => 'grid-item grid-item-full',
        ]);
    }
    
    /**
     * 2 элемента - вертикальный сплит
     */
    protected function renderTwoItems($items)
    {
        $html = Html::beginTag('div', [
            'class' => 'grid-column',
        ]);
        
        foreach ($items as $item) {
            $html .= Html::tag('div', $this->getItemTag($item), [
                'class' => 'grid-item grid-item-half',
            ]);
        }
        
        $html .= Html::endTag('div');
        return $html;
    }
    
    /**
     * 3 элемента - 2 сверху, 1 снизу
     */
    protected function renderThreeItems($items)
    {
        $html = Html::beginTag('div', [
            'class' => 'grid-three',
        ]);
        
        // Верхняя строка - 2 элемента
        $topRow = Html::beginTag('div', [
            'class' => 'grid-row',
        ]);
        foreach (array_slice($items, 0, 2) as $item) {
            $topRow .= Html::tag('div', $this->getItemTag($item), [
                'class' => 'grid-item grid-item-half',
            ]);
        }
        $topRow .= Html::endTag('div');
        $html .= $topRow;
        
        // Нижняя строка - 1 элемент
        $html .= Html::tag('div', $this->getItemTag($items[2]), [
            'class' => 'grid-item grid-item-full',
        ]);
        
        $html .= Html::endTag('div');
        return $html;
    }
    
    /**
     * 4 элемента - квадрат 2x2
     */
    protected function renderFourItems($items)
    {
        $html = Html::beginTag('div', [
            'class' => 'grid-four',
        ]);
        
        for ($row = 0; $row < 2; $row++) {
            $rowHtml = Html::beginTag('div', [
                'class' => 'grid-row',
            ]);
            for ($col = 0; $col < 2; $col++) {
                $index = $row * 2 + $col;
                if (isset($items[$index])) {
                    $rowHtml .= Html::tag('div', $this->getItemTag($items[$index]), [
                        'class' => 'grid-item grid-item-quarter',
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
     * 5 элементов - как в Telegram/VK
     * Схема: 1 большой слева, 4 маленьких справа (2x2)
     */
    protected function renderFiveItems($items)
    {
        $html = Html::beginTag('div', [
            'class' => 'grid-five',
        ]);
        
        // Левая часть - большой элемент
        $html .= Html::tag('div', $this->getItemTag($items[0]), [
            'class' => 'grid-item grid-item-main',
        ]);
        
        // Правая часть - 4 маленьких (2x2)
        $rightHtml = Html::beginTag('div', [
            'class' => 'grid-right',
        ]);
        
        for ($row = 0; $row < 2; $row++) {
            $rowHtml = Html::beginTag('div', [
                'class' => 'grid-row',
            ]);
            for ($col = 0; $col < 2; $col++) {
                $index = 1 + $row * 2 + $col;
                if (isset($items[$index])) {
                    $rowHtml .= Html::tag('div', $this->getItemTag($items[$index]), [
                        'class' => 'grid-item grid-item-small',
                    ]);
                } else {
                    $rowHtml .= Html::tag('div', '', [
                        'class' => 'grid-item grid-item-small',
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
     * Получить HTML тег элемента (изображение или видео)
     */
    protected function getItemTag($item)
    {
        if (is_object($item)) {
            if (method_exists($item, 'isVideo') && $item->isVideo()) {
                return $this->getVideoTag($item);
            }
            if (method_exists($item, 'getThumbnailUrl')) {
                $url = $item->getThumbnailUrl();
            } else {
                $url = '';
            }
            if (method_exists($item, 'getImageUrl')) {
                $fullUrl = $item->getImageUrl();
            } else {
                $fullUrl = $url;
            }
            $isVideo = method_exists($item, 'isVideo') && $item->isVideo();
        } elseif (is_string($item)) {
            $url = $item;
            $fullUrl = $item;
            $isVideo = false;
        } else {
            $url = '';
            $fullUrl = '';
            $isVideo = false;
        }
        
        // Для видео используем миниатюру с иконкой проигрывания
        if ($isVideo) {
            $videoIcon = '<div class="video-icon-overlay"><div class="play-icon"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg></div></div>';
            return Html::tag('div', 
                Html::img($url, [
                    'alt' => 'Видео',
                    'loading' => 'lazy',
                    'data-full-image' => $fullUrl,
                    'data-is-video' => 'true',
                    'class' => 'grid-preview-item',
                    'onclick' => 'window.openFullscreenFromPreview(this)',
                ]) . $videoIcon,
                ['class' => 'video-item']
            );
        }
        
        return Html::img($url, [
            'alt' => 'Изображение',
            'loading' => 'lazy',
            'data-full-image' => $fullUrl,
            'data-is-video' => 'false',
            'class' => 'grid-preview-item',
            'onclick' => 'window.openFullscreenFromPreview(this)',
        ]);
    }
    
    /**
     * Получить HTML для видео
     */
    protected function getVideoTag($item)
    {
        $thumbnailUrl = $item->getThumbnailUrl();
        $videoUrl = $item->getImageUrl();
        
        $videoIcon = '<div class="video-icon-overlay"><div class="play-icon"><svg viewBox="0 0 24 24" fill="currentColor"><polygon points="5,3 19,12 5,21"/></svg></div></div>';
        
        return Html::tag('div', 
            Html::img($thumbnailUrl, [
                'alt' => 'Видео',
                'loading' => 'lazy',
                'data-full-image' => $videoUrl,
                'data-is-video' => 'true',
                'class' => 'grid-preview-item',
                'onclick' => 'window.openFullscreenFromPreview(this)',
            ]) . $videoIcon,
            ['class' => 'video-item']
        );
    }
    
    /**
     * Рендерит плейсхолдер при отсутствии элементов
     */
    protected function renderPlaceholder()
    {
        return Html::tag('div', 
            Html::tag('span', '', [
                'class' => 'glyphicon glyphicon-picture',
            ]),
            [
                'class' => $this->containerClass . ' placeholder',
            ]
        );
    }
}