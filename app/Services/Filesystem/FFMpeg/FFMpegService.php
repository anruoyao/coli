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
        
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => config('ffmpeg.ffmpeg_path'),
            'ffprobe.binaries' => config('ffmpeg.ffprobe_path'),
            'timeout' => config('ffmpeg.timeout'),
            'ffmpeg.threads' => config('ffmpeg.threads'),
            'temporary_directory' => config('ffmpeg.temporary_directory')
        ]);

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
