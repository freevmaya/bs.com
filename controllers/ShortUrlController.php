<?php
// FILE: .\controllers\ShortUrlController.php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\NotFoundHttpException;
use app\models\ShortUrl;

class ShortUrlController extends Controller
{
    /**
     * Отключаем CSRF только для редиректа.
     * Для actionShorten — оставляем (POST-запрос, CSRF проверяется).
     */
    public function beforeAction($action)
    {
        if ($action->id === 'redirect') {
            $this->enableCsrfValidation = false;
        }
        return parent::beforeAction($action);
    }

    /**
     * Редирект по короткой ссылке
     *
     * @param string $code
     * @return Response
     * @throws NotFoundHttpException
     */
    public function actionRedirect($code)
    {
        $url = ShortUrl::resolveCode($code);

        if ($url === null) {
            throw new NotFoundHttpException('Ссылка не найдена');
        }

        return $this->redirect($url, 302);
    }

    /**
     * Создать короткую ссылку из полного URL
     *
     * Принимает POST { url: 'https://bs.com/advertisements/sell?...' }
     * Возвращает JSON { success: true, short_url: 'https://bs.com/s/abc1234' }
     *
     * @return array
     */
    public function actionShorten()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!Yii::$app->request->isPost) {
            return ['success' => false, 'error' => 'Только POST-запросы'];
        }

        $url = trim((string)Yii::$app->request->post('url', ''));

        if ($url === '') {
            return ['success' => false, 'error' => 'URL не указан'];
        }

        if (mb_strlen($url) > 2000) {
            return ['success' => false, 'error' => 'URL слишком длинный'];
        }

        // Берём схему и хост из текущего запроса
        $hostInfo = Yii::$app->request->hostInfo; // https://bs.com

        // Проверяем, что URL ведёт на наш домен
        if (strpos($url, $hostInfo . '/') !== 0 && $url !== $hostInfo) {
            Yii::warning('Shorten rejected: URL does not belong to host. URL=' . $url . ', host=' . $hostInfo, 'short_url');
            return ['success' => false, 'error' => 'URL должен принадлежать этому сайту'];
        }

        // Проверяем, что URL валидный
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'Некорректный URL'];
        }

        $shortUrl = ShortUrl::getOrCreate($url, $hostInfo);

        if ($shortUrl === null) {
            return ['success' => false, 'error' => 'Не удалось создать короткую ссылку'];
        }

        return [
            'success' => true,
            'short_url' => $shortUrl,
        ];
    }
}