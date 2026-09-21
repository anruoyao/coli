<?php

namespace App\Services\Filesystem\FFMpeg;

use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;

class FFMpegService
{
    protected $ffmpeg;
    protected $ffprobe;

    public function __construct()
    {
        $this->initializeFFmpeg();
    }

    /**
     * Initialize FFmpeg and FFprobe instances with configuration.
     *
     * @return void
     * @throws RuntimeException
     */
    private function initializeFFmpeg()
    {

        ini_set('memory_limit', '512M');

        $temporaryDirectory = config('ffmpeg.temporary_directory');

        // ffmpeg 临时目录（如 /var/ffmpeg-tmp）是系统路径，全新部署后可能缺失；
        // 缺失时自动创建，避免 ffmpeg 处理视频/音频时因目录不存在而失败。
        if ($temporaryDirectory && ! is_dir($temporaryDirectory)) {
            @mkdir($temporaryDirectory, 0775, true);
        }

        $ffmpegConfig = [
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg_path'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe_path'),
            'timeout' => config('ffmpeg.timeout'),
            'ffmpeg.threads' => config('ffmpeg.threads'),
        ];

        // 仅当临时目录实际存在且可写时才传入，否则交给 FFMpeg 库使用系统默认临时目录
        if ($temporaryDirectory && is_dir($temporaryDirectory) && is_writable($temporaryDirectory)) {
            $ffmpegConfig['temporary_directory'] = $temporaryDirectory;
        }

        $this->ffmpeg = FFMpeg::create($ffmpegConfig);

        $this->ffprobe = FFProbe::create([
            'ffprobe.binaries' => config('ffmpeg.ffprobe_path')
        ]);

        $this->ffmpeg->setFFProbe($this->ffprobe);
    }

    /**
     * Get the FFmpeg instance.
     *
     * @return FFMpeg
     */
    public function getFFMpeg()
    {
        return $this->ffmpeg;
    }

    /**
     * Get the FFprobe instance.
     *
     * @return FFProbe
     */
    public function getFFProbe()
    {
        return $this->ffprobe;
    }
}
