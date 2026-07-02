<?php
return [
    'app_name' => 'Pet Adoption Platform',
    'env' => getenv('APP_ENV') ?: 'local',
    'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost/NGO-Pet-Adoption/public',
    'base_path' => getenv('APP_BASE_PATH') ?: '',
    'upload_dir' => dirname(__DIR__) . '/public/uploads',
    'upload_url' => '/uploads',
    'uploads' => [
        'max_size' => 8 * 1024 * 1024,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        'allowed_mime_types' => [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ],
        'animal_max_width' => 1600,
        'thumbnail_width' => 520,
        'quality' => 82,
    ],
    'visibility' => [
        'weights' => [
            'days_listed' => 0.30,
            'low_views' => 0.22,
            'low_engagement' => 0.18,
            'senior_factor' => 0.20,
            'vote_boost' => 0.10,
        ],
        'limits' => [
            'max_days_listed' => 180,
            'max_views' => 100,
            'max_favorites' => 25,
            'max_vote_wins' => 50,
        ],
    ],
    'pagination' => [
        'per_page' => 12,
    ],
    'rate_limits' => [
        'inquiry' => ['attempts' => 3, 'decay_seconds' => 3600],
        'vote' => ['attempts' => 20, 'decay_seconds' => 3600],
        'favorite' => ['attempts' => 30, 'decay_seconds' => 3600],
        'report' => ['attempts' => 3, 'decay_seconds' => 3600],
    ],
];
