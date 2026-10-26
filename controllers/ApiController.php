<?php
// FILE: .\controllers\ApiController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use app\models\Manufacturer;
use app\models\Wing;
use app\models\WingLowerLine;
use app\models\WingUpperLine;

/**
 * Публичный JSON API для данных о длинах строп.
 *
 * Эндпоинты:
 *   GET /api/manufacturers                  — список производителей
 *   GET /api/manufacturers/{id}/wings       — крылья производителя
 *   GET /api/wings/{id}/lines               — длины строп крыла
 *
 * Все запросы без авторизации. Кэширование не используется.
 * Отдаются только крылья в состоянии state = 'active'.
 */
class ApiController extends Controller
{
    /**
     * Отключаем CSRF для всех action'ов (только GET-запросы, публичный API).
     */
    public $enableCsrfValidation = false;

    /**
     * Отключаем layout — только JSON.
     */
    public $layout = false;

    /**
     * {@inheritdoc}
     */
    public function beforeAction($action)
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        // CORS: разрешаем все origins
        $headers = Yii::$app->response->headers;
        $headers->set('Access-Control-Allow-Origin', '*');
        $headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
        $headers->set('Access-Control-Allow-Headers', 'Content-Type, Accept');

        // Preflight
        if (Yii::$app->request->isOptions) {
            Yii::$app->response->statusCode = 204;
            Yii::$app->response->data = null;
            return false;
        }

        // Только GET
        if (!Yii::$app->request->isGet) {
            Yii::$app->response->statusCode = 405;
            Yii::$app->response->data = [
                'success' => false,
                'error' => 'Method Not Allowed',
            ];
            return false;
        }

        return parent::beforeAction($action);
    }

    /**
     * GET /api/manufacturers
     *
     * Список всех производителей, у которых есть хотя бы одно active-крыло.
     */
    public function actionManufacturers()
    {
        $manufacturers = Manufacturer::find()
            ->where([
                'id' => Wing::find()
                    ->select('manufacturer_id')
                    ->where(['state' => Wing::STATE_ACTIVE])
                    ->andWhere(['is not', 'manufacturer_id', null])
                    ->distinct(),
            ])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        $data = [];
        foreach ($manufacturers as $m) {
            $wingsCount = (int)Wing::find()
                ->where([
                    'manufacturer_id' => $m->id,
                    'state' => Wing::STATE_ACTIVE,
                ])
                ->count();

            $data[] = [
                'id' => (int)$m->id,
                'name' => $m->name,
                'website' => $m->website,
                'layout_method' => $m->layout_method,
                'wings_count' => $wingsCount,
            ];
        }

        return [
            'success' => true,
            'data' => $data,
        ];
    }

    /**
     * GET /api/manufacturers/{id}/wings
     *
     * Список active-крыльев выбранного производителя.
     *
     * @param int $id
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionManufacturerWings($id)
    {
        $id = (int)$id;

        $manufacturer = Manufacturer::findOne($id);
        if ($manufacturer === null) {
            throw new NotFoundHttpException('Производитель не найден');
        }

        $wings = Wing::find()
            ->where([
                'manufacturer_id' => $manufacturer->id,
                'state' => Wing::STATE_ACTIVE,
            ])
            ->orderBy([
                'model' => SORT_ASC,
                'model_year' => SORT_ASC,
                'size' => SORT_ASC,
                'source_id' => SORT_ASC,
            ])
            ->all();

        $data = [];
        foreach ($wings as $w) {
            $data[] = [
                'id' => (int)$w->id,
                'source_id' => (int)$w->source_id,
                'model' => $w->model,
                'model_year' => $w->model_year !== null ? (int)$w->model_year : null,
                'size' => $w->size,
                'full_name' => $w->full_name,
                'source_url' => $w->source_url,
                'catalog_match' => (bool)$w->catalog_match,
                'parsed_at' => $w->parsed_at,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'manufacturer' => [
                    'id' => (int)$manufacturer->id,
                    'name' => $manufacturer->name,
                    'website' => $manufacturer->website,
                    'layout_method' => $manufacturer->layout_method,
                ],
                'wings' => $data,
            ],
        ];
    }

    /**
     * GET /api/wings/{id}/lines
     *
     * Полные данные о длинах строп выбранного крыла:
     * метаданные крыла + файлы + нижние стропы с привязанными верхними.
     *
     * @param int $id
     * @return array
     * @throws NotFoundHttpException
     */
    public function actionWingLines($id)
    {
        $id = (int)$id;

        $wing = Wing::find()
            ->where([
                'id' => $id,
                'state' => Wing::STATE_ACTIVE,
            ])
            ->with(['manufacturer', 'files'])
            ->one();

        if ($wing === null) {
            throw new NotFoundHttpException('Крыло не найдено');
        }

        $lowerLines = WingLowerLine::find()
            ->where(['wing_id' => $wing->id])
            ->orderBy(['group_index' => SORT_ASC])
            ->all();

        $upperByLower = [];
        if (!empty($lowerLines)) {
            $upperLines = WingUpperLine::find()
                ->where(['wing_id' => $wing->id])
                ->orderBy(['sort_order' => SORT_ASC, 'name' => SORT_ASC])
                ->all();

            foreach ($upperLines as $line) {
                $upperByLower[(int)$line->lower_line_id][] = $line;
            }
        }

        $lowerData = [];
        foreach ($lowerLines as $lower) {
            $isBrake = ($lower->length_mm === null && $lower->riser_note === null);

            $upperData = [];
            foreach ($upperByLower[(int)$lower->id] ?? [] as $upper) {
                $upperData[] = [
                    'id' => (int)$upper->id,
                    'name' => $upper->name,
                    'length_mm' => (float)$upper->length_mm,
                    'sort_order' => (int)$upper->sort_order,
                ];
            }

            $lowerData[] = [
                'id' => (int)$lower->id,
                'group_index' => (int)$lower->group_index,
                'name' => $lower->name,
                'length_mm' => $lower->length_mm !== null ? (float)$lower->length_mm : null,
                'riser_note' => $lower->riser_note,
                'raw_value' => $lower->raw_value,
                'is_brake' => $isBrake,
                'upper_lines' => $upperData,
            ];
        }

        $filesData = [];
        foreach ($wing->files as $file) {
            $filesData[] = [
                'id' => (int)$file->id,
                'name' => $file->name,
                'url' => $file->url,
                'file_date' => $file->file_date,
                'sort_order' => (int)$file->sort_order,
            ];
        }

        return [
            'success' => true,
            'data' => [
                'wing' => [
                    'id' => (int)$wing->id,
                    'source_id' => (int)$wing->source_id,
                    'model' => $wing->model,
                    'model_year' => $wing->model_year !== null ? (int)$wing->model_year : null,
                    'size' => $wing->size,
                    'full_name' => $wing->full_name,
                    'source_url' => $wing->source_url,
                    'catalog_match' => (bool)$wing->catalog_match,
                    'parsed_at' => $wing->parsed_at,
                ],
                'manufacturer' => $wing->manufacturer !== null ? [
                    'id' => (int)$wing->manufacturer->id,
                    'name' => $wing->manufacturer->name,
                    'website' => $wing->manufacturer->website,
                    'layout_method' => $wing->manufacturer->layout_method,
                ] : null,
                'files_used' => $filesData,
                'lower_lines' => $lowerData,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Обработка NotFoundHttpException и любых других исключений
     * с отдачей JSON вместо HTML.
     */
    public function runAction($id, $params = [])
    {
        try {
            return parent::runAction($id, $params);
        } catch (NotFoundHttpException $e) {
            Yii::$app->response->statusCode = 404;
            return [
                'success' => false,
                'error' => $e->getMessage() ?: 'Not Found',
            ];
        } catch (\Throwable $e) {
            Yii::error('API error: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'api');
            Yii::$app->response->statusCode = 500;
            return [
                'success' => false,
                'error' => 'Internal Server Error',
            ];
        }
    }
}