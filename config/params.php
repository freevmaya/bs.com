<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    
    // Настройки для SMS
    'sms_api_key' => 'your_sms_api_key',
    'sms_api_url' => 'https://sms.ru/send',
    
    // Настройки для VK
    'vk_access_token' => 'your_vk_access_token',
    
    // Настройки для FFmpeg
    'ffmpeg_paths' => [
        'ffmpeg', // если в PATH
        'C:\ffmpeg\bin\ffmpeg.exe',
        'D:\Programs\ffmpeg\bin\ffmpeg.exe',
        'C:\Program Files\ffmpeg\bin\ffmpeg.exe',
        'C:\Program Files (x86)\ffmpeg\bin\ffmpeg.exe',
        '/usr/bin/ffmpeg', // для Linux
        '/usr/local/bin/ffmpeg', // для Linux
    ]
];
