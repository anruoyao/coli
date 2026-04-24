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

use Exception;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Enums\Post\PostStatus;
use App\Http\Controllers\Controller;
use App\Actions\Post\DeletePostAction;
use App\Services\Text\LinkPreviewService;
use App\Services\Reaction\ReactionService;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Events\User\Timeline\PostCreatedEvent;
use App\Http\Resources\User\Timeline\QuoteResource;
use App\Http\Resources\User\Timeline\TimelineResource;
use App\Http\Resources\User\Morph\LinkSnapshotResource;
use App\Http\Resources\User\Timeline\ReactionCollection;
use App\Notifications\User\Post\PostReactedNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Resources\User\Timeline\Editor\DraftPostResource;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPollData;
use App\Traits\Http\Controllers\Api\User\Timeline\ValidatesPostData;
use App\Traits\Http\Controllers\Api\User\Timeline\InteractsWithDraftPost;

class PostController extends Controller
{
    use SupportsApiResponses,
        AuthorizesRequests,
        InteractsWithDraftPost,
        ValidatesPollData,
        ValidatesPostData;
    
    public function createPost(Request $request)
    {
        $this->initializePostAndValidateData($request);
        
        $this->defineAndSetPostStatus();

        if($this->draftPost->content) {
            $this->draftPost->text_language = $this->draftPost->getContentLanguage();
        }

        $quotedPostId = $request->integer('quoted_post_id', null);

        if($quotedPostId) {
            $quotedPost = Post::activeById($quotedPostId)->first();

            if($quotedPost) {
                $this->draftPost->quote_post_id = $quotedPost->id;
                $this->draftPost->is_quoting = true;

                $quotedPost->increment('quotes_count', 1);
            }
        }

        $postMarks = $request->array('marks', []);

        if(! empty($postMarks['is_ai_generated'])) {
            $this->draftPost->is_ai_generated = true;
        }

        if(! empty($postMarks['is_sensitive'])) {
            $this->draftPost->is_sensitive = true;
        }
        
        $this->draftPost->save();

        $finalPost = $this->getFinialPost(); 

        $finalPost->user->increment('publications_count', 1);

        event(new PostCreatedEvent($finalPost));

        return $this->responseSuccess([
            'data' => TimelineResource::make($finalPost)
        ]);
    }

    public function bookmarkPost(Request $request)
    {
        $postId = $request->integer('id');

        $postData = Post::activeById($postId)->first();

        if($postData) {
            $bookmarkedStatus = $postData->isBookmarkedBy(me()->id);

            if($bookmarkedStatus) {
                $postData->removeBookmark(me()->id);
            }
            else {
                $postData->addBookmark(me()->id);
            }

            return $this->responseSuccess([
                'data' => [
                    'bookmarked' => (! $bookmarkedStatus)
                ]
            ]);
        }
        else {
            return $this->responseResourceNotFoundError('Post', $postId);
        }
    }

    public function getDraftPost(Request $request)
    {
        $this->fetchOrInitializeDraftPost();

        $quotedPostId = $request->integer('quoted_post_id', null);

        $responseData = [
            'data' => [
                'draft' => null
            ]
        ];

        if ($this->draftPost->exists) {
            $responseData['data']['draft'] = DraftPostResource::make($this->draftPost);
        }

        if($quotedPostId) {
            $quotedPost = Post::activeById($quotedPostId)->with('user')->first();

            if($quotedPost) {
                $responseData['data']['quoted_post'] = QuoteResource::make($quotedPost);
            }
        }

        return $this->responseSuccess($responseData);
    }

    private function defineAndSetPostStatus()
    {
        $this->draftPost->status = PostStatus::ACTIVE;

        if($this->draftPost->type->isVideo()) {
            $this->draftPost->status = PostStatus::PROCESSING_VIDEO;
        }
    }

    private function initializePostAndValidateData(Request $request)
    {
        $this->fetchOrInitializeDraftPost();

        $this->validatePostData([
            'content' => $request->get('content', null)
        ]);

        if($request->filled('content')) {
            $this->draftPost->content = normalize_nls($request->get('content', ''));
        }

        if($this->draftPost->type->isPoll()) {
            $this->validatePollData([
                'poll_options' => $request->get('poll_options', [])
            ]);

            if(! $this->draftPost->exists) {
                $this->draftPost->save();
            }

            $this->draftPost->poll->update([
                'choices' => $request->get('poll_options')
            ]);
        }
    }

    public function deletePost(Request $request)
    {
        $postId = $request->integer('id');

        $postData = Post::findOrFail($postId);

        $this->authorize('delete', $postData);

        (new DeletePostAction($postData))->execute();

        $postData->user->decrementValue('publications_count', 1);

        return $this->responseSuccess([
            'data' => null
        ]);
    }

    private function getFinialPost()
    {
        return $this->draftPost->refresh();
    }

    public function addReaction(Request $request, ReactionService $reactionService)
    {
        $request->validate([
            'post_id' => ['required', 'integer'],
            'unified_id' => ['required', 'string', 'min:4', 'max:32']
        ]);

        $reactionUnifiedId = $request->get('unified_id');
        $postId = $request->get('post_id');

        try {
            $postData = Post::activeById($postId)->firstOrFail();

            $isReactionAdded = $reactionService
                ->setUserId(me()->id)
                ->setReactable($postData)
                ->setUnifiable(strtolower($reactionUnifiedId))
                ->handleReaction();
                
            if (! $postData->is_owner && $isReactionAdded) {
                $postData->user->notify(new PostReactedNotification($postData, strtolower($reactionUnifiedId)));
            }

            return $this->responseSuccess([
                'data' => ReactionCollection::make($postData->reactions)
            ]);
        }
        
        catch (Exception $e) {
            return $this->responseError([
                'message' => $e->getMessage(),
                'errors' => [
                    $e->getMessage()
                ]
            ]);
        }
    }

    public function previewLink(Request $request)
    {
        $request->validate([
            'url' => ['required', 'string', 'url']
        ]);

        $this->fetchOrInitializeDraftPost();

        $this->draftPost->linkSnapshot()->delete();

        $url = $request->get('url');

        $linkPreviewService = app(LinkPreviewService::class);

        $linkPreview = $linkPreviewService->previewLink($url);

        // Save the draft post first to ensure it has an ID
        $this->draftPost->content = $url;
        $this->draftPost->save();

        $linkSnapshotData = $this->draftPost->linkSnapshot()->create([
            'title' => Str::limit($linkPreview['title'], 250),
            'description' => Str::limit($linkPreview['description'], 250),
            'url' => Str::limit($linkPreview['url'], 250),
            'metadata' => [
                'is_fallback' => isset($linkPreview['is_fallback']) ? $linkPreview['is_fallback'] : false,
                'preview_image_base64' => $linkPreview['preview_image_base64']
            ]
        ]);
        
        return $this->responseSuccess([
            'data' => LinkSnapshotResource::make($linkSnapshotData)
        ]);
    }

    public function deleteLinkSnapshot()
    {
        $this->fetchOrInitializeDraftPost();

        $this->draftPost->linkSnapshot()->delete();

        $this->draftPost->content = '';
        $this->draftPost->save();

        return $this->responseSuccess([
            'data' => null
        ]);
    }
}
