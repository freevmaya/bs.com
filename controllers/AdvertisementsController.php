<?php

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
        
        // Создаем дополнительные модели - ВАЖНО: используем полное пространство имен
        $gliderModel = new AdvertisementGlider();
        $harnessModel = new AdvertisementHarness();
        $deviceModel = new AdvertisementDevice();
        
        if ($model->load(Yii::$app->request->post())) {
            if ($model->save()) {
                // Сохраняем дополнительные данные в зависимости от типа
                if ($this->saveExtraFields($model, Yii::$app->request->post())) {
                    // Переносим изображения
                    $migratedCount = $this->tempStorage->migrateImages($tempId, $model->id);
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
                    
                    // Загружаем данные
                    $extraModel->setAttributes($postData['AdvertisementGlider']);
                    $extraModel->advertisement_id = $advertisement->id;
                    
                    // Обрабатываем certification_id - если пустая строка, устанавливаем NULL
                    if (isset($postData['AdvertisementGlider']['certification_id']) && $postData['AdvertisementGlider']['certification_id'] === '') {
                        $extraModel->certification_id = null;
                    }
                    
                    // Устанавливаем значение по умолчанию для condition
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
        
        // Проверяем CSRF
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
        
        // Проверяем CSRF
        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }
        
        if ($image->delete()) {
            return ['success' => true, 'message' => 'Изображение удалено'];
        }
        
        return ['success' => false, 'error' => 'Ошибка при удалении'];
    }
    
    public function actionReorderImages()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        
        if (Yii::$app->request->isPost) {
            $orders = Yii::$app->request->post('orders');
            foreach ($orders as $order) {
                $image = AdvertisementImage::findOne($order['id']);
                if ($image) {
                    $image->sort_order = $order['position'];
                    $image->save();
                }
            }
            return ['success' => true];
        }
        return ['success' => false];
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
        
        // Проверяем CSRF
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
        
        // Проверяем CSRF
        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }
        
        $sessionTempId = Yii::$app->session->get('temp_ad_id');
        if ($sessionTempId != $tempId) {
            return ['success' => false, 'error' => 'Неверный идентификатор сессии'];
        }
        
        $tempImages = $this->tempStorage->getTempImages($tempId);
        
        if (isset($tempImages[$index])) {
            // Удаляем файлы
            $this->tempStorage->deleteTempFiles($tempImages[$index]);
            
            // Удаляем из сессии
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
            
            // Валидация основной модели
            if (!$model->validate()) {
                foreach ($model->errors as $field => $fieldErrors) {
                    $errors[$field] = implode(', ', $fieldErrors);
                    $invalidFields[] = 'Advertisement[' . $field . ']';
                }
            }
            
            // Валидация дополнительных моделей
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
}