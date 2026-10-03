<?php

namespace App\Traits\Http\Controllers\Api\User\Chat;

use App\Constants\Filesystem;
use App\Enums\Chat\MessageType;
use App\Enums\Media\MediaStatus;
use App\Enums\Media\MediaType;
use App\Models\Message;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Services\Filesystem\Upload\ImageUploadService;
use Exception;
use Illuminate\Http\UploadedFile;

trait WithMediaUpload
{
    private RoundRobinService $roundRobinService;
    private ImageUploadService $imageUploadService;
    private Message $messageData;

    /**
     * 上传并挂载媒体。返回是否成功。
     * 失败时必须由调用方删除已创建的空消息行——旧实现静默吞掉异常，
     * 数据库会残留「无文本、无媒体」的消息，双方界面显示为空白气泡。
     *
     * 聊天仅保留图片媒体（录音/录像已下线，不再处理 video/audio 类型）。
     */
    private function uploadMedia(Message $messageData, UploadedFile $mediaData, string $mediaType): bool
    {
        $this->roundRobinService = app(RoundRobinService::class);
        $this->imageUploadService = app(ImageUploadService::class);
        $this->messageData = $messageData;

        if($mediaType === 'image') {
            return $this->uploadImage($mediaData);
        }

        return false;
    }

    private function uploadImage(UploadedFile $mediaData): bool
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

            return true;
        }
        catch(Exception $e) {
            report($e);
            return false;
        }
    }
}
