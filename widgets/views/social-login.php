<?php
// widgets/views/social-login.php

use yii\helpers\Html;
use yii\helpers\Url;

/**
 * @var array $providers
 * @var bool $showDivider
 * @var string $title
 */

$authUrl = Url::to(['/auth/auth']);
$icons = [
    'vkontakte' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M13.162 18.994c.609 0 .858-.406.851-.915-.031-1.917.714-2.949 2.059-1.604 1.488 1.488 1.796 2.519 3.603 2.519h3.2c.808 0 1.126-.26 1.126-.668 0-.863-1.421-2.386-2.625-3.504-1.686-1.565-1.765-1.602-.313-3.486 1.826-2.371 2.517-3.744 1.274-3.744h-3.064c-.597 0-.777.306-1.228 1.019-1.358 2.186-2.317 3.146-2.927 3.146-.178 0-.442-.198-.442-.616V7.868c0-.753-.226-1.088-.771-1.088H8.679c-.35 0-.57.259-.57.569 0 .601.895.812 1.214 2.103v3.636c0 .807-.132 1.148-.635 1.148-.887 0-2.524-2.464-3.616-5.081-.27-.67-.572-.944-1.259-.944H3.39c-.512 0-.607.275-.607.619 0 .731.89 3.556 3.034 6.026 1.64 1.965 3.789 3.058 5.723 3.058z"/></svg>',
        'label' => 'VK',
        'class' => 'btn-social vk',
    ],
    'google' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="#EA4335" d="M5.26620003,9.76452941 C6.19878754,6.93863203 8.85444915,4.90909091 12,4.90909091 C13.6909091,4.90909091 15.2181818,5.50909091 16.4181818,6.49090909 L19.9090909,3 C17.7818182,1.14545455 15.0545455,0 12,0 C7.27006974,0 3.1977497,2.69829785 1.23999023,6.65002441 L5.26620003,9.76452941 Z"/><path fill="#34A853" d="M16.0407269,18.0125889 C14.9509167,18.7163016 13.5660892,19.0909091 12,19.0909091 C8.86648613,19.0909091 6.21911939,17.076871 5.27698177,14.2678769 L1.23746264,17.3749879 C3.19279051,21.2935536 7.26500293,24 12,24 C14.9328362,24 17.7353462,22.9573905 19.834192,20.9995801 L16.0407269,18.0125889 Z"/><path fill="#4A90E2" d="M19.834192,20.9995801 C22.0291676,19.0838393 23.5,16.2731544 23.5,12 C23.5,11.3125 23.4181818,10.5909091 23.2545455,9.90909091 L12,9.90909091 L12,14.4545455 L18.4363636,14.4545455 C18.1187732,16.013626 17.2662994,17.2212117 16.0407269,18.0125889 L19.834192,20.9995801 Z"/><path fill="#FBBC05" d="M5.27698177,14.2678769 C5.03832634,13.556323 4.90909091,12.7937589 4.90909091,12 C4.90909091,11.2182781 5.03443647,10.4668121 5.26620003,9.76452941 L1.23999023,6.65002441 C0.436587264,8.26043117 0,10.0753928 0,12 C0,13.9195484 0.444780743,15.7301709 1.23746264,17.3749879 L5.27698177,14.2678769 Z"/></svg>',
        'label' => 'Google',
        'class' => 'btn-social google',
    ],
    'facebook' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#1877F2"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        'label' => 'Facebook',
        'class' => 'btn-social facebook',
    ],
    'yandex' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M13.996 12.004L9 5h3.996L17 12.004v7.002h-3.004v-7.002z"/></svg>',
        'label' => 'Яндекс',
        'class' => 'btn-social yandex',
    ],
    'github' => [
        'icon' => '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="#333"><path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.468-2.38 1.235-3.22-.123-.3-.535-1.52.117-3.16 0 0 1.008-.322 3.3 1.23.96-.267 1.98-.399 3-.399s2.04.132 3 .399c2.292-1.552 3.3-1.23 3.3-1.23.653 1.64.24 2.86.118 3.16.768.84 1.233 1.91 1.233 3.22 0 4.61-2.804 5.62-5.476 5.92.43.37.824 1.102.824 2.22 0 1.602-.015 2.894-.015 3.287 0 .322.216.694.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/></svg>',
        'label' => 'GitHub',
        'class' => 'btn-social github',
    ],
];
?>

<?php if ($showDivider): ?>
<div class="social-login-divider">
    <span><?= $title ?></span>
</div>
<?php endif; ?>

<div class="social-login-buttons">
    <?php foreach ($providers as $providerName): ?>
        <?php if (isset($icons[$providerName])): 
            $icon = $icons[$providerName];
            $authUrlWithProvider = $authUrl . '?authclient=' . $providerName;
        ?>
            <?= Html::a(
                $icon['icon'] . ' ' . $icon['label'],
                $authUrlWithProvider,
                [
                    'class' => 'btn ' . $icon['class'],
                    'rel' => 'nofollow',
                    'data-popup' => true,
                ]
            ) ?>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

<?php
// JavaScript для открытия в popup
$js = <<<JS
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-popup]').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var width = 600;
            var height = 600;
            var left = (window.innerWidth - width) / 2;
            var top = (window.innerHeight - height) / 2;
            var popup = window.open(
                this.href,
                'social-auth',
                'width=' + width + ',height=' + height + ',left=' + left + ',top=' + top + ',scrollbars=yes'
            );
            if (popup) {
                popup.focus();
            } else {
                window.location.href = this.href;
            }
            return false;
        });
    });
});
JS;
$this->registerJs($js);
?>

<style>
.social-login-divider {
    text-align: center;
    margin: 20px 0;
    position: relative;
}
.social-login-divider:before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #dee2e6;
}
.social-login-divider span {
    display: inline-block;
    padding: 0 15px;
    background: #fff;
    position: relative;
    z-index: 1;
    color: #6c757d;
    font-size: 14px;
}

[data-bs-theme="dark"] .social-login-divider:before {
    background: #444;
}
[data-bs-theme="dark"] .social-login-divider span {
    background: #212529;
    color: #adb5bd;
}

.social-login-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: center;
    margin-top: 10px;
}

.btn-social {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    border: 1px solid #dee2e6;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.25s ease;
    min-width: 120px;
    cursor: pointer;
    text-decoration: none !important;
}

.btn-social:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    text-decoration: none !important;
}

.btn-social svg {
    flex-shrink: 0;
}

/* VK */
.btn-social.vk {
    background: #f0f2f5;
    color: #000;
    border-color: #e0e0e0;
}
.btn-social.vk:hover {
    background: #e6e8ec;
    border-color: #d0d0d0;
}
[data-bs-theme="dark"] .btn-social.vk {
    background: #2a2a2a;
    color: #fff;
    border-color: #444;
}
[data-bs-theme="dark"] .btn-social.vk:hover {
    background: #3a3a3a;
}

/* Google */
.btn-social.google {
    background: #fff;
    color: #333;
    border-color: #ddd;
}
.btn-social.google:hover {
    background: #f5f5f5;
    border-color: #ccc;
}
[data-bs-theme="dark"] .btn-social.google {
    background: #2a2a2a;
    color: #fff;
    border-color: #444;
}
[data-bs-theme="dark"] .btn-social.google:hover {
    background: #3a3a3a;
}

/* Facebook */
.btn-social.facebook {
    background: #1877F2;
    color: #fff;
    border-color: #1877F2;
}
.btn-social.facebook:hover {
    background: #1664d1;
    border-color: #1664d1;
    color: #fff;
}

/* Yandex */
.btn-social.yandex {
    background: #fc3f1d;
    color: #fff;
    border-color: #fc3f1d;
}
.btn-social.yandex:hover {
    background: #e03a1a;
    border-color: #e03a1a;
    color: #fff;
}

/* GitHub */
.btn-social.github {
    background: #24292e;
    color: #fff;
    border-color: #24292e;
}
.btn-social.github:hover {
    background: #1a1e22;
    border-color: #1a1e22;
    color: #fff;
}
[data-bs-theme="dark"] .btn-social.github {
    background: #333;
    border-color: #444;
}
[data-bs-theme="dark"] .btn-social.github:hover {
    background: #444;
}

/* Адаптивность */
@media (max-width: 576px) {
    .btn-social {
        flex: 1;
        min-width: 80px;
        padding: 8px 12px;
        font-size: 13px;
    }
    .btn-social span {
        display: none;
    }
    .btn-social svg {
        width: 20px;
        height: 20px;
    }
}
</style>