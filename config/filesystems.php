<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported Drivers: "local", "ftp", "sftp", "s3"
    |
    */

    // This is the main static storage disk.
    'static_storage_disk' => env('STATIC_STORAGE_DISK', 'public'),

    'system_disks' => [
        // Never remove this disk (local). It is used for the internal storage.
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
            'throw' => false,
        ],
    ],
    'disks' => [
        // Never add here your disks. Add them in var/config/filesystems/disks.php
    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

    'upload_namespaces' => [
        'post_images' => 'uploads/posts/images',
        'post_video_thumbnails' => 'uploads/posts/video_thumbnails',
        'post_videos' => 'uploads/posts/videos',
        'post_audios' => 'uploads/posts/audios',
        'user_avatars' => 'uploads/users/avatars',
        'post_documents' => 'uploads/documents',
        'product_images' => 'uploads/products/images',
        'story_images' => 'uploads/stories/images',
        'story_videos' => 'uploads/stories/videos',
        'story_video_thumbnails' => 'uploads/stories/video_thumbnails',
        'ad_creatives' => 'uploads/ads/creatives',
    ],

    'image_encoder' => 'webp', // One of: jpeg, png, webp.
];
