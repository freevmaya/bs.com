<?php
// FILE: .\components\FavoriteStorage.php

namespace app\components;

use Yii;
use yii\base\Component;
use app\models\Favorite;

/**
 * Хранилище избранного.
 *
 * Для авторизованного пользователя — таблица favorites.
 * Для гостя — cookie COOKIE_NAME = JSON-массив ID объявлений.
 *
 * Единый API:
 *   - getIds(): int[]           — список ID избранного
 *   - has(int $adId): bool      — есть ли объявление в избранном
 *   - add(int $adId): bool      — добавить
 *   - remove(int $adId): bool   — удалить
 *   - toggle(int $adId): bool   — переключить, вернуть новое состояние
 *   - count(): int              — количество
 */
class FavoriteStorage extends Component
{
    /**
     * Получить список ID избранного для текущего пользователя/гостя.
     *
     * @return int[]
     */
    public function getIds()
    {
        if (Yii::$app->user->isGuest) {
            return $this->getGuestIds();
        }
        return Favorite::find()
            ->select('advertisement_id')
            ->where(['user_id' => Yii::$app->user->id])
            ->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC])
            ->column();
    }

    /**
     * Проверить, есть ли объявление в избранном.
     *
     * @param int $advertisementId
     * @return bool
     */
    public function has($advertisementId)
    {
        $advertisementId = (int)$advertisementId;
        if ($advertisementId <= 0) {
            return false;
        }

        if (Yii::$app->user->isGuest) {
            return in_array($advertisementId, $this->getGuestIds(), true);
        }

        return Favorite::find()
            ->where([
                'user_id' => Yii::$app->user->id,
                'advertisement_id' => $advertisementId,
            ])
            ->exists();
    }

    /**
     * Добавить в избранное.
     *
     * @param int $advertisementId
     * @return bool
     */
    public function add($advertisementId)
    {
        $advertisementId = (int)$advertisementId;
        if ($advertisementId <= 0) {
            return false;
        }

        if (Yii::$app->user->isGuest) {
            $ids = $this->getGuestIds();
            if (in_array($advertisementId, $ids, true)) {
                return true;
            }
            $ids[] = $advertisementId;
            return $this->saveGuestIds($ids);
        }

        if ($this->has($advertisementId)) {
            return true;
        }

        $favorite = new Favorite();
        $favorite->user_id = Yii::$app->user->id;
        $favorite->advertisement_id = $advertisementId;
        $favorite->created_at = time();

        return $favorite->save(false);
    }

    /**
     * Удалить из избранного.
     *
     * @param int $advertisementId
     * @return bool
     */
    public function remove($advertisementId)
    {
        $advertisementId = (int)$advertisementId;
        if ($advertisementId <= 0) {
            return false;
        }

        if (Yii::$app->user->isGuest) {
            $ids = $this->getGuestIds();
            $ids = array_values(array_filter($ids, function ($id) use ($advertisementId) {
                return (int)$id !== $advertisementId;
            }));
            return $this->saveGuestIds($ids);
        }

        Favorite::deleteAll([
            'user_id' => Yii::$app->user->id,
            'advertisement_id' => $advertisementId,
        ]);
        return true;
    }

    /**
     * Переключить состояние избранного.
     *
     * @param int $advertisementId
     * @return bool Новое состояние: true — в избранном, false — нет
     */
    public function toggle($advertisementId)
    {
        if ($this->has($advertisementId)) {
            $this->remove($advertisementId);
            return false;
        }
        $this->add($advertisementId);
        return true;
    }

    /**
     * Количество избранного.
     *
     * @return int
     */
    public function count()
    {
        if (Yii::$app->user->isGuest) {
            return count($this->getGuestIds());
        }
        return (int)Favorite::find()
            ->where(['user_id' => Yii::$app->user->id])
            ->count();
    }

    /**
     * Слить гостевые ID в БД пользователя и очистить cookie.
     *
     * @param int $userId
     * @return int Количество добавленных записей
     */
    public function mergeGuestFavorites($userId)
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            return 0;
        }

        $guestIds = $this->getGuestIds();
        if (empty($guestIds)) {
            $this->clearGuestCookie();
            return 0;
        }

        // Оставляем только существующие активные объявления
        $existingIds = Advertisement::find()
            ->select('id')
            ->where(['id' => $guestIds])
            ->column();
        $existingIds = array_map('intval', $existingIds);

        $inserted = 0;
        $now = time();

        foreach ($existingIds as $adId) {
            $alreadyExists = Favorite::find()
                ->where(['user_id' => $userId, 'advertisement_id' => $adId])
                ->exists();
            if ($alreadyExists) {
                continue;
            }

            $favorite = new Favorite();
            $favorite->user_id = $userId;
            $favorite->advertisement_id = $adId;
            $favorite->created_at = $now;

            if ($favorite->save(false)) {
                $inserted++;
            }
        }

        $this->clearGuestCookie();

        return $inserted;
    }

    /**
     * Получить ID избранного из cookie гостя.
     *
     * @return int[]
     */
    protected function getGuestIds()
    {
        $raw = Yii::$app->request->cookies->getValue(Favorite::COOKIE_NAME);
        if (empty($raw)) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $ids = [];
        foreach ($decoded as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Сохранить ID избранного в cookie гостя.
     *
     * @param int[] $ids
     * @return bool
     */
    protected function saveGuestIds(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, function ($id) {
            return $id > 0;
        });
        $ids = array_values($ids);

        $cookie = new \yii\web\Cookie([
            'name' => Favorite::COOKIE_NAME,
            'value' => json_encode($ids),
            'expire' => time() + Favorite::COOKIE_TTL,
            'path' => '/',
            'httpOnly' => false,
            'sameSite' => \yii\web\Cookie::SAME_SITE_LAX,
        ]);
        Yii::$app->response->cookies->add($cookie);

        return true;
    }

    /**
     * Очистить cookie гостя.
     */
    public function clearGuestCookie()
    {
        Yii::$app->response->cookies->remove(Favorite::COOKIE_NAME);
    }
}