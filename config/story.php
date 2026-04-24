<?php

return [
    'video_clip_size' => env('STORY_VIDEO_CLIP_SIZE', 60),
    'image_clip_size' => env('STORY_IMAGE_CLIP_SIZE', 10),
    'max_frames_per_story' => env('STORY_MAX_FRAMES_PER_STORY', 10),
    'validation' => [
        'content' => [
            'min' => env('STORY_CONTENT_MIN', 1),
            'max' => env('STORY_CONTENT_MAX', 2200)
        ],
        'image' => [
			'mimes' => join(',', [
                'webp',
                'jpeg',
                'png',
                'jpg',
                'gif'
            ]),
			'mimetypes' => join(',', [
                'image/webp',
                'image/jpeg',
                'image/png',
                'image/jpg',
                'image/gif'
            ]),
			'max' => env('STORY_IMAGE_MAX_SIZE', 12000000) // 12MB
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
			'max' => env('STORY_VIDEO_MAX_SIZE', 48000000) // 48MB
		]
	]
];