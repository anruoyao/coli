<?php

return [
    'max_images_count' => 10,
    'paginate_per' => 30,
    'validation' => [
        'content' => [
            'min' => 1,
            'max' => 8200
        ],
        'image' => [
            'mimes' => join(',', [
                'jpeg',
                'png',
                'webp',
                'jpg',
                'gif'
            ]),
            'mimetypes' => join(',', [
                'image/jpeg',
                'image/webp',
                'image/png',
                'image/jpg',
                'image/gif'
            ]),
            'max' => '128000' // 128MB
        ],
        'video' => [
            'mimes' => join(',', [
                'mp4',
                'avi',
                'mpeg',
                'mov'
            ]),
            'mimetypes' => join(',', [
                'video/mp4',
                'video/avi',
                'video/mpeg',
                'video/quicktime',
                'video/webm'
            ]),
            'max' => '512000' // 512MB
        ],
        'audio' => [
            'mimes' => join(',', [
                'mp3',
                'wav',
                'webm'
            ]),
            'mimetypes' => join(',', [
                'audio/mpeg',
                'audio/wav',
                'audio/x-wav',
                'video/webm',
                'audio/webm'
            ]),
            'max' => '24576', // 24MB,
        ],
        'gif' => [
            'mimes' => 'gif',
            'mimetypes' => join(',', [
                'image/gif'
            ]), 
            'max' => '2048' // 2MB
        ],
        'document' => [
            'mimes' => join(',', [
                'pdf',
                'doc', 
                'docx', 
                'ppt', 
                'pptx', 
                'xls', 
                'xlsx'
            ]),
            'mimetypes' => join(',', [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ]),
            'max' => '24576', // 24MB,
        ]
    ],
    'processing' => [
        'image' => [
            'compress_rate' => 30, // Set quality of image from 1 to 100 
        ]
    ],
    'link_preview' => [
        'enable' => true,
        'driver' => 'php-embed'
    ],
    'reactions' => [
        'allow_multiple_reactions' => false
    ],
    'comments' => [
        'paginate_per' => 20,
        'validation' => [
            'min' => 1,
            'max' => 2200
        ]
    ]
];