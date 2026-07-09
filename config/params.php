<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@bs.com',
    'senderName' => 'BS.com',
    
    // Настройки для SMS
    'sms_api_key' => 'your_sms_api_key',
    'sms_api_url' => 'https://sms.ru/send',
    
    // Настройки для VK
    'vk_access_token' => 'vk1.a.f13FiAyX8EGlICNdvmjy83T9YhlYAM0ay6OxRVN-2NSR5ZfRrExzzphvSigozC3EV-S2sC4uN5axQXIgVeIoyp-WWWT6Gk6Lx62ClBKxafwk7LNUaXnfntZsn0GBq2IMkIUPPXueTymWzixZTVBxwxZFE2ycvBUWkGKU97aQ3BAkPUaUyCLp-BJI0k0UAMB7OXp7jwgxwpZ4ZcTid1AwhQ', // Замените на реальный токен
    'vk_group_id' => 240146240, // ID вашего сообщества (для отправки от имени сообщества)
    'vk_confirm_token' => 'ew3243dew32re2e32e', // Для callback API
    
    // Настройки для FFmpeg
    'ffmpeg_paths' => [
        'ffmpeg',
        // ...
    ]
];