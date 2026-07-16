<?php
// FILE: .\views\user\profile.php

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
        <span class="profile-avatar">
            <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                <circle cx="12" cy="7" r="4"/>
            </svg>
        </span>
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
        
        <!-- Контакты -->
        <div style="margin-top: 15px;">
            <?php if ($user->city): ?>
                <span class="label label-default" style="margin: 3px;">
                    <span class="glyphicon glyphicon-map-marker"></span> <?= Html::encode($user->city) ?>
                </span>
            <?php endif; ?>
            <?php if ($user->phone): ?>
                <span class="label label-default" style="margin: 3px;">
                    <span class="glyphicon glyphicon-phone"></span> <?= Html::encode($user->phone) ?>
                </span>
            <?php endif; ?>
            <?php if ($user->telegram): ?>
                <span class="label label-default" style="margin: 3px;">
                    <span class="glyphicon glyphicon-send"></span> 
                    <a href="https://t.me/<?= ltrim($user->telegram, '@') ?>" target="_blank" style="color: inherit;">
                        <?= Html::encode($user->telegram) ?>
                    </a>
                </span>
            <?php endif; ?>
            <?php if ($user->whatsapp): ?>
                <span class="label label-default" style="margin: 3px;">
                    <span class="glyphicon glyphicon-phone"></span> 
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $user->whatsapp) ?>" target="_blank" style="color: inherit;">
                        WhatsApp
                    </a>
                </span>
            <?php endif; ?>
            <?php if ($user->vk_profile_url): ?>
                <span class="label label-default" style="margin: 3px;">
                    <span class="glyphicon glyphicon-user"></span> 
                    <a href="<?= Html::encode($user->vk_profile_url) ?>" target="_blank" style="color: inherit;">
                        VK
                    </a>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Меню профиля -->
    <div class="profile-menu">
        <?= Html::a(
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg> Мои объявления',
            ['/advertisements/my'],
            ['class' => 'btn btn-sm']
        ) ?>
        <?= Html::a(
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Редактировать',
            ['/user/edit'],
            ['class' => 'btn btn-sm']
        ) ?>
        <?= Html::a(
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg> Сменить пароль',
            ['/user/change-password'],
            ['class' => 'btn btn-sm btn-warning']
        ) ?>
        <?= Html::a(
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg> Уведомления',
            ['/notification/index'],
            ['class' => 'btn btn-sm']
        ) ?>
        <?= Html::a(
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4v16h16"/><polyline points="20 10 12 18 8 14"/></svg> Подписки',
            ['/search-subscription/index'],
            ['class' => 'btn btn-sm']
        ) ?>
        <?= Html::a(
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg> Выйти',
            ['/site/logout'],
            [
                'class' => 'btn btn-sm',
                'data-method' => 'post',
            ]
        ) ?>
    </div>

    <!-- Мои объявления -->
    <div class="profile-ads">
        <div class="profile-section-title">
            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>
            Мои объявления
        </div>
        <?= Html::a(
            '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Добавить объявление',
            ['/advertisements/create'],
            ['class' => 'btn btn-success btn-sm mb-3']
        ) ?>
        
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
                            return Html::a(
                                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
                                ['/advertisements/view', 'id' => $model->id],
                                ['class' => 'btn btn-sm btn-outline-primary']
                            );
                        },
                        'update' => function ($url, $model) {
                            return Html::a(
                                '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>',
                                ['/advertisements/update', 'id' => $model->id],
                                ['class' => 'btn btn-sm btn-outline-warning']
                            );
                        },
                    ],
                ],
            ],
        ]); ?>
    </div>
</div>