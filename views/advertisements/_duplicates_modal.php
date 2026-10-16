<?php
// FILE: .\views\advertisements\_duplicates_modal.php

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 */
?>

<!-- Модальное окно предупреждения о дубликатах -->
<div class="modal fade" id="duplicates-modal" tabindex="-1" role="dialog" aria-labelledby="duplicates-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="duplicates-modal-label">
                    <span class="glyphicon glyphicon-warning-sign"></span>
                    Найдены похожие объявления
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px;">
                    В базе уже есть объявления с такими же параметрами. Возможно, вы пытаетесь создать дубликат.
                    Пожалуйста, проверьте список ниже и решите, стоит ли создавать новое объявление.
                </p>
                <div id="duplicates-list" class="duplicates-list">
                    <!-- Список заполняется через JS -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal" id="duplicates-cancel-btn">
                    <span class="glyphicon glyphicon-remove"></span> Отмена
                </button>
                <button type="button" class="btn btn-success" id="duplicates-confirm-btn">
                    <span class="glyphicon glyphicon-ok"></span> Всё равно создать
                </button>
            </div>
        </div>
    </div>
</div>