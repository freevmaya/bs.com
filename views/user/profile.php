<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\grid\GridView;
use app\models\Advertisement;

$this->title = 'Профиль: ' . Html::encode($user->username);
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем CSS для профиля
$this->registerCssFile('@web/css/user-profile.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
?>

<div class="user-profile">
    <!-- Заголовок профиля -->
    <div class="profile-header">
        <span class="profile-avatar">👤</span>
        <div class="profile-username"><?= Html::encode($user->username) ?></div>
        <div class="profile-email"><?= Html::encode($user->email) ?></div>
        
        <div class="profile-stats">
            <div class="profile-stat">
                <span class="number"><?= Advertisement::find()->where(['user_id' => $user->id])->count() ?></span>
                <span class="label">Объявлений</span>
            </div>
            <div class="profile-stat">
                <span class="number"><?= \app\models\SearchSubscription::find()->where(['user_id' => $user->id, 'is_active' => true])->count() ?></span>
                <span class="label">Подписок</span>
            </div>
        </div>
    </div>

    <!-- Меню профиля -->
    <div class="profile-menu">
        <?= Html::a('📋 Мои объявления', ['/advertisements/my'], ['class' => 'btn btn-sm']) ?>
        <?= Html::a('✏️ Редактировать', ['/user/edit'], ['class' => 'btn btn-sm']) ?>
        <?= Html::a('🔔 Уведомления', ['/notification/index'], ['class' => 'btn btn-sm']) ?>
        <?= Html::a('📬 Подписки', ['/search-subscription/index'], ['class' => 'btn btn-sm']) ?>
        <?= Html::a(
            '🚪 Выйти',
            ['/site/logout'],
            [
                'class' => 'btn btn-sm',
                'data-method' => 'post',
            ]
        ) ?>
    </div>

    <!-- Мои объявления -->
    <div class="profile-ads">
        <div class="profile-section-title">📋 Мои объявления</div>
        <?= Html::a('➕ Добавить объявление', ['/advertisements/create'], ['class' => 'btn btn-success btn-sm mb-3']) ?>
        
        <?= GridView::widget([
            'dataProvider' => $adsDataProvider,
            'columns' => [
                [
                    'attribute' => 'title',
                    'format' => 'raw',
                    'value' => function ($model) {
                        return Html::a(Html::encode($model->title), ['/advertisements/view', 'id' => $model->id]);
                    },
                ],
                [
                    'attribute' => 'section',
                    'value' => function ($model) {
                        return $model->getSectionLabel();
                    },
                ],
                [
                    'attribute' => 'price',
                    'value' => function ($model) {
                        return $model->price ? number_format($model->price, 0, '.', ' ') . ' ₽' : '—';
                    },
                ],
                [
                    'attribute' => 'status',
                    'format' => 'raw',
                    'value' => function ($model) {
                        $isActive = ($model->status === Advertisement::STATUS_ACTIVE);
                        return '<span class="label label-' . ($isActive ? 'success' : 'default') . '">' . $model->getStatusLabel() . '</span>';
                    },
                ],
                [
                    'class' => 'yii\grid\ActionColumn',
                    'template' => '{view} {update}',
                    'buttons' => [
                        'view' => function ($url, $model) {
                            return Html::a('👁', ['/advertisements/view', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-primary']);
                        },
                        'update' => function ($url, $model) {
                            return Html::a('✏️', ['/advertisements/update', 'id' => $model->id], ['class' => 'btn btn-sm btn-outline-warning']);
                        },
                    ],
                ],
            ],
        ]); ?>
    </div>
</div>