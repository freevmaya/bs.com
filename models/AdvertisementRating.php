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
     * Получить HTML для отображения оценки
     */
    public function getRatingHtml()
    {
        $data = $this->getParsedRatingData();
        $html = '';

        $html .= '<div class="rating-container">';
        
        // Общая оценка
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

        // Резюме
        if ($this->summary) {
            $html .= '<div class="rating-summary"><strong>Резюме:</strong> ' . nl2br($this->summary) . '</div>';
        }

        // Плюсы и минусы
        $html .= '<div class="rating-pros-cons">';
        if ($this->pros) {
            $html .= '<div class="rating-pros"><strong>✅ Плюсы:</strong><ul>';
            foreach (explode("\n", $this->pros) as $item) {
                if (trim($item)) {
                    $html .= '<li>' . trim($item) . '</li>';
                }
            }
            $html .= '</ul></div>';
        }
        if ($this->cons) {
            $html .= '<div class="rating-cons"><strong>❌ Минусы:</strong><ul>';
            foreach (explode("\n", $this->cons) as $item) {
                if (trim($item)) {
                    $html .= '<li>' . trim($item) . '</li>';
                }
            }
            $html .= '</ul></div>';
        }
        $html .= '</div>';

        // Рекомендация
        if ($this->recommendation) {
            $html .= '<div class="rating-recommendation"><strong>💡 Рекомендация:</strong> ' . nl2br($this->recommendation) . '</div>';
        }

        // Детальные данные
        if (!empty($data)) {
            $html .= '<details class="rating-details">';
            $html .= '<summary>📊 Детальный разбор</summary>';
            $html .= '<pre>' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . '</pre>';
            $html .= '</details>';
        }

        $html .= '<div class="rating-meta">';
        $html .= 'Оценено с помощью: <strong>' . $this->ai_model . '</strong>';
        $html .= ' • ' . Yii::$app->formatter->asDatetime($this->created_at);
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    private function getScoreColor($score)
    {
        if ($score >= 8) return '#28a745';
        if ($score >= 6) return '#ffc107';
        if ($score >= 4) return '#fd7e14';
        return '#dc3545';
    }

    private function getScoreColorClass($score)
    {
        if ($score >= 8) return 'score-excellent';
        if ($score >= 6) return 'score-good';
        if ($score >= 4) return 'score-average';
        return 'score-poor';
    }
}