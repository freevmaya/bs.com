<?php
// FILE: .\controllers\AdvertisementsController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\UploadedFile;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use app\models\Advertisement;
use app\models\AdvertisementSearch;
use app\models\AdvertisementImage;
use app\models\AdvertisementGlider;
use app\models\AdvertisementHarness;
use app\models\AdvertisementDevice;
use app\models\Producer;
use app\models\Certification;
use app\models\SearchSubscription;
use app\components\TempAdStorage;

class AdvertisementsController extends Controller
{
    private $tempStorage;
    
    public function init()
    {
        parent::init();
        $this->tempStorage = new TempAdStorage();
    }
    
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['create', 'update', 'delete', 'my', 'add-image', 'delete-image', 'reorder-images', 'add-temp-image', 'delete-temp-image'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                    'delete-image' => ['POST'],
                    'delete-temp-image' => ['POST'],
                    'reorder-images' => ['POST'],
                ],
            ],
        ];
    }
    
    public function actionIndex()
    {
        $searchModel = new AdvertisementSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);
        
        return $this->render('index', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
        ]);
    }
    
    public function actionSell()
    {
        $searchModel = new AdvertisementSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            Advertisement::SECTION_SELL
        );
        
        return $this->render('section', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'section' => Advertisement::SECTION_SELL,
            'sectionTitle' => 'Продам',
        ]);
    }
    
    public function actionBuy()
    {
        $searchModel = new AdvertisementSearch();
        $dataProvider = $searchModel->search(
            Yii::$app->request->queryParams,
            Advertisement::SECTION_BUY
        );
        
        return $this->render('section', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'section' => Advertisement::SECTION_BUY,
            'sectionTitle' => 'Куплю',
        ]);
    }
    
    public function actionView($id)
    {
        $model = $this->findModel($id);
        $model->incrementViews();
        
        return $this->render('view', [
            'model' => $model,
        ]);
    }
    
    public function actionCreate($section = null)
    {
        $model = new Advertisement();
        $model->user_id = Yii::$app->user->id;
        $model->status = Advertisement::STATUS_MODERATION;
        
        if ($section && in_array($section, [Advertisement::SECTION_SELL, Advertisement::SECTION_BUY])) {
            $model->section = $section;
        }
        
        // Генерируем временный ID для изображений
        $tempId = Yii::$app->session->get('temp_ad_id');
        if (!$tempId) {
            $tempId = $this->tempStorage->generateTempId();
            Yii::$app->session->set('temp_ad_id', $tempId);
        }
        
        $tempImages = $this->tempStorage->getTempImages($tempId);
        
        // Создаем дополнительные модели
        $gliderModel = new AdvertisementGlider();
        $harnessModel = new AdvertisementHarness();
        $deviceModel = new AdvertisementDevice();
        
        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                // Сохраняем дополнительные данные в зависимости от типа
                if ($this->saveExtraFields($model, Yii::$app->request->post())) {
                    // Переносим изображения
                    $migratedCount = $this->tempStorage->migrateImages($tempId, $model->id);
                    
                    // Отправляем уведомления подписчикам
                    $this->notifySubscribers($model);
                    
                    Yii::$app->session->setFlash('success', 'Объявление создано. Загружено фотографий: ' . $migratedCount);
                    Yii::$app->session->remove('temp_ad_id');
                    return $this->redirect(['view', 'id' => $model->id]);
                }
            }
        }
        
        return $this->render('create', [
            'model' => $model,
            'gliderModel' => $gliderModel,
            'harnessModel' => $harnessModel,
            'deviceModel' => $deviceModel,
            'tempId' => $tempId,
            'tempImages' => $tempImages,
        ]);
    }
    
    public function actionUpdate($id)
    {
        $model = $this->findModel($id);
        
        if ($model->user_id !== Yii::$app->user->id && !Yii::$app->user->can('admin')) {
            throw new ForbiddenHttpException('У вас нет прав для редактирования этого объявления');
        }
        
        // Загружаем дополнительные модели
        $gliderModel = $model->glider ?: new AdvertisementGlider();
        $harnessModel = $model->harness ?: new AdvertisementHarness();
        $deviceModel = $model->device ?: new AdvertisementDevice();
        
        // Если есть дополнительные данные, заполняем их
        if ($gliderModel->advertisement_id === null) {
            $gliderModel->advertisement_id = $model->id;
        }
        if ($harnessModel->advertisement_id === null) {
            $harnessModel->advertisement_id = $model->id;
        }
        if ($deviceModel->advertisement_id === null) {
            $deviceModel->advertisement_id = $model->id;
        }
        
        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            Yii::info('Main model saved: ' . json_encode($model->attributes));
            
            // Получаем данные из POST для дополнительных полей
            $postData = Yii::$app->request->post();
            Yii::info('POST data for extra fields: ' . json_encode($postData));
            
            // Сохраняем дополнительные поля
            if ($this->saveExtraFields($model, $postData)) {
                Yii::$app->session->setFlash('success', 'Объявление обновлено');
                return $this->redirect(['view', 'id' => $model->id]);
            } else {
                Yii::$app->session->setFlash('error', 'Ошибка при сохранении дополнительных полей');
            }
        }
        
        return $this->render('update', [
            'model' => $model,
            'gliderModel' => $gliderModel,
            'harnessModel' => $harnessModel,
            'deviceModel' => $deviceModel,
        ]);
    }

    private function saveExtraFields($advertisement, $postData)
    {
        $type = $advertisement->type;
        
        Yii::info('Saving extra fields for type: ' . $type);
        Yii::info('POST data: ' . json_encode($postData));
        
        switch ($type) {
            case Advertisement::TYPE_GLIDER:
                if (isset($postData['AdvertisementGlider'])) {
                    $extraModel = $advertisement->glider ?: new AdvertisementGlider();
                    $extraModel->advertisement_id = $advertisement->id;
                    
                    $extraModel->setAttributes($postData['AdvertisementGlider']);
                    $extraModel->advertisement_id = $advertisement->id;
                    
                    if (isset($postData['AdvertisementGlider']['certification_id']) && $postData['AdvertisementGlider']['certification_id'] === '') {
                        $extraModel->certification_id = null;
                    }
                    
                    if (empty($extraModel->condition)) {
                        $extraModel->condition = AdvertisementGlider::CONDITION_GOOD;
                    }
                    
                    Yii::info('Glider attributes before save: ' . json_encode($extraModel->attributes));
                    
                    if ($extraModel->validate()) {
                        if ($extraModel->save()) {
                            Yii::info('Glider saved successfully');
                            return true;
                        } else {
                            Yii::error('Failed to save glider: ' . json_encode($extraModel->errors));
                            return false;
                        }
                    } else {
                        Yii::error('Validation errors for glider: ' . json_encode($extraModel->errors));
                        return false;
                    }
                } else {
                    Yii::error('AdvertisementGlider not found in POST data');
                    return false;
                }
                
            case Advertisement::TYPE_HARNESS:
                if (isset($postData['AdvertisementHarness'])) {
                    $extraModel = $advertisement->harness ?: new AdvertisementHarness();
                    $extraModel->setAttributes($postData['AdvertisementHarness']);
                    $extraModel->advertisement_id = $advertisement->id;
                    
                    if (empty($extraModel->condition)) {
                        $extraModel->condition = AdvertisementHarness::CONDITION_GOOD;
                    }
                    
                    Yii::info('Harness attributes before save: ' . json_encode($extraModel->attributes));
                    
                    if ($extraModel->validate()) {
                        if ($extraModel->save()) {
                            Yii::info('Harness saved successfully');
                            return true;
                        } else {
                            Yii::error('Failed to save harness: ' . json_encode($extraModel->errors));
                            return false;
                        }
                    } else {
                        Yii::error('Validation errors for harness: ' . json_encode($extraModel->errors));
                        return false;
                    }
                } else {
                    Yii::error('AdvertisementHarness not found in POST data');
                    return false;
                }
                
            case Advertisement::TYPE_DEVICE:
                if (isset($postData['AdvertisementDevice'])) {
                    $extraModel = $advertisement->device ?: new AdvertisementDevice();
                    $extraModel->setAttributes($postData['AdvertisementDevice']);
                    $extraModel->advertisement_id = $advertisement->id;
                    
                    if (empty($extraModel->condition)) {
                        $extraModel->condition = AdvertisementDevice::CONDITION_GOOD;
                    }
                    
                    Yii::info('Device attributes before save: ' . json_encode($extraModel->attributes));
                    
                    if ($extraModel->validate()) {
                        if ($extraModel->save()) {
                            Yii::info('Device saved successfully');
                            return true;
                        } else {
                            Yii::error('Failed to save device: ' . json_encode($extraModel->errors));
                            return false;
                        }
                    } else {
                        Yii::error('Validation errors for device: ' . json_encode($extraModel->errors));
                        return false;
                    }
                } else {
                    Yii::error('AdvertisementDevice not found in POST data');
                    return false;
                }
                
            default:
                return true;
        }
    }
    
    public function actionDelete($id)
    {
        $model = $this->findModel($id);
        
        if ($model->user_id !== Yii::$app->user->id && !Yii::$app->user->can('admin')) {
            throw new ForbiddenHttpException('У вас нет прав для удаления этого объявления');
        }
        
        $model->delete();
        Yii::$app->session->setFlash('success', 'Объявление удалено');
        
        return $this->redirect(['index']);
    }
    
    public function actionMy()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => Advertisement::find()->where(['user_id' => Yii::$app->user->id]),
            'sort' => ['defaultOrder' => ['created_at' => SORT_DESC]],
            'pagination' => ['pageSize' => 20],
        ]);
        
        return $this->render('my', [
            'dataProvider' => $dataProvider,
        ]);
    }

    public function actionAddImage($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $model = $this->findModel($id);
        
        if ($model->user_id !== Yii::$app->user->id && !Yii::$app->user->can('admin')) {
            return ['success' => false, 'error' => 'У вас нет прав для добавления изображений'];
        }
        
        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }
        
        $uploadedFile = \yii\web\UploadedFile::getInstanceByName('imageFile');
        if (!$uploadedFile) {
            return ['success' => false, 'error' => 'Файл не выбран'];
        }
        
        $image = new \app\models\AdvertisementImage();
        $image->advertisement_id = $model->id;
        $image->imageFile = $uploadedFile;
        
        if ($image->upload()) {
            $image->sort_order = $model->getImages()->count();
            $image->save(false);
            
            $thumbnailUrl = $image->getThumbnailUrl();
            $deleteUrl = \yii\helpers\Url::to(['delete-image-ajax', 'id' => $image->id]);
            
            return [
                'success' => true,
                'thumbnailUrl' => $thumbnailUrl,
                'imageId' => $image->id,
                'deleteUrl' => $deleteUrl,
                'isVideo' => $image->isVideo(),
                'message' => 'Файл успешно загружен'
            ];
        } else {
            $errors = $image->getErrors();
            return ['success' => false, 'error' => 'Ошибка при загрузке: ' . json_encode($errors)];
        }
    }

    public function actionDeleteImageAjax($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $image = \app\models\AdvertisementImage::findOne($id);
        if (!$image) {
            return ['success' => false, 'error' => 'Изображение не найдено'];
        }
        
        $advertisement = $image->advertisement;
        if ($advertisement->user_id !== Yii::$app->user->id && !Yii::$app->user->can('admin')) {
            return ['success' => false, 'error' => 'У вас нет прав для удаления этого изображения'];
        }
        
        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }
        
        if ($image->delete()) {
            return ['success' => true, 'message' => 'Изображение удалено'];
        }
        
        return ['success' => false, 'error' => 'Ошибка при удалении'];
    }
    
    protected function findModel($id)
    {
        if (($model = Advertisement::findOne($id)) !== null) {
            return $model;
        }
        throw new NotFoundHttpException('Объявление не найдено');
    }

    public function actionAddTempImage($tempId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }
        
        $sessionTempId = Yii::$app->session->get('temp_ad_id');
        if (!$sessionTempId || $sessionTempId != $tempId) {
            return ['success' => false, 'error' => 'Неверный идентификатор сессии'];
        }
        
        $uploadedFile = \yii\web\UploadedFile::getInstanceByName('imageFile');
        if (!$uploadedFile) {
            return ['success' => false, 'error' => 'Файл не выбран'];
        }
        
        $image = new \app\models\AdvertisementImage();
        $image->imageFile = $uploadedFile;
        
        if ($image->uploadTemp($tempId)) {
            $imageData = [
                'file_name' => $image->file_name,
                'file_path' => $image->file_path,
                'thumbnail_path' => $image->thumbnail_path,
                'sort_order' => count($this->tempStorage->getTempImages($tempId)),
            ];
            
            $this->tempStorage->addTempImage($tempId, $imageData);
            
            $tempImages = $this->tempStorage->getTempImages($tempId);
            $currentIndex = count($tempImages) - 1;
            
            $thumbnailUrl = Yii::getAlias('@web/uploads/advertisements/' . $image->thumbnail_path);
            
            return [
                'success' => true,
                'thumbnailUrl' => $thumbnailUrl,
                'index' => $currentIndex,
                'message' => 'Изображение успешно загружено'
            ];
        } else {
            $errors = $image->getErrors();
            return ['success' => false, 'error' => 'Ошибка при загрузке: ' . json_encode($errors)];
        }
    }

    public function actionDeleteTempImageAjax($tempId, $index)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }
        
        $sessionTempId = Yii::$app->session->get('temp_ad_id');
        if ($sessionTempId != $tempId) {
            return ['success' => false, 'error' => 'Неверный идентификатор сессии'];
        }
        
        $tempImages = $this->tempStorage->getTempImages($tempId);
        
        if (isset($tempImages[$index])) {
            $this->tempStorage->deleteTempFiles($tempImages[$index]);
            
            unset($tempImages[$index]);
            $this->tempStorage->saveTempAd($tempId, ['images' => array_values($tempImages)]);
            
            return ['success' => true, 'message' => 'Изображение удалено'];
        }
        
        return ['success' => false, 'error' => 'Изображение не найдено'];
    }

    /**
     * AJAX валидация формы
     */
    public function beforeAction($action)
    {
        $isAjaxValidate = Yii::$app->request->isAjax && Yii::$app->request->get('validate');
        
        if (($action->id == 'create' || $action->id == 'update') && $isAjaxValidate) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            
            if ($action->id == 'create') {
                $model = new Advertisement();
                $model->user_id = Yii::$app->user->id;
                $model->status = Advertisement::STATUS_MODERATION;
            } else {
                $id = Yii::$app->request->get('id');
                $model = $this->findModel($id);
            }
            
            $model->load(Yii::$app->request->post());
            
            $errors = [];
            $invalidFields = [];
            
            if (!$model->validate()) {
                foreach ($model->errors as $field => $fieldErrors) {
                    $errors[$field] = implode(', ', $fieldErrors);
                    $invalidFields[] = 'Advertisement[' . $field . ']';
                }
            }
            
            $type = $model->type;
            if ($type === 'glider') {
                $gliderModel = new AdvertisementGlider();
                $gliderModel->load(Yii::$app->request->post());
                if (!$gliderModel->validate()) {
                    foreach ($gliderModel->errors as $field => $fieldErrors) {
                        $errors['glider_' . $field] = 'Параплан: ' . implode(', ', $fieldErrors);
                        $invalidFields[] = 'AdvertisementGlider[' . $field . ']';
                    }
                }
            } elseif ($type === 'harness') {
                $harnessModel = new AdvertisementHarness();
                $harnessModel->load(Yii::$app->request->post());
                if (!$harnessModel->validate()) {
                    foreach ($harnessModel->errors as $field => $fieldErrors) {
                        $errors['harness_' . $field] = 'Подвеска: ' . implode(', ', $fieldErrors);
                        $invalidFields[] = 'AdvertisementHarness[' . $field . ']';
                    }
                }
            } elseif ($type === 'device') {
                $deviceModel = new AdvertisementDevice();
                $deviceModel->load(Yii::$app->request->post());
                if (!$deviceModel->validate()) {
                    foreach ($deviceModel->errors as $field => $fieldErrors) {
                        $errors['device_' . $field] = 'Прибор: ' . implode(', ', $fieldErrors);
                        $invalidFields[] = 'AdvertisementDevice[' . $field . ']';
                    }
                }
            }
            
            if (empty($errors)) {
                return ['success' => true];
            } else {
                return [
                    'success' => false,
                    'errors' => $errors,
                    'invalidFields' => $invalidFields,
                    'message' => 'Пожалуйста, исправьте ошибки в форме'
                ];
            }
        }
        
        return parent::beforeAction($action);
    }

    /**
     * Сортировка временных изображений
     */
    public function actionReorderTempImages($tempId)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }
        
        $sessionTempId = Yii::$app->session->get('temp_ad_id');
        if ($sessionTempId != $tempId) {
            return ['success' => false, 'error' => 'Неверный идентификатор сессии'];
        }
        
        if (Yii::$app->request->isPost) {
            $orders = Yii::$app->request->post('orders');
            
            if (!is_array($orders) || empty($orders)) {
                return ['success' => false, 'error' => 'Нет данных для сортировки'];
            }
            
            $tempImages = $this->tempStorage->getTempImages($tempId);
            
            $newImages = [];
            foreach ($orders as $order) {
                $index = $order['id'];
                $position = $order['position'];
                if (isset($tempImages[$index])) {
                    $image = $tempImages[$index];
                    $image['sort_order'] = $position;
                    $newImages[$position] = $image;
                }
            }
            
            ksort($newImages);
            
            $this->tempStorage->saveTempAd($tempId, ['images' => array_values($newImages)]);
            
            return ['success' => true, 'message' => 'Порядок сохранен'];
        }
        
        return ['success' => false, 'error' => 'Неверный метод запроса'];
    }

    /**
     * Сортировка постоянных изображений
     */
    public function actionReorderImages()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }
        
        if (Yii::$app->request->isPost) {
            $orders = Yii::$app->request->post('orders');
            
            if (!is_array($orders) || empty($orders)) {
                return ['success' => false, 'error' => 'Нет данных для сортировки'];
            }
            
            $transaction = Yii::$app->db->beginTransaction();
            try {
                foreach ($orders as $order) {
                    $image = \app\models\AdvertisementImage::findOne($order['id']);
                    if ($image) {
                        $image->sort_order = $order['position'];
                        if (!$image->save()) {
                            throw new \Exception('Ошибка сохранения изображения ID ' . $order['id']);
                        }
                    }
                }
                $transaction->commit();
                return ['success' => true, 'message' => 'Порядок сохранен'];
            } catch (\Exception $e) {
                $transaction->rollBack();
                Yii::error('Reorder error: ' . $e->getMessage());
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }
        
        return ['success' => false, 'error' => 'Неверный метод запроса'];
    }

    /**
     * Отправка уведомлений подписчикам
     * Исправлено: учитываем все параметры подписки, включая дополнительные поля
     */
    protected function notifySubscribers($advertisement)
    {
        // Получаем все активные подписки для данного раздела
        $subscriptions = SearchSubscription::find()
            ->where([
                'section' => $advertisement->section,
                'is_active' => true,
            ])
            ->all();
        
        $notified = 0;
        
        foreach ($subscriptions as $subscription) {
            // Проверяем соответствие объявления параметрам подписки
            if ($subscription->matchesAdvertisement($advertisement)) {
                // Проверяем, не отправляли ли уже уведомление об этом объявлении
                $lastNotified = $subscription->last_notified_at;
                if ($lastNotified && $lastNotified > $advertisement->created_at - 3600) {
                    continue; // Не отправляем повторно в течение часа
                }
                
                $this->sendSubscriptionNotification($advertisement, $subscription);
                $notified++;
                
                $subscription->last_notified_at = time();
                $subscription->save(false);
            }
        }
        
        Yii::info("Notified {$notified} subscribers about new advertisement #{$advertisement->id}", 'search_subscription');
        return $notified;
    }

    /**
     * Отправка уведомления подписчику
     */
    protected function sendSubscriptionNotification($advertisement, $subscription)
    {
        $user = $subscription->user;
        if (!$user) return;
        
        $subject = "Новое объявление по вашей подписке: {$advertisement->title}";
        $message = $this->buildSubscriptionMessage($advertisement, $subscription);
        
        try {
            $manager = Yii::$app->notificationManager;
            $result = $manager->sendToUser(
                $user->id,
                'search_subscription',
                $subject,
                $message,
                ['html_body' => $this->buildSubscriptionHtmlMessage($advertisement, $subscription)]
            );
            
            Yii::info("Subscription notification sent to user {$user->id}", 'search_subscription');
        } catch (\Exception $e) {
            Yii::error("Failed to send subscription notification: " . $e->getMessage(), 'search_subscription');
        }
    }

    /**
     * Сборка текстового сообщения для подписчика
     */
    protected function buildSubscriptionMessage($advertisement, $subscription)
    {
        $parts = [
            "По вашей подписке появилось новое объявление!",
            "",
            "Заголовок: {$advertisement->title}",
            "Раздел: " . $advertisement->getSectionLabel(),
            "Цена: " . ($advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана'),
            "Город: " . ($advertisement->city ?: 'не указан'),
            "",
            "Ваши параметры подписки:",
            $subscription->getDescription(),
            "",
            "Ссылка: " . Yii::$app->urlManager->createAbsoluteUrl(['advertisements/view', 'id' => $advertisement->id]),
        ];
        
        return implode("\n", $parts);
    }

    /**
     * Сборка HTML сообщения для подписчика
     */
    protected function buildSubscriptionHtmlMessage($advertisement, $subscription)
    {
        $price = $advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана';
        $link = Yii::$app->urlManager->createAbsoluteUrl(['advertisements/view', 'id' => $advertisement->id]);
        $description = $subscription->getDescription();
        
        return "
            <html>
            <head><style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .price { font-size: 24px; color: #d9534f; font-weight: bold; }
                .params { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; padding: 15px; color: #6c757d; font-size: 12px; }
            </style></head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>📢 Новое объявление!</h2>
                    </div>
                    <div class='content'>
                        <h3>{$advertisement->title}</h3>
                        <p><strong>Раздел:</strong> {$advertisement->getSectionLabel()}</p>
                        <p class='price'>{$price}</p>
                        <p><strong>Город:</strong> " . ($advertisement->city ?: 'не указан') . "</p>
                        <p><strong>Описание:</strong></p>
                        <p>" . nl2br($advertisement->description ?: 'не указано') . "</p>
                        <div class='params'>
                            <strong>Ваши параметры подписки:</strong><br>
                            {$description}
                        </div>
                        <p style='margin-top: 20px;'>
                            <a href='{$link}' class='btn'>Посмотреть объявление</a>
                        </p>
                        <p style='margin-top: 10px; font-size: 12px; color: #6c757d;'>
                            Вы получили это уведомление, так как подписаны на параметры поиска.
                            <a href='" . Yii::$app->urlManager->createAbsoluteUrl(['search-subscription/index']) . "'>Управлять подписками</a>
                        </p>
                    </div>
                    <div class='footer'>
                        &copy; " . Yii::$app->name . " " . date('Y') . "
                    </div>
                </div>
            </body>
            </html>
        ";
    }
}