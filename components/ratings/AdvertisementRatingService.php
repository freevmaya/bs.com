<?php
// FILE: .\components\ratings\AdvertisementRatingService.php

namespace app\components\ratings;

use yii\httpclient\Client;
use Yii;
use app\models\Advertisement;
use app\models\AdvertisementGlider;
use app\models\Producer;
use app\models\Certification;

class AdvertisementRatingService
{
    /**
     * @var string API ключ OpenRouter
     */
    private string $apiKey;
    
    /**
     * @var string Базовый URL API
     */
    private string $baseUrl = 'https://openrouter.ai/api/v1';
    
    /**
     * @var string Модель для использования
     */
    private string $model = 'openai/gpt-3.5-turbo';
    
    /**
     * @var string Модель по умолчанию (для совместимости с конфигурацией)
     */
    public string $defaultModel = 'openai/gpt-3.5-turbo';
    
    /**
     * @var array Доступные модели (для совместимости с конфигурацией)
     */
    public array $availableModels = [
        'openai/gpt-3.5-turbo',
        'openai/gpt-4',
        'anthropic/claude-3-haiku',
        'anthropic/claude-3-sonnet',
        'google/gemini-pro',
    ];
    
    /**
     * @var string HTTP Referer для запросов
     */
    private string $referer = 'https://your-site.com';
    
    /**
     * @var string Название приложения
     */
    private string $appTitle = 'Yii2 App';
    
    /**
     * @var int Таймаут запроса в секундах
     */
    private int $timeout = 30;
    
    /**
     * @var array Кэш для объявлений из БД
     */
    private array $advertisementsCache = [];
    
    /**
     * Конструктор
     */
    public function __construct()
    {
        $this->apiKey = Yii::$app->params['openrouter_api_key'] ?? '';
        
        if (empty($this->apiKey)) {
            throw new \yii\base\InvalidConfigException(
                'OpenRouter API key не настроен в параметрах приложения. ' .
                'Добавьте параметр openrouter_api_key в config/params.php'
            );
        }
        
        $this->baseUrl = Yii::$app->params['openrouter_base_url'] ?? $this->baseUrl;
        $this->model = Yii::$app->params['openrouter_default_model'] ?? $this->model;
        $this->defaultModel = $this->model;
        $this->referer = Yii::$app->params['openrouter_referer'] ?? $this->referer;
        $this->appTitle = Yii::$app->params['openrouter_app_title'] ?? $this->appTitle;
        $this->timeout = Yii::$app->params['openrouter_timeout'] ?? $this->timeout;
    }
    
    /**
     * Получить похожие объявления из БД для сравнения
     * 
     * @param Advertisement $advertisement Объявление для поиска аналогов
     * @param int $limit Максимальное количество аналогов
     * @return array Массив объявлений-аналогов
     */
    private function getSimilarAdvertisements(Advertisement $advertisement, int $limit = 10): array
    {
        $cacheKey = $advertisement->id . '_' . $limit;
        if (isset($this->advertisementsCache[$cacheKey])) {
            return $this->advertisementsCache[$cacheKey];
        }
        
        $query = Advertisement::find()
            ->where(['status' => Advertisement::STATUS_ACTIVE])
            ->andWhere(['section' => Advertisement::SECTION_SELL])
            ->andWhere(['type' => $advertisement->type])
            ->andWhere(['<>', 'advertisements.id', $advertisement->id]) // ✅ Указываем полное имя таблицы
            ->with(['glider', 'harness', 'device', 'user'])
            ->limit($limit);
        
        // Для glider добавляем дополнительные фильтры
        if ($advertisement->type === Advertisement::TYPE_GLIDER && $advertisement->glider) {
            $glider = $advertisement->glider;
            
            $query->innerJoin('advertisement_glider', 'advertisement_glider.advertisement_id = advertisements.id');
            
            // Приоритет 1: Сертификация
            if ($glider->certification_id) {
                $query->andWhere(['advertisement_glider.certification_id' => $glider->certification_id]);
            }
            
            // Приоритет 2: Год выпуска (с разбросом +/- 3 года)
            if ($glider->date_release) {
                $year = (int)$glider->date_release;
                $query->andWhere(['between', 'advertisement_glider.date_release', $year - 3, $year + 3]);
            }
            
            // Приоритет 3: Производитель
            if ($glider->producer_id) {
                $query->andWhere(['advertisement_glider.producer_id' => $glider->producer_id]);
            }
            
            // Приоритет 4: Состояние
            if ($glider->condition) {
                $query->andWhere(['advertisement_glider.condition' => $glider->condition]);
            }
            
            // Приоритет 5: Весовая вилка (с разбросом +/- 10 кг)
            if ($glider->weight_min && $glider->weight_max) {
                $min = $glider->weight_min - 10;
                $max = $glider->weight_max + 10;
                $query->andWhere(['between', 'advertisement_glider.weight_min', $min, $max]);
                $query->andWhere(['between', 'advertisement_glider.weight_max', $min, $max]);
            }
        }
        
        // Для harness
        if ($advertisement->type === Advertisement::TYPE_HARNESS && $advertisement->harness) {
            $harness = $advertisement->harness;
            
            $query->innerJoin('advertisement_harness', 'advertisement_harness.advertisement_id = advertisements.id');
            
            if ($harness->producer_id) {
                $query->andWhere(['advertisement_harness.producer_id' => $harness->producer_id]);
            }
            
            if ($harness->size) {
                $query->andWhere(['advertisement_harness.size' => $harness->size]);
            }
            
            if ($harness->condition) {
                $query->andWhere(['advertisement_harness.condition' => $harness->condition]);
            }
            
            if ($harness->date_release) {
                $year = (int)$harness->date_release;
                $query->andWhere(['between', 'advertisement_harness.date_release', $year - 2, $year + 2]);
            }
        }
        
        // Для device
        if ($advertisement->type === Advertisement::TYPE_DEVICE && $advertisement->device) {
            $device = $advertisement->device;
            
            $query->innerJoin('advertisement_device', 'advertisement_device.advertisement_id = advertisements.id');
            
            if ($device->producer_id) {
                $query->andWhere(['advertisement_device.producer_id' => $device->producer_id]);
            }
            
            if ($device->condition) {
                $query->andWhere(['advertisement_device.condition' => $device->condition]);
            }
        }
        
        // Выполняем запрос
        $similar = $query->all();
        
        // Если ничего не найдено, пробуем получить просто похожие по типу
        if (empty($similar)) {
            // Fallback: просто объявления того же типа
            $query = Advertisement::find()
                ->where(['status' => Advertisement::STATUS_ACTIVE])
                ->andWhere(['section' => Advertisement::SECTION_SELL])
                ->andWhere(['type' => $advertisement->type])
                ->andWhere(['<>', 'advertisements.id', $advertisement->id]) // ✅ Указываем полное имя таблицы
                ->with(['glider', 'harness', 'device', 'user'])
                ->limit($limit);
            
            $similar = $query->all();
        }
        
        $this->advertisementsCache[$cacheKey] = $similar;
        return $similar;
    }
    
    /**
     * Формирует промпт для AI на основе объявления и аналогов
     * 
     * @param Advertisement $advertisement Оцениваемое объявление
     * @param array $similar Аналоги из БД
     * @param string $context Контекст/тема
     * @return string Сформированный промпт
     */
    private function buildPrompt(Advertisement $advertisement, array $similar, string $context): string
    {
        // 1. Информация об оцениваемом объявлении
        $targetInfo = $this->formatAdvertisementInfo($advertisement);
        
        // 2. Информация об аналогах
        $similarInfo = '';
        if (!empty($similar)) {
            $similarInfo = "\n\n--- АНАЛОГИЧНЫЕ ОБЪЯВЛЕНИЯ ИЗ БАЗЫ ДАННЫХ (для сравнения): ---\n";
            foreach ($similar as $index => $similarAd) {
                $similarInfo .= "\n" . ($index + 1) . ". " . $this->formatAdvertisementInfo($similarAd);
            }
        } else {
            $similarInfo = "\n\n--- ВНИМАНИЕ: В БАЗЕ НЕТ АНАЛОГИЧНЫХ ОБЪЯВЛЕНИЙ. ---\nОценивайте на основе рыночной логики и здравого смысла.\n";
        }
        
        // 3. Инструкции для оценки (с приоритетами)
        $instructions = $this->getRatingInstructions($advertisement->type);
        
        // 4. Собираем полный промпт
        $prompt = $instructions . "\n\n" .
                  "--- ОЦЕНИВАЕМОЕ ОБЪЯВЛЕНИЕ: ---\n" .
                  $targetInfo .
                  $similarInfo . "\n\n" .
                  "--- ТРЕБОВАНИЯ К ОТВЕТУ: ---\n" .
                  "Верни ответ строго в формате JSON со следующей структурой:\n" .
                  "{\n" .
                  "    \"fair_price\": число (рекомендуемая справедливая цена в рублях),\n" .
                  "    \"price_range\": {\n" .
                  "        \"min\": число (минимальная цена),\n" .
                  "        \"max\": число (максимальная цена)\n" .
                  "    },\n" .
                  "    \"confidence\": число от 1 до 10 (уверенность в оценке),\n" .
                  "    \"appeal\": число от 1 до 10 (привлекательность объявления),\n" .
                  "    \"clarity\": число от 1 до 10 (ясность описания),\n" .
                  "    \"relevance\": число от 1 до 10 (соответствие рыночной ситуации),\n" .
                  "    \"call_to_action\": число от 1 до 10 (призыв к действию),\n" .
                  "    \"pros\": [\"плюс 1\", \"плюс 2\", ...],\n" .
                  "    \"cons\": [\"минус 1\", \"минус 2\", ...],\n" .
                  "    \"recommendations\": \"строка с рекомендациями по цене и улучшению объявления\",\n" .
                  "    \"market_analysis\": \"строка с анализом рынка на основе аналогов\"\n" .
                  "}\n\n" .
                  "Важно: fair_price и price_range должны быть основаны на анализе аналогов из БД.\n" .
                  "Если аналогов нет, используй рыночную логику и указывай это в market_analysis.";
        
        return $prompt;
    }
    
    /**
     * Форматирует информацию об объявлении для промпта
     */
    private function formatAdvertisementInfo(Advertisement $ad): string
    {
        $info = "ID: #{$ad->id}\n";
        $info .= "Заголовок: {$ad->title}\n";
        $info .= "Цена: " . ($ad->price ? number_format($ad->price, 0, '.', ' ') . ' ₽' : 'не указана') . "\n";
        $info .= "Город: " . ($ad->city ?: 'не указан') . "\n";
        $info .= "Описание: " . ($ad->description ?: 'не указано') . "\n";
        
        // Добавляем информацию о типе
        $typeObject = $ad->getTypeObject();
        if ($typeObject) {
            $info .= "\n--- ХАРАКТЕРИСТИКИ ТОВАРА: ---\n";
            
            if ($typeObject instanceof AdvertisementGlider) {
                $info .= "Тип: Параплан\n";
                $info .= "Модель: " . ($typeObject->model ?: 'не указана') . "\n";
                
                $producer = $typeObject->producer;
                $info .= "Производитель: " . ($producer ? $producer->name : 'не указан') . "\n";
                
                $cert = $typeObject->certification;
                $info .= "Сертификация: " . ($cert ? $cert->name : 'не указана') . "\n";
                
                $info .= "Весовая вилка: " . 
                    ($typeObject->weight_min && $typeObject->weight_max 
                        ? "{$typeObject->weight_min} - {$typeObject->weight_max} кг" 
                        : 'не указана') . "\n";
                
                $info .= "Год выпуска: " . ($typeObject->date_release ?: 'не указан') . "\n";
                $info .= "Налёт: " . ($typeObject->flight_time ? $typeObject->flight_time . ' ч.' : 'не указан') . "\n";
                $info .= "Состояние: " . $this->getConditionLabel($typeObject->condition) . "\n";
                $info .= "Дефекты: " . ($typeObject->defects ?: 'не указаны') . "\n";
                if ($typeObject->cause) {
                    $info .= "Причина продажи: {$typeObject->cause}\n";
                }
            } elseif ($typeObject instanceof AdvertisementHarness) {
                $info .= "Тип: Подвесная система\n";
                $info .= "Модель: " . ($typeObject->model ?: 'не указана') . "\n";
                
                $producer = $typeObject->producer;
                $info .= "Производитель: " . ($producer ? $producer->name : 'не указан') . "\n";
                
                $info .= "Размер: " . ($typeObject->size ?: 'не указан') . "\n";
                $info .= "Год выпуска: " . ($typeObject->date_release ?: 'не указан') . "\n";
                $info .= "Состояние: " . $this->getConditionLabel($typeObject->condition) . "\n";
                $info .= "Дефекты: " . ($typeObject->defects ?: 'не указаны') . "\n";
            } elseif ($typeObject instanceof AdvertisementDevice) {
                $info .= "Тип: Прибор\n";
                $info .= "Модель: " . ($typeObject->model ?: 'не указана') . "\n";
                
                $producer = $typeObject->producer;
                $info .= "Производитель: " . ($producer ? $producer->name : 'не указан') . "\n";
                
                $info .= "Состояние: " . $this->getConditionLabel($typeObject->condition) . "\n";
                $info .= "Дефекты: " . ($typeObject->defects ?: 'не указаны') . "\n";
            }
        }
        
        return $info;
    }
    
    /**
     * Возвращает метку состояния на русском
     */
    private function getConditionLabel(?string $condition): string
    {
        if (!$condition) return 'не указано';
        
        $labels = [
            'new' => 'Новый',
            'excellent' => 'Отличное',
            'good' => 'Хорошее',
            'fair' => 'Удовлетворительное',
            'bad' => 'Плохое',
        ];
        
        return $labels[$condition] ?? $condition;
    }
    
    /**
     * Инструкции для оценки в зависимости от типа
     */
    private function getRatingInstructions(string $type): string
    {
        $baseInstructions = "Ты - эксперт по оценке стоимости подержанного парапланерного снаряжения. " .
                           "Твоя задача - оценить справедливую рыночную цену объявления на основе " .
                           "реальных данных из базы данных аналогичных объявлений.\n\n" .
                           "Учитывай следующие факторы при оценке:\n";
        
        if ($type === Advertisement::TYPE_GLIDER) {
            $baseInstructions .= "1. Сертификация (EN A, EN B, EN C, EN D, CCC) - САМЫЙ ВАЖНЫЙ ФАКТОР!\n" .
                                 "2. Год выпуска - чем новее, тем дороже (износ материалов)\n" .
                                 "3. Производитель (бренд) - влияет на цену\n" .
                                 "4. Состояние (новый, отличное, хорошее, удовлетворительное, плохое)\n" .
                                 "5. Наличие/отсутствие дефектов (ремонты, повреждения)\n" .
                                 "6. Весовая вилка - популярные веса ценятся выше\n" .
                                 "7. Налёт (часы) - чем меньше, тем дороже\n" .
                                 "8. Комплектация (чехол, ремкомплект, шнуровка и т.д.)\n";
        } elseif ($type === Advertisement::TYPE_HARNESS) {
            $baseInstructions .= "1. Производитель (бренд) - САМЫЙ ВАЖНЫЙ ФАКТОР!\n" .
                                 "2. Состояние (новый, отличное, хорошее, удовлетворительное, плохое)\n" .
                                 "3. Год выпуска - чем новее, тем дороже\n" .
                                 "4. Размер - популярные размеры ценятся выше\n" .
                                 "5. Наличие/отсутствие дефектов (износ, повреждения)\n" .
                                 "6. Модель и её актуальность\n";
        } elseif ($type === Advertisement::TYPE_DEVICE) {
            $baseInstructions .= "1. Производитель (бренд) - САМЫЙ ВАЖНЫЙ ФАКТОР!\n" .
                                 "2. Состояние (новый, отличное, хорошее, удовлетворительное, плохое)\n" .
                                 "3. Модель и её актуальность (функционал)\n" .
                                 "4. Наличие/отсутствие дефектов\n" .
                                 "5. Полнота комплектации (кабель, датчики и т.д.)\n";
        }
        
        $baseInstructions .= "\nСравни цену оцениваемого объявления с ценами аналогов из БД.\n" .
                             "Если цена значительно выше/ниже рыночной - укажи это в рекомендациях.\n" .
                             "Будь объективен и используй данные из БД для обоснования оценки.";
        
        return $baseInstructions;
    }
    
    /**
     * Отправляет запрос к OpenRouter API
     * 
     * @param array $messages Массив сообщений для чата
     * @param array $options Дополнительные параметры
     * @return array Ответ от API
     * @throws \yii\web\ServerErrorHttpException При ошибке API
     */
    public function callOpenRouter(array $messages, array $options = []): array
    {
        $requestBody = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'response_format' => ['type' => 'json_object'],
        ], $options);
        
        Yii::info([
            'url' => $this->baseUrl . '/chat/completions',
            'method' => 'POST',
            'body' => $requestBody,
        ], 'openrouter.request');
        
        try {
            $client = new Client([
                'baseUrl' => $this->baseUrl,
                'transport' => 'yii\httpclient\CurlTransport',
                'requestConfig' => [
                    'options' => [
                        'timeout' => $this->timeout,
                    ],
                ],
            ]);
            
            $request = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('chat/completions')
                ->setFormat(Client::FORMAT_JSON)
                ->setData($requestBody)
                ->setHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'HTTP-Referer' => $this->referer,
                    'X-Title' => $this->appTitle,
                ]);
            
            Yii::info($request->getContent(), 'openrouter.raw_json');
            
            $response = $request->send();
            
            if (!$response->isOk) {
                $errorBody = $response->getContent();
                $errorData = json_decode($errorBody, true);
                $errorMessage = $errorData['error']['message'] ?? $errorBody ?? 'Неизвестная ошибка';
                
                Yii::error([
                    'status' => $response->getStatusCode(),
                    'body' => $errorBody,
                    'headers' => $response->getHeaders()->toArray(),
                ], 'openrouter.error');
                
                throw new \yii\web\ServerErrorHttpException(
                    'Ошибка OpenRouter API: ' . $errorMessage
                );
            }
            
            $data = $response->getData();
            
            Yii::info([
                'model' => $data['model'] ?? 'unknown',
                'usage' => $data['usage'] ?? null,
            ], 'openrouter.success');
            
            return $data;
            
        } catch (\yii\httpclient\Exception $e) {
            Yii::error('HTTP клиент ошибка: ' . $e->getMessage(), 'openrouter.http_error');
            throw new \yii\web\ServerErrorHttpException('Ошибка соединения с OpenRouter API: ' . $e->getMessage());
        } catch (\yii\base\Exception $e) {
            Yii::error('Общая ошибка: ' . $e->getMessage(), 'openrouter.general_error');
            throw $e;
        }
    }
    
    /**
     * Оценивает рекламное объявление на основе БД
     * 
     * @param Advertisement $advertisement Объявление для оценки
     * @param string $context Контекст/тема
     * @return array|null Результат оценки или null при ошибке
     */
    public function rateAdvertisement(Advertisement $advertisement, string $context): ?array
    {
        try {
            // Получаем аналоги из БД
            $similar = $this->getSimilarAdvertisements($advertisement, 10);
            
            // Формируем промпт
            $prompt = $this->buildPrompt($advertisement, $similar, $context);
            
            $messages = [
                ['role' => 'system', 'content' => 'Ты - эксперт по оценке стоимости подержанного парапланерного снаряжения. Отвечай строго в формате JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ];
            
            $response = $this->callOpenRouter($messages, [
                'max_tokens' => 1500,
                'temperature' => 0.3,
            ]);
            
            $content = $response['choices'][0]['message']['content'] ?? null;
            
            if (!$content) {
                Yii::error('Пустой ответ от OpenRouter', 'advertisement.empty_response');
                return null;
            }
            
            // Парсим JSON ответ
            $result = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Yii::error('Ошибка парсинга JSON от AI: ' . json_last_error_msg(), 'advertisement.parse_error');
                Yii::info('Raw ответ: ' . $content, 'advertisement.raw_response');
                return $this->parseRatingResponse($content);
            }
            
            // Проверяем наличие всех полей
            $requiredFields = ['fair_price', 'price_range', 'confidence', 'appeal', 'clarity', 'relevance', 'call_to_action'];
            foreach ($requiredFields as $field) {
                if (!isset($result[$field])) {
                    Yii::error("Отсутствует поле '$field' в ответе", 'advertisement.missing_field');
                    return $this->parseRatingResponse($content);
                }
            }
            
            // Валидируем числовые значения
            $result['fair_price'] = max(0, (int)$result['fair_price']);
            $result['price_range']['min'] = max(0, (int)$result['price_range']['min']);
            $result['price_range']['max'] = max(0, (int)$result['price_range']['max']);
            
            foreach (['appeal', 'clarity', 'relevance', 'call_to_action', 'confidence'] as $field) {
                $result[$field] = min(10, max(1, (int)$result[$field]));
            }
            
            // Преобразуем pros и cons в строки, если они массивы
            if (isset($result['pros']) && is_array($result['pros'])) {
                $result['pros'] = implode("\n", $result['pros']);
            }
            if (isset($result['cons']) && is_array($result['cons'])) {
                $result['cons'] = implode("\n", $result['cons']);
            }
            
            return $result;
            
        } catch (\yii\web\ServerErrorHttpException $e) {
            Yii::error('Ошибка оценки: ' . $e->getMessage(), 'advertisement.rating_error');
            return null;
        }
    }
    
    /**
     * Парсит ответ от AI в структурированный массив (fallback)
     */
    private function parseRatingResponse(string $content): ?array
    {
        $result = [
            'fair_price' => 0,
            'price_range' => ['min' => 0, 'max' => 0],
            'confidence' => 5,
            'appeal' => 5,
            'clarity' => 5,
            'relevance' => 5,
            'call_to_action' => 5,
            'pros' => '',
            'cons' => '',
            'recommendations' => '',
            'market_analysis' => '',
            'raw_response' => $content,
        ];
        
        // Извлекаем цену
        if (preg_match('/fair_price["\s:]+(\d+)/i', $content, $matches)) {
            $result['fair_price'] = (int)$matches[1];
        }
        
        // Извлекаем диапазон цен
        if (preg_match('/price_range["\s:]+.*?min["\s:]+(\d+).*?max["\s:]+(\d+)/is', $content, $matches)) {
            $result['price_range']['min'] = (int)$matches[1];
            $result['price_range']['max'] = (int)$matches[2];
        }
        
        // Извлекаем оценки
        $scorePatterns = [
            'confidence' => '/confidence["\s:]+(\d+)/i',
            'appeal' => '/appeal["\s:]+(\d+)/i',
            'clarity' => '/clarity["\s:]+(\d+)/i',
            'relevance' => '/relevance["\s:]+(\d+)/i',
            'call_to_action' => '/call_to_action["\s:]+(\d+)/i',
        ];
        
        foreach ($scorePatterns as $key => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $result[$key] = min(10, max(1, (int)$matches[1]));
            }
        }
        
        // Извлекаем рекомендации
        if (preg_match('/recommendations["\s:]+"(.+?)"/is', $content, $matches)) {
            $result['recommendations'] = trim($matches[1]);
        } elseif (preg_match('/рекомендации["\s:]+"(.+?)"/is', $content, $matches)) {
            $result['recommendations'] = trim($matches[1]);
        }
        
        // Извлекаем анализ рынка
        if (preg_match('/market_analysis["\s:]+"(.+?)"/is', $content, $matches)) {
            $result['market_analysis'] = trim($matches[1]);
        }
        
        // Извлекаем плюсы и минусы
        if (preg_match('/pros["\s:]+\[(.*?)\]/is', $content, $matches)) {
            $items = array_map('trim', explode(',', $matches[1]));
            $result['pros'] = implode("\n", array_filter($items));
        }
        if (preg_match('/cons["\s:]+\[(.*?)\]/is', $content, $matches)) {
            $items = array_map('trim', explode(',', $matches[1]));
            $result['cons'] = implode("\n", array_filter($items));
        }
        
        // Валидируем
        if ($result['fair_price'] > 0) {
            return $result;
        }
        
        return null;
    }
    
    /**
     * Устанавливает модель для использования
     */
    public function setModel(string $model): self
    {
        if (in_array($model, $this->availableModels)) {
            $this->model = $model;
            $this->defaultModel = $model;
        } else {
            Yii::warning("Модель '$model' не найдена в списке доступных", 'openrouter.model_warning');
        }
        return $this;
    }
    
    /**
     * Возвращает текущую модель
     */
    public function getModel(): string
    {
        return $this->model;
    }
    
    /**
     * Возвращает список доступных моделей
     */
    public function getAvailableModels(): array
    {
        return $this->availableModels;
    }
}