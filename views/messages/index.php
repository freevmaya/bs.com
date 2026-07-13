<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ListView;
use app\models\User;

$this->title = 'Мои диалоги';
$this->params['breadcrumbs'][] = $this->title;

// Регистрируем CSS и JS
$this->registerCssFile('@web/css/messages.css', ['depends' => [\yii\bootstrap5\BootstrapAsset::class]]);
$this->registerJsFile('@web/js/messages.js', [
    'depends' => [\yii\web\JqueryAsset::class],
    'position' => \yii\web\View::POS_END
]);

?>

<div class="messages-index">
    <div class="row">
        <div class="col-md-8 col-lg-6">
            <h1><?= Html::encode($this->title) ?></h1>
            
            <?php if ($dataProvider->getCount() > 0): ?>
                <div class="conversations-list panel panel-default">
                    <?= ListView::widget([
                        'dataProvider' => $dataProvider,
                        'itemView' => '_conversation_item',
                        'layout' => "{items}\n{pager}",
                        'emptyText' => 'У вас нет диалогов',
                        'itemOptions' => ['class' => 'conversation-item'],
                    ]); ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <p>У вас пока нет диалогов.</p>
                    <p>Начните переписку с автором любого объявления, нажав кнопку "Написать автору".</p>
                </div>
            <?php endif; ?>
            
            <p>
                <?= Html::a('← Назад в профиль', ['/user/profile'], ['class' => 'btn btn-default']) ?>
            </p>
        </div>
    </div>
</div>