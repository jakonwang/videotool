<?php

return [
    // 默认磁盘
    'default' => env('filesystem.driver', 'local'),
    // 磁盘列表
    'disks'   => [
        'local'  => [
            'type' => 'local',
            'root' => __DIR__ . '/../public/uploads',
            'url'  => '/uploads',
            'visibility' => 'public',
        ],
        'public' => [
            'type'       => 'local',
            'root'       => __DIR__ . '/../public/uploads',
            'url'        => '/uploads',
            'visibility' => 'public',
        ],
    ],
];

