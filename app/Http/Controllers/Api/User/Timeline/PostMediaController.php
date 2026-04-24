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

namespace App\Http\Controllers\Api\User\Timeline;

use App\Models\Post;
use App\Models\Media;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Actions\Post\DeletePostAction;
use App\Traits\Http\Api\SupportsApiResponses;
use Illuminate\Auth\Access\AuthorizationException;
use App\Services\Filesystem\Delete\FileDeleteService;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostMediaController extends Controller
{
    use SupportsApiResponses,
        InteractsWithDraftPost;

    private FileDeleteService $fileDeleteService;

    public function __construct(FileDeleteService $fileDeleteService)
    {
        $this->fileDeleteService = $fileDeleteService;
    }
    
    public function deleteMedia(Request $request)
    {
        try {
            $postMedia = Media::with('post')->findOrFail($request->get('id', null));
            
            if($postMedia->post->user_id != me()->id) {
                throw new AuthorizationException();
            }

            if($postMedia->type->isGif()) {
                $postMedia->post()->delete();
            }
            else if($postMedia->type->isImage()) {
                $this->fileDeleteService->setStorageDisk($postMedia->disk)->deleteFile($postMedia->source_path);

                $postMedia->delete();

                $this->deleteDraftImagePost($postMedia->post);
            }
            else if($postMedia->type->isVideo() || $postMedia->type->isAudio() || $postMedia->type->isDocument()) {
                (new DeletePostAction($postMedia->post))->execute();
            }

            return $this->responseSuccess();
        }
        catch (AuthorizationException $e) {
            return $this->responseUnauthorizedError();
        }
    }

    private function deleteDraftImagePost(Post $postData)
    {
        if(empty($postData) != true) {
            if($postData->type->isImage() && $postData->media()->count() == 0) {
                $postData->delete();
            }
        }
    }
}
