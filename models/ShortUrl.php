<?php
// FILE: .\models\ShortUrl.php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Модель коротких ссылок
 *
 * @property int $id
 * @property string $code
 * @property string $url_hash
 * @property string $url
 * @property int $created_at
 */
class ShortUrl extends ActiveRecord
{
    /**
     * Длина короткого кода
     */
    const CODE_LENGTH = 7;

    /**
     * Алфавит для генерации кода (только a-zA-Z0-9)
     */
    const CODE_ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /**
     * Максимальное количество попыток генерации уникального кода
     */
    const MAX_CODE_ATTEMPTS = 10;

    public static function tableName()
    {
        return 'short_urls';
    }

    public function rules()
    {
        return [
            [['code', 'url_hash', 'url'], 'required'],
            [['url'], 'string', 'max' => 2000],
            [['code'], 'string', 'max' => 16],
            [['url_hash'], 'string', 'length' => 64],
            [['created_at'], 'integer'],
            [['code'], 'unique'],
            [['url_hash'], 'unique'],
            [['url'], 'url'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'code' => 'Короткий код',
            'url_hash' => 'Хэш URL',
            'url' => 'Полный URL',
            'created_at' => 'Создано',
        ];
    }

    public function beforeSave($insert)
    {
        if (parent::beforeSave($insert)) {
            if ($insert && empty($this->created_at)) {
                $this->created_at = time();
            }
            return true;
        }
        return false;
    }

    /**
     * Получить короткую ссылку по полному URL.
     * Если ссылка уже существует — возвращает существующий код.
     * Иначе создаёт новый.
     *
     * @param string $url Полный URL
     * @param string $hostInfo Домен с протоколом (например, https://bs.com)
     * @return string|null Короткая ссылка или null при ошибке
     */
    public static function getOrCreate($url, $hostInfo)
    {
        $urlHash = hash('sha256', $url);

        // 1. Ищем существующую запись
        $existing = static::findOne(['url_hash' => $urlHash]);
        if ($existing) {
            return rtrim($hostInfo, '/') . '/s/' . $existing->code;
        }

        // 2. Генерируем новый код с проверкой на коллизии
        $code = static::generateUniqueCode();
        if ($code === null) {
            Yii::error('Failed to generate unique short URL code after ' . self::MAX_CODE_ATTEMPTS . ' attempts', 'short_url');
            return null;
        }

        // 3. Пытаемся сохранить. Если другой процесс в этот момент вставил тот же url_hash —
        //    ловим исключение и возвращаем существующую ссылку.
        try {
            $model = new static();
            $model->code = $code;
            $model->url_hash = $urlHash;
            $model->url = $url;
            $model->created_at = time();

            if ($model->save()) {
                return rtrim($hostInfo, '/') . '/s/' . $code;
            }

            Yii::error('Failed to save short URL: ' . json_encode($model->errors), 'short_url');
            return null;
        } catch (\yii\db\IntegrityException $e) {
            // Коллизия по url_hash — кто-то вставил параллельно. Возвращаем существующую.
            $existing = static::findOne(['url_hash' => $urlHash]);
            if ($existing) {
                return rtrim($hostInfo, '/') . '/s/' . $existing->code;
            }

            Yii::error('Integrity exception and no existing record: ' . $e->getMessage(), 'short_url');
            return null;
        }
    }

    /**
     * Найти полный URL по короткому коду
     *
     * @param string $code
     * @return string|null
     */
    public static function resolveCode($code)
    {
        $model = static::findOne(['code' => $code]);
        return $model ? $model->url : null;
    }

    /**
     * Сгенерировать уникальный короткий код
     *
     * @return string|null
     */
    protected static function generateUniqueCode()
    {
        $alphabetLength = strlen(self::CODE_ALPHABET);

        for ($attempt = 0; $attempt < self::MAX_CODE_ATTEMPTS; $attempt++) {
            $code = '';
            for ($i = 0; $i < self::CODE_LENGTH; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, $alphabetLength - 1)];
            }

            // Проверяем, что кода ещё нет
            if (!static::find()->where(['code' => $code])->exists()) {
                return $code;
            }
        }

        return null;
    }
}