<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Constants;

abstract class Filesystem
{
    const EXTERNAL_DISK_NAME = 'external';

    const IMAGE_PLACEHOLDER_WIDTH = 128;

    const IMAGE_PLACEHOLDER_BLUR = 1;

    const POST_IMAGE_UPLOAD_NAMESPACE = 'post-image';

    static function getPostImageNamespace() {
        return config('filesystems.upload_namespaces.post_images');
    }

    static function getAdCreativeNamespace() {
        return config('filesystems.upload_namespaces.ad_creatives');
    }

    static function getProductImageNamespace() {
        return config('filesystems.upload_namespaces.product_images');
    }

    static function getPostVideoThumbnailNamespace() {
        return config('filesystems.upload_namespaces.post_video_thumbnails');
    }

    static function getPostVideoNamespace() {
        return config('filesystems.upload_namespaces.post_videos');
    }

    static function getPostDocumentNamespace() {
        return config('filesystems.upload_namespaces.post_documents');
    }

    static function getPostAudioNamespace() {
        return config('filesystems.upload_namespaces.post_audios');
    }

    static function getStoryImageNamespace() {
        return config('filesystems.upload_namespaces.story_images');
    }
    
    static function getStoryVideoThumbnailNamespace() {
        return config('filesystems.upload_namespaces.story_video_thumbnails');
    }

    static function getStoryVideoNamespace() {
        return config('filesystems.upload_namespaces.story_videos');
    }
}
