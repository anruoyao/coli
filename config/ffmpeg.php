<?php

return [
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
    'timeout' => env('FFMPEG_TIMEOUT', 3600),
    'threads' => env('FFMPEG_THREADS', 12),
    'temporary_directory' => env('FFMPEG_TEMP_DIR', '/var/ffmpeg-tmp')
];