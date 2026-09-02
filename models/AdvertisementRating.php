<?php
// models/AdvertisementRating.php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

/**
 * Модель для хранения результатов оценки снаряжения
 * 
 * @property int $id
 * @property int $advertisement_id
 * @property int $user_id
 * @property string $ai_model
 * @property string $rating_data
 * @property float $overall_score
 * @property string $summary
 * @property string $pros
 * @property string $cons
 * @property string $recommendation
 * @property int $created_at
 */
class AdvertisementRating extends ActiveRecord
{
    public static function tableName()
    {
        return 'advertisement_ratings';
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
            ],
        ];
    }

    public function rules()
    {
        return [
            [['advertisement_id', 'ai_model'], 'required'],
            [['advertisement_id', 'user_id'], 'integer'],
            [['rating_data', 'summary', 'pros', 'cons', 'recommendation'], 'string'],
            [['overall_score'], 'number', 'min' => 0, 'max' => 10],
            [['ai_model'], 'string', 'max' => 50],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'advertisement_id' => 'Объявление',
            'user_id' => 'Пользователь',
            'ai_model' => 'Модель AI',
            'rating_data' => 'Данные оценки',
            'overall_score' => 'Общая оценка',
            'summary' => 'Краткое резюме',
            'pros' => 'Плюсы',
            'cons' => 'Минусы',
            'recommendation' => 'Рекомендация',
            'created_at' => 'Создано',
        ];
    }

    public function getAdvertisement()
    {
        return $this->hasOne(Advertisement::class, ['id' => 'advertisement_id']);
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /**
     * Получить структурированный массив данных оценки
     */
    public function getParsedRatingData()
    {
        return json_decode($this->rating_data, true) ?: [];
    }

    /**
     * Получить символ валюты
     */
    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'RUB' => '₽',
            'USD' => '$',
            'EUR' => '€',
        ];
        return $symbols[strtoupper($currency)] ?? $currency;
    }

    /**
     * Получить HTML для отображения оценки
     */
    public function getRatingHtml()
    {
        $data = $this->getParsedRatingData();
        $html = '';

        $html .= '<div class="rating-container">';
        
        // ============================================================
        // ЦЕНОВОЙ БЛОК
        // ============================================================
        if (isset($data['fair_price']) && $data['fair_price'] > 0) {
            $currency = $data['currency'] ?? 'RUB';
            $symbol = $data['currency_symbol'] ?? $this->getCurrencySymbol($currency);
            $fairPrice = $data['fair_price'];
            
            $html .= '<div class="rating-price-block" style="padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #007bff;">';
            $html .= '<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">';
            
            // Рекомендуемая цена
            $html .= '<div>';
            $html .= '<span style="font-size: 14px; color: #6c757d;">💰 Рекомендуемая цена:</span>';
            $html .= '<div style="font-size: 28px; font-weight: 700; color: #28a745;">' . number_format($fairPrice, 0, '.', ' ') . ' ' . $symbol . '</div>';
            $html .= '</div>';
            
            // Диапазон цен
            if (isset($data['price_range'])) {
                $minPrice = $data['price_range']['min'] ?? 0;
                $maxPrice = $data['price_range']['max'] ?? 0;
                
                $html .= '<div style="text-align: right;">';
                $html .= '<span style="font-size: 13px; color: #6c757d;">Диапазон цен на рынке:</span>';
                $html .= '<div style="font-size: 16px; font-weight: 600;">' . 
                         number_format($minPrice, 0, '.', ' ') . ' ' . $symbol . ' — ' . 
                         number_format($maxPrice, 0, '.', ' ') . ' ' . $symbol . '</div>';
                $html .= '</div>';
            }
            
            // Уверенность
            if (isset($data['confidence'])) {
                $confidence = (int)$data['confidence'];
                $html .= '<div style="text-align: center; min-width: 80px;">';
                $html .= '<span style="font-size: 13px; color: #6c757d;">Уверенность:</span>';
                $html .= '<div style="font-size: 18px; font-weight: 700; color: ' . $this->getScoreColor($confidence * 2) . ';">' . $confidence . '/10</div>';
                $html .= '</div>';
            }
            
            $html .= '</div>'; // flex
            $html .= '</div>'; // rating-price-block
        }

        // ============================================================
        // ОБЩАЯ ОЦЕНКА
        // ============================================================
        $score = $this->overall_score ?? 0;
        $scorePercent = round($score / 10 * 100);
        $colorClass = $this->getScoreColorClass($score);
        
        $html .= '<div class="rating-overall">';
        $html .= '<div class="rating-score ' . $colorClass . '">';
        $html .= number_format($score, 1) . ' / 10';
        $html .= '</div>';
        $html .= '<div class="rating-bar">';
        $html .= '<div class="rating-bar-fill" style="width: ' . $scorePercent . '%; background: ' . $this->getScoreColor($score) . ';"></div>';
        $html .= '</div>';
        $html .= '</div>';

        // ============================================================
        // РЕЗЮМЕ / АНАЛИЗ РЫНКА
        // ============================================================
        if ($this->summary) {
            $html .= '<div class="rating-summary" style="padding: 12px 16px; background: var(--bs-tertiary-bg); border-radius: 8px; margin-bottom: 15px; line-height: 1.6;">';
            $html .= '<strong>📊 Анализ рынка:</strong> ' . nl2br($this->summary);
            $html .= '</div>';
        }

        // ============================================================
        // ПЛЮСЫ И МИНУСЫ
        // ============================================================
        $html .= '<div class="rating-pros-cons" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">';
        
        // Плюсы
        if ($this->pros) {
            $html .= '<div class="rating-pros" style="padding: 12px 16px; border-radius: 8px; background: rgba(40, 167, 69, 0.05); border-left: 3px solid #28a745;">';
            $html .= '<strong>✅ Плюсы:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px;">';
            foreach (explode("\n", $this->pros) as $item) {
                if (trim($item)) {
                    $html .= '<li style="margin-bottom: 4px; line-height: 1.5;">' . trim($item) . '</li>';
                }
            }
            $html .= '</ul></div>';
        }
        
        // Минусы
        if ($this->cons) {
            $html .= '<div class="rating-cons" style="padding: 12px 16px; border-radius: 8px; background: rgba(220, 53, 69, 0.05); border-left: 3px solid #dc3545;">';
            $html .= '<strong>❌ Минусы:</strong><ul style="margin: 8px 0 0 0; padding-left: 20px;">';
            foreach (explode("\n", $this->cons) as $item) {
                if (trim($item)) {
                    $html .= '<li style="margin-bottom: 4px; line-height: 1.5;">' . trim($item) . '</li>';
                }
            }
            $html .= '</ul></div>';
        }
        
        $html .= '</div>';

        // ============================================================
        // РЕКОМЕНДАЦИЯ
        // ============================================================
        if ($this->recommendation) {
            $html .= '<div class="rating-recommendation" style="padding: 12px 16px; background: rgba(0, 123, 255, 0.05); border-left: 3px solid #007bff; border-radius: 8px; margin-bottom: 15px;">';
            $html .= '<strong>💡 Рекомендация:</strong> ' . nl2br($this->recommendation);
            $html .= '</div>';
        }

        // ============================================================
        // ДЕТАЛЬНЫЕ ДАННЫЕ (раскрывающийся блок)
        // ============================================================
        if (!empty($data)) {
            $html .= '<details class="rating-details" style="margin-top: 10px; cursor: pointer;">';
            $html .= '<summary style="color: var(--bs-link-color); cursor: pointer; padding: 4px 0;">';
            $html .= '📊 Детальный разбор';
            $html .= '</summary>';
            
            // Форматируем данные для отображения
            $displayData = $data;
            unset($displayData['raw_response']); // Убираем сырой ответ если есть
            
            $html .= '<pre style="background: var(--bs-tertiary-bg); padding: 12px; border-radius: 6px; margin: 8px 0 0 0; font-size: 12px; overflow-x: auto; max-height: 300px;">';
            $html .= json_encode($displayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $html .= '</pre>';
            $html .= '</details>';
        }

        // ============================================================
        // МЕТА-ИНФОРМАЦИЯ
        // ============================================================
        $html .= '<div class="rating-meta" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--bs-border-color); font-size: 12px; color: var(--bs-secondary-color);">';
        
        // Тип оценки (AI или внутренний алгоритм)
        if ($this->ai_model === 'internal_algorithm_v1') {
            $html .= 'Оценено на основе <strong>внутреннего алгоритма</strong> на основе данных из БД';
        } else {
            $html .= 'Оценено с помощью: <strong>' . $this->ai_model . '</strong>';
        }
        
        $html .= ' • ' . Yii::$app->formatter->asDatetime($this->created_at);
        $html .= '</div>';

        $html .= '</div>'; // rating-container

        return $html;
    }

    /**
     * Получить цвет оценки
     */
    private function getScoreColor($score)
    {
        if ($score >= 8) return '#28a745';
        if ($score >= 6) return '#ffc107';
        if ($score >= 4) return '#fd7e14';
        return '#dc3545';
    }

    /**
     * Получить CSS класс для оценки
     */
    private function getScoreColorClass($score)
    {
        if ($score >= 8) return 'score-excellent';
        if ($score >= 6) return 'score-good';
        if ($score >= 4) return 'score-average';
        return 'score-poor';
    }
}