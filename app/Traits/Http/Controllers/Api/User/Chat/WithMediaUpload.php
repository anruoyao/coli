<?php

namespace App\Traits\Http\Controllers\Api\User\Chat;

use App\Constants\Filesystem;
use App\Enums\Chat\MessageType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Models\Message;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\AudioUploadService;
use App\Services\Filesystem\Upload\ImageUploadService;
use App\Services\Filesystem\Upload\VideoThumbnailService;
use App\Services\Filesystem\Upload\VideoUploadService;
use Exception;
use FFMpeg\Format\Video\X264;
use Illuminate\Http\UploadedFile;

trait WithMediaUpload
{
    private VideoUploadService $videoUploadService;
    private RoundRobinService $roundRobinService;
    private VideoThumbnailService $videoThumbnailService;
    private ImageUploadService $imageUploadService;
    private AudioUploadService $audioUploadService;
    private Message $messageData;
    private int $mediaDuration;

    private function uploadMedia(Message $messageData, UploadedFile $mediaData, string $mediaType, int $mediaDuration)
    {
        $this->videoUploadService = app(VideoUploadService::class);
        $this->audioUploadService = app(AudioUploadService::class);
        $this->roundRobinService = app(RoundRobinService::class);
        $this->videoThumbnailService = app(VideoThumbnailService::class);
        $this->imageUploadService = app(ImageUploadService::class);
        $this->messageData = $messageData;
        $this->mediaDuration = $mediaDuration;

        if($mediaType === 'video') {
            return $this->uploadVideo($mediaData);
        }
        elseif($mediaType === 'audio') {
            return $this->uploadAudio($mediaData);
        }
        elseif($mediaType === 'image') {
            return $this->uploadImage($mediaData);
        }
    }

    private function uploadImage(UploadedFile $mediaData)
    {
        try {
            $imageStorageDisk = $this->roundRobinService->getNextDisk();
            $imageData = $this->imageUploadService
                ->load($mediaData->getRealPath())
                ->setNamespace(Filesystem::mediaNamespace('chats/images'))
                ->setStorageDisk($imageStorageDisk)
                ->watermark()
                ->compress(20)
                ->upload();

            $this->messageData->media()->create([
                'source_path' => $imageData['image_path'],
                'type' => MediaType::IMAGE,
                'status' => MediaStatus::PROCESSED,
                'disk' => $imageData['disk'],
                'extension' => $mediaData->getClientOriginalExtension(),
                'mime' => $mediaData->getClientMimeType(),
                'size' => $imageData['image_size'],
                'lqip_base64' => null,
                'metadata' => []
            ]);

            $this->messageData->update([
                'type' => MessageType::IMAGE,
            ]);
        }
        catch(Exception $e) {
            // Pass
        }
    }

    private function uploadVideo(UploadedFile $chatVideoFile)
    {
        try {
            $videoStorageDisk = $this->roundRobinService->getNextDisk();

            $videoData = $this->videoUploadService
                ->setStorageDisk($videoStorageDisk)
                ->tempSaveLocally($chatVideoFile);

            $videoThumbnailPath = $this->videoThumbnailService
                ->setSecondsOffset(1)
                ->generateThumbnail($videoData['video_path']);

            if(config('chat.enable_video_compression')) {
                // Compress video
                $this->compressVideo(storage_local_path($videoData['video_path']));
            }
            else {
                $this->videoUploadService->setDefaultExtension($chatVideoFile->getClientOriginalExtension());
            }

            $imageData = $this->imageUploadService
                ->load($videoThumbnailPath)
                ->setNamespace(Filesystem::mediaNamespace('chats/video_thumbnails'))
                ->setStorageDisk($videoStorageDisk)
                ->compress(20)
                ->upload();

            unlink($videoThumbnailPath);

            $videoData = $this->videoUploadService
                ->setStorageDisk($videoStorageDisk)
                ->setNamespace(Filesystem::mediaNamespace('chats/videos'))
                ->upload(storage_local_path($videoData['video_path']));

            $this->messageData->media()->create([
                'source_path' => $videoData['video_path'],
                'type' => MediaType::VIDEO,
                'status' => MediaStatus::PROCESSED,
                'disk' => $videoStorageDisk,
                'extension' => $chatVideoFile->getClientOriginalExtension(),
                'mime' => $chatVideoFile->getClientMimeType(),
                'size' => $chatVideoFile->getSize(),
                'thumbnail_path' => $imageData['image_path'],
                'thumbnail_size' => $imageData['image_size'],
                'thumbnail_disk' => $imageData['disk'],
                'metadata' => [
                    'duration' => parse_duration(intval($this->mediaDuration)),
                    'is_portrait' => false
                ]
            ]);

            $this->messageData->update([
                'type' => MessageType::VIDEO_CIRCLE,
            ]);
        } catch (Exception $e) {
            // Pass
        }
    }

    private function uploadAudio(UploadedFile $chatAudioFile)
    {
        try {
            $audioData = $this->audioUploadService
                ->setStorageDisk($this->roundRobinService->getNextDisk())
                ->tempSaveLocally($chatAudioFile);

            $audioData = $this->audioUploadService
                ->setNamespace(Filesystem::mediaNamespace('chats/audios'))
                ->setStorageDisk($audioData['disk'])
                ->setDefaultExtension($chatAudioFile->getClientOriginalExtension())
                ->upload(storage_local_path($audioData['audio_path']));

            $this->messageData->media()->create([
                'source_path' => $audioData['audio_path'],
                'type' => MediaType::AUDIO,
                'status' => MediaStatus::PROCESSED,
                'disk' => $audioData['disk'],
                'extension' => $chatAudioFile->getClientOriginalExtension(),
                'mime' => $chatAudioFile->getClientMimeType(),
                'size' => $chatAudioFile->getSize(),
                'metadata' => [
                    'duration' => parse_duration(intval($this->mediaDuration)),
                    'file_name' => $chatAudioFile->getClientOriginalName()
                ]
            ]);

            $this->messageData->update([
                'type' => MessageType::AUDIO,
            ]);
        }
        catch(Exception $e) {
            // Pass
        }
    }

    private function compressVideo(string $videoPath)
    {
        $ffmpeg = $this->videoUploadService->getFFMpeg();

        $video = $ffmpeg->open($videoPath);
        $format = new X264();
        $format->setKiloBitrate(800)
               ->setAudioKiloBitrate(64)
               ->setAdditionalParameters([
                   '-preset',
                   'veryfast',
                   '-crf',
                   '28',
                   '-movflags',
                   '+faststart',
                   '-vf',
                   'scale=480:480:force_original_aspect_ratio=increase,crop=480:480',
               ]);

        $videoTempNewPath = storage_local_path($this->videoUploadService->generateVideoTemporaryFilePath("compressed.{$this->videoUploadService->videoDefaultExtension}"));

        $video->save($format, $videoTempNewPath);

        rename($videoTempNewPath, $videoPath);
    }
}
