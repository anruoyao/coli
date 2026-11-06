<?php

namespace App\Actions\Post;

use App\Enums\Post\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SetProfilePinAction
{
    public const LIMIT = 3;

    public function execute(User $user, int $postId, bool $pinned): Post
    {
        return DB::transaction(function () use ($user, $postId, $pinned) {
            // Serialize all pin changes for this user, including the first pin.
            $owner = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $post = Post::whereKey($postId)->lockForUpdate()->firstOrFail();

            Gate::forUser($owner)->authorize('pin', $post);

            if ($pinned && ($post->status !== PostStatus::ACTIVE || $post->is_sensitive)) {
                throw ValidationException::withMessages(['pinned' => __('post.pin_inactive')]);
            }

            if ($post->profile_pinned === $pinned) {
                return $post;
            }

            if ($pinned) {
                $pins = Post::where('user_id', $owner->id)->active()->excludeAds()
                    ->where('profile_pinned', true)->lockForUpdate()->get(['id']);

                if ($pins->count() >= self::LIMIT) {
                    throw ValidationException::withMessages(['pinned' => __('post.pin_limit', ['limit' => self::LIMIT])]);
                }
            }

            $post->profile_pinned = $pinned;
            $post->profile_pinned_at = $pinned ? now() : null;
            $post->save();

            return $post;
        }, 3);
    }
}
