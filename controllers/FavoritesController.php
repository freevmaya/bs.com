<?php
// FILE: .\controllers\FavoritesController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\data\ArrayDataProvider;
use yii\filters\AccessControl;
use app\components\FavoriteStorage;
use app\models\Advertisement;

class FavoritesController extends Controller
{
    /** @var FavoriteStorage */
    private $storage;

    public function init()
    {
        parent::init();
        $this->storage = new FavoriteStorage();
    }

    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'only' => ['index', 'toggle', 'count'],
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['?', '@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => \yii\filters\VerbFilter::class,
                'actions' => [
                    'toggle' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Страница «Избранное».
     *
     * Для авторизованного — записи из БД.
     * Для гостя — ID из cookie, объявления загружаются из БД (только активные).
     */
    public function actionIndex()
    {
        $ids = $this->storage->getIds();

        $query = Advertisement::find()
            ->where(['id' => $ids])
            ->andWhere(['status' => Advertisement::STATUS_ACTIVE])
            ->with(['images', 'glider', 'harness', 'device', 'reserve']);

        $ads = $query->all();

        // Сортируем в порядке $ids (новые сверху — для БД уже отсортировано по created_at)
        if (!Yii::$app->user->isGuest) {
            // уже отсортировано в getIds() по created_at DESC
        } else {
            // для гостя — по порядку в cookie (новые в конце, поэтому реверсим)
            $idsOrder = array_flip($ids);
            usort($ads, function ($a, $b) use ($idsOrder) {
                $pa = $idsOrder[$a->id] ?? PHP_INT_MAX;
                $pb = $idsOrder[$b->id] ?? PHP_INT_MAX;
                return $pb <=> $pa; // новые сверху
            });
        }

        $dataProvider = new ArrayDataProvider([
            'allModels' => $ads,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => false,
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'totalCount' => count($ads),
        ]);
    }

    /**
     * AJAX: переключить избранное.
     *
     * @return array
     */
    public function actionToggle()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->validateCsrfToken()) {
            return ['success' => false, 'error' => 'CSRF token validation failed'];
        }

        $adId = (int)Yii::$app->request->post('advertisement_id', 0);
        if ($adId <= 0) {
            return ['success' => false, 'error' => 'Не указан ID объявления'];
        }

        $advertisement = Advertisement::findOne($adId);
        if (!$advertisement) {
            return ['success' => false, 'error' => 'Объявление не найдено'];
        }

        $isFavorite = $this->storage->toggle($adId);

        return [
            'success' => true,
            'is_favorite' => $isFavorite,
            'count' => $this->storage->count(),
            'message' => $isFavorite ? 'Добавлено в избранное' : 'Удалено из избранного',
        ];
    }

    /**
     * AJAX: получить количество избранного (для бейджа в тулбаре).
     *
     * @return array
     */
    public function actionCount()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        return [
            'success' => true,
            'count' => $this->storage->count(),
        ];
    }
}