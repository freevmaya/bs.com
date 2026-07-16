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
use app\models\NotificationSubscription;
use app\models\NotificationLog;
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
                'only' => ['create', 'update', 'delete', 'my', 'add-image', 'delete-image', 'reorder-images', 'add-temp-image', 'delete-temp-image', 'toggle-status'],
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
                    'toggle-status' => ['POST'],
                ],
            ],
        ];
    }
    
    public function actionIndex()
    {
        $searchModel = new AdvertisementSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $dataProvider->query->with(['images', 'glider', 'harness', 'device']);
        
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
        
        $dataProvider->query->with(['images', 'glider', 'harness', 'device']);
        
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
        
        $dataProvider->query->with(['images', 'glider', 'harness', 'device']);
        
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
        $model->status = Advertisement::STATUS_ACTIVE;
        
        // Заполняем контакты из профиля пользователя
        $user = Yii::$app->user->identity;
        $model->fillContactsFromUser($user);
        
        if ($section && in_array($section, [Advertisement::SECTION_SELL, Advertisement::SECTION_BUY])) {
            $model->section = $section;
        }
        
        $tempId = Yii::$app->session->get('temp_ad_id');
        if (!$tempId) {
            $tempId = $this->tempStorage->generateTempId();
            Yii::$app->session->set('temp_ad_id', $tempId);
        }
        
        $tempImages = $this->tempStorage->getTempImages($tempId);
        
        $gliderModel = new AdvertisementGlider();
        $harnessModel = new AdvertisementHarness();
        $deviceModel = new AdvertisementDevice();
        
        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                if ($this->saveExtraFields($model, Yii::$app->request->post())) {
                    $migratedCount = $this->tempStorage->migrateImages($tempId, $model->id);
                    
                    // Отправляем уведомления подписчикам (событие: создание)
                    $this->notifySubscribers($model, 'create');
                    
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
        
        // Если контакты пустые - заполняем из профиля
        $user = Yii::$app->user->identity;
        if (empty($model->phone) || empty($model->email) || empty($model->telegram) || empty($model->vk_profile_url) || empty($model->whatsapp)) {
            $model->fillContactsFromUser($user);
        }
        
        $gliderModel = $model->glider ?: new AdvertisementGlider();
        $harnessModel = $model->harness ?: new AdvertisementHarness();
        $deviceModel = $model->device ?: new AdvertisementDevice();
        
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
            
            $postData = Yii::$app->request->post();
            Yii::info('POST data for extra fields: ' . json_encode($postData));
            
            if ($this->saveExtraFields($model, $postData)) {
                // Отправляем уведомления подписчикам (событие: обновление)
                $this->notifySubscribers($model, 'update');
                
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
    
    public function actionToggleStatus()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        $id = Yii::$app->request->post('id');
        $status = Yii::$app->request->post('status');
        
        if (!$id || !$status) {
            return ['success' => false, 'error' => 'Не указаны параметры'];
        }
        
        $model = $this->findModel($id);
        
        if ($model->user_id !== Yii::$app->user->id && !Yii::$app->user->can('admin')) {
            return ['success' => false, 'error' => 'У вас нет прав для изменения статуса этого объявления'];
        }
        
        if (!in_array($status, [Advertisement::STATUS_ACTIVE, Advertisement::STATUS_CLOSED])) {
            return ['success' => false, 'error' => 'Некорректный статус'];
        }
        
        $model->status = $status;
        if ($model->save()) {
            return [
                'success' => true, 
                'message' => 'Статус изменен на ' . $model->getStatusLabel()
            ];
        }
        
        return ['success' => false, 'error' => 'Ошибка при сохранении статуса'];
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
            $image->save();
            
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

    public function beforeAction($action)
    {
        $isAjaxValidate = Yii::$app->request->isAjax && Yii::$app->request->get('validate');
        
        if (($action->id == 'create' || $action->id == 'update') && $isAjaxValidate) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            
            if ($action->id == 'create') {
                $model = new Advertisement();
                $model->user_id = Yii::$app->user->id;
                $model->status = Advertisement::STATUS_ACTIVE;
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
     * Отправка уведомлений подписчикам (теперь только ставит в очередь)
     * 
     * @param Advertisement $advertisement Объявление
     * @param string $eventType Тип события: 'create' или 'update'
     * @return int Количество добавленных в очередь уведомлений
     */
    protected function notifySubscribers($advertisement, $eventType = 'create')
    {
        // Получаем все активные подписки для данного раздела
        $subscriptions = SearchSubscription::find()
            ->where([
                'section' => $advertisement->section,
                'is_active' => true,
            ])
            ->all();
        
        $queued = 0;
        
        foreach ($subscriptions as $subscription) {
            // Проверяем соответствие объявления параметрам подписки
            if ($subscription->matchesAdvertisement($advertisement)) {
                // Проверяем, не отправляли ли уже уведомление об этом объявлении
                $lastNotified = $subscription->last_notified_at;
                
                if ($eventType === 'update') {
                    // Если объявление обновлено менее чем 5 минут назад и уже было уведомление - пропускаем
                    if ($lastNotified && $lastNotified > $advertisement->updated_at - 300) {
                        Yii::info("Skipping duplicate notification for subscription {$subscription->id} (update within 5 min)", 'search_subscription');
                        continue;
                    }
                } else {
                    // Для создания: если уведомление уже отправлено в течение 1 часа - пропускаем
                    if ($lastNotified && $lastNotified > $advertisement->created_at - 3600) {
                        Yii::info("Skipping duplicate notification for subscription {$subscription->id}, last notified at " . date('Y-m-d H:i:s', $lastNotified), 'search_subscription');
                        continue;
                    }
                }
                
                // Добавляем уведомление в очередь
                $result = $this->queueSubscriptionNotification($advertisement, $subscription, $eventType);
                
                if ($result) {
                    // Обновляем время последнего уведомления, чтобы не дублировать
                    $subscription->last_notified_at = time();
                    $subscription->save(false);
                    $queued++;
                    Yii::info("Subscription notification queued for user {$subscription->user_id} (event: {$eventType})", 'search_subscription');
                } else {
                    Yii::warning("Failed to queue subscription notification for user {$subscription->user_id} (event: {$eventType})", 'search_subscription');
                }
            }
        }
        
        Yii::info("Queued {$queued} notifications for advertisement #{$advertisement->id} (event: {$eventType})", 'search_subscription');
        return $queued;
    }

    /**
     * Добавить уведомление в очередь (вместо немедленной отправки)
     */
    protected function queueSubscriptionNotification($advertisement, $subscription, $eventType = 'create')
    {
        $user = $subscription->user;
        if (!$user) {
            Yii::warning("User not found for subscription {$subscription->id}", 'search_subscription');
            return false;
        }
        
        if ($eventType === 'update') {
            $subject = "Обновление объявления по вашей подписке: {$advertisement->title}";
            $message = $this->buildSubscriptionMessage($advertisement, $subscription, 'update');
            $htmlMessage = $this->buildSubscriptionHtmlMessage($advertisement, $subscription, 'update');
        } else {
            $subject = "Новое объявление по вашей подписке: {$advertisement->title}";
            $message = $this->buildSubscriptionMessage($advertisement, $subscription, 'create');
            $htmlMessage = $this->buildSubscriptionHtmlMessage($advertisement, $subscription, 'create');
        }
        
        try {
            $userSubscriptions = NotificationSubscription::getActiveSubscriptions($user->id, 'search_subscription');
            
            if (empty($userSubscriptions)) {
                Yii::info("User {$user->id} has no active subscriptions for event 'search_subscription'", 'notification');
                return false;
            }
            
            $queued = 0;
            foreach ($userSubscriptions as $userSubscription) {
                $channelName = $userSubscription->channel;
                $channel = Yii::$app->notificationManager->getChannel($channelName);
                
                if (!$channel) {
                    Yii::warning("Channel '{$channelName}' not found", 'notification');
                    continue;
                }
                
                if (!$channel->isAvailable()) {
                    Yii::warning("Channel '{$channelName}' is not available", 'notification');
                    continue;
                }
                
                $to = $this->getRecipient($user, $channelName);
                if (!$to) {
                    Yii::warning("No recipient found for channel '{$channelName}'", 'notification');
                    continue;
                }
                
                // Просто удаляем эмодзи из сообщений
                $cleanMessage = $this->cleanHtmlFromEmoji($message);
                $cleanSubject = $this->cleanHtmlFromEmoji($subject);
                $cleanHtml = $this->cleanHtmlFromEmoji($htmlMessage);
                
                $log = new NotificationLog();
                $log->user_id = $user->id;
                $log->channel = $channelName;
                $log->event = 'search_subscription';
                $log->subject = $cleanSubject;
                $log->message = $cleanMessage;
                $log->html_body = $cleanHtml;
                $log->status = NotificationLog::STATUS_QUEUED;
                $log->queued_at = time();
                $log->retry_count = 0;
                
                if ($log->save()) {
                    $queued++;
                    Yii::info("Notification queued for user {$user->id} via '{$channelName}' (log_id: {$log->id})", 'notification');
                } else {
                    Yii::error("Failed to queue notification: " . json_encode($log->errors), 'notification');
                }
            }
            
            return $queued > 0;
            
        } catch (\Exception $e) {
            Yii::error("Failed to queue subscription notification: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'search_subscription');
            return false;
        }
    }

    /**
     * Получить получателя для канала
     */
    protected function getRecipient($user, $channelName)
    {
        switch ($channelName) {
            case 'email':
                return $user->email;
            case 'sms':
                return $user->phone;
            case 'vk':
                return $user->vk_id ?? null;
            case 'telegram':
                if (!empty($user->telegram_chat_id)) {
                    return $user->telegram_chat_id;
                }
                return $user->telegram ?? null;
            case 'whatsapp':
                return $user->whatsapp;
            default:
                return null;
        }
    }

    /**
     * Отправка уведомления подписчику
     * 
     * @param Advertisement $advertisement Объявление
     * @param SearchSubscription $subscription Подписка
     * @param string $eventType Тип события: 'create' или 'update'
     * @return bool true если уведомление успешно отправлено хотя бы по одному каналу
     */
    protected function sendSubscriptionNotification($advertisement, $subscription, $eventType = 'create')
    {
        $user = $subscription->user;
        if (!$user) {
            Yii::warning("User not found for subscription {$subscription->id}", 'search_subscription');
            return false;
        }
        
        // Разные заголовки и тексты в зависимости от типа события
        if ($eventType === 'update') {
            $subject = "Обновление объявления по вашей подписке: {$advertisement->title}";
            $message = $this->buildSubscriptionMessage($advertisement, $subscription, 'update');
            $htmlMessage = $this->buildSubscriptionHtmlMessage($advertisement, $subscription, 'update');
        } else {
            $subject = "Новое объявление по вашей подписке: {$advertisement->title}";
            $message = $this->buildSubscriptionMessage($advertisement, $subscription, 'create');
            $htmlMessage = $this->buildSubscriptionHtmlMessage($advertisement, $subscription, 'create');
        }
        
        try {
            $manager = Yii::$app->notificationManager;
            $result = $manager->sendToUser(
                $user->id,
                'search_subscription',
                $subject,
                $message,
                ['html_body' => $htmlMessage]
            );
            
            if ($result && is_array($result) && in_array(true, $result)) {
                Yii::info("Subscription notification sent to user {$user->id} (event: {$eventType})", 'search_subscription');
                return true;
            } else {
                Yii::error("Subscription notification failed for user {$user->id} (event: {$eventType})", 'search_subscription');
                return false;
            }
        } catch (\Exception $e) {
            Yii::error("Failed to send subscription notification: " . $e->getMessage(), 'search_subscription');
            return false;
        }
    }

    /**
     * Сборка текстового сообщения для подписчика
     * 
     * @param Advertisement $advertisement Объявление
     * @param SearchSubscription $subscription Подписка
     * @param string $eventType Тип события: 'create' или 'update'
     * @return string Текст сообщения
     */
    protected function buildSubscriptionMessage($advertisement, $subscription, $eventType = 'create')
    {
        $eventText = ($eventType === 'update') ? 'изменилось' : 'появилось';
        
        $parts = [
            "По вашей подписке {$eventText} объявление!",
            "",
            "Заголовок: {$advertisement->title}",
            "Раздел: " . $advertisement->getSectionLabel(),
            "Цена: " . ($advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана'),
            "Город: " . ($advertisement->city ?: 'не указан'),
            "",
            "Ваши параметры подписки:",
            $subscription->getDescription(),
            "",
            "ID объявления: #{$advertisement->id}",
        ];
        
        return implode("\n", $parts);
    }

    /**
     * Сборка HTML сообщения для подписчика
     * 
     * @param Advertisement $advertisement Объявление
     * @param SearchSubscription $subscription Подписка
     * @param string $eventType Тип события: 'create' или 'update'
     * @return string HTML сообщения
     */
    protected function buildSubscriptionHtmlMessage($advertisement, $subscription, $eventType = 'create')
    {
        $price = $advertisement->price ? number_format($advertisement->price, 0, '.', ' ') . ' ₽' : 'не указана';
        $description = $subscription->getDescription();
        
        $headerTitle = ($eventType === 'update') ? 'Объявление обновлено!' : 'Новое объявление!';
        $headerColor = ($eventType === 'update') ? '#ff9800' : '#28a745';
        $eventBadge = ($eventType === 'update') ? 'ОБНОВЛЕНО' : 'НОВОЕ';
        $eventDescription = ($eventType === 'update') 
            ? 'Объявление, на которое вы подписаны, было обновлено!' 
            : 'По вашей подписке появилось новое объявление!';
        
        return "
            <html>
            <head><style>
                body { font-family: Arial, sans-serif; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: {$headerColor}; color: white; padding: 15px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .price { font-size: 24px; color: #d9534f; font-weight: bold; }
                .params { background: #e9ecef; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
                .footer { text-align: center; padding: 15px; color: #6c757d; font-size: 12px; }
                .event-badge { 
                    display: inline-block; 
                    padding: 4px 12px; 
                    border-radius: 4px; 
                    font-size: 12px; 
                    font-weight: bold;
                    background: rgba(255,255,255,0.2);
                    margin-left: 10px;
                }
            </style></head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h2>{$headerTitle} <span class='event-badge'>{$eventBadge}</span></h2>
                    </div>
                    <div class='content'>
                        <p>{$eventDescription}</p>
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
                            <a href='" . Yii::$app->urlManager->createAbsoluteUrl(['advertisements/view', 'id' => $advertisement->id]) . "' class='btn'>Посмотреть объявление</a>
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


    /**
     * Сброс всех фильтров поиска
     * 
     * @param string|null $section - раздел (sell/buy)
     * @return \yii\web\Response
     */
    public function actionResetFilters($section = null)
    {
        // Очищаем все параметры поиска из сессии
        $session = Yii::$app->session;
        
        // Удаляем все параметры, связанные с поиском
        $session->remove('AdvertisementSearch');
        $session->remove('search_params');
        $session->remove('advertisement_search');
        
        // Если указан раздел, перенаправляем на соответствующую страницу
        if ($section === 'sell') {
            return $this->redirect(['/advertisements/sell']);
        } elseif ($section === 'buy') {
            return $this->redirect(['/advertisements/buy']);
        }
        
        // Иначе на главную страницу объявлений
        return $this->redirect(['/advertisements/index']);
    }

    /**
     * Очистка HTML от эмодзи и 4-байтовых символов (просто удаляем)
     */
    protected function cleanHtmlFromEmoji($html)
    {
        if ($html === null || $html === '') {
            return $html;
        }
        
        // Удаляем 4-байтовые символы (эмодзи)
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $html);
    }
}