<?php

namespace App\Jobs\User\Timeline;

use Exception;
use App\Models\Post;
use App\Constants\Filesystem;
use FFMpeg\Format\Video\X264;
use App\Enums\Post\PostStatus;
use App\Enums\Media\MediaStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Events\User\Timeline\MediaProcessedEvent;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Services\Filesystem\Upload\VideoUploadService;

class ConvertAndCompressPostVideo implements ShouldQueue
{
    use Queueable;

    public $timeout = (60 * 30); // 30 minutes
    
    private $postData;

    public function __construct(Post $postData)
    {
        $this->postData = $postData;
    }

    public function handle(): void
    {
        try {
            $videoUploadService = app(VideoUploadService::class);
            $fileDeleteService = app(FileDeleteService::class);

            $postMedia = $this->postData->media()->first();

            if (! $videoUploadService) {
                throw new Exception('Required services are not available. Ensure that fileUploaderService and ffmpegService are properly injected.');
            }

            // Get video video local temporary path
            $videoTempOldPath = $postMedia->source_path;

            // Generate new video temporary path for compressed video marking it as compressed. [compressed.mp4]
            $videoTempNewPath = $videoUploadService->generateVideoTemporaryFilePath("compressed.{$videoUploadService->videoDefaultExtension}");

            $ffmpeg = $videoUploadService->getFFMpeg();
            $videoOldAbsLocalPath = storage_local_path($videoTempOldPath);
            $videoNewAbsLocalPath = storage_local_path($videoTempNewPath);

            if(config('logging.debugging.video_process_logging')) {
                $fileOldExists = (file_exists($videoOldAbsLocalPath)) ? 'Yes' : 'No';

                Log::info("Video with path: {$videoOldAbsLocalPath} loaded. Video file exists: {$fileOldExists}");
            }

            // Compress video and save to new path converting it to mp4
            $video = $ffmpeg->open($videoOldAbsLocalPath);
            $format = new X264();
            $format = $format->setKiloBitrate(1000);
            $watermarkConfig = config('assets.watermark');
            $video->filters()->watermark($watermarkConfig['local_path'], [
                'position' => $watermarkConfig['position'],
                'x' => $watermarkConfig['x'],
                'y' => $watermarkConfig['y'],
            ]);

            $video->save($format, $videoNewAbsLocalPath);

            if(file_exists($videoNewAbsLocalPath)) {
                // Upload compressed video to public disk and update post media
                // Public disk is determined by post media with round robin algorithm
                // and it is not local public folder of the application.

                $videoData = $videoUploadService
                    ->setStorageDisk($postMedia->disk)
                    ->setNamespace(Filesystem::getPostVideoNamespace())
                    ->upload($videoNewAbsLocalPath);

                $postMedia->source_path = $videoData['video_path'];
                $postMedia->status = MediaStatus::PROCESSED;
                $postMedia->save();

                $this->postData->status = PostStatus::ACTIVE;

                $this->postData->save();

                if(config('logging.debugging.video_process_logging')) {
                    $fileNewExists = file_exists($videoNewAbsLocalPath) ? 'Yes' : 'No';

                    Log::info("Compressed video with new path: {$videoNewAbsLocalPath} saved. Video new file exists: {$fileNewExists}");
                }

                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempOldPath);
                $fileDeleteService->setStorageDisk('local')->deleteFile($videoTempNewPath);
                
                // Broadcast video processed event with updated post media and user id
                // to notify users that video has been processed.
                
                try {
                    event(new MediaProcessedEvent($postMedia->refresh(), $this->postData->user_id));
                } catch (Exception $e) {
                    Log::error('Failed to broadcast video processed event: ' . $e->getMessage());
                }
            }
        }
        
        catch (Exception $e) {
            Log::error('Post video processing failed after 5 attempts. Error: ' . $e->getMessage());

            $this->fail($e);
        }
    }

    public function tries(): int
    {
        return 5;
    }
}
