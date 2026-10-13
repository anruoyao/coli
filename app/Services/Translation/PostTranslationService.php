<?php

namespace App\Services\Translation;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Services\Relations\BlockService;
use App\Services\Translation\Drivers\Nllb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PostTranslationService
{
    public function translate(int $postId, User $viewer, string $target): string
    {
        $target = self::canonicalTarget($target);
        $post = $this->visiblePost($postId, $viewer);
        $hash = hash('sha256', $post->content);

        if ($cached = $this->cached($postId, $target, $hash)) {
            return $cached->translated_text;
        }

        $lock = Cache::lock("post-translation:{$postId}:{$target}", max(60, (int) config('services.translation.timeout', 15) + 60));
        abort_unless($lock->get(), 429, 'This post is being translated. Please try again shortly.');

        try {
            $post = $this->visiblePost($postId, $viewer);
            $hash = hash('sha256', $post->content);
            if ($cached = $this->cached($postId, $target, $hash)) {
                return $cached->translated_text;
            }

            $text = $post->content;
            abort_if(trim($text) === '' || mb_strlen($text) > (int) config('services.translation.max_text_length', 2000), 422, 'Text exceeds the configured translation length limit or is empty.');
            $source = config('services.translation.service') === 'nllb'
                ? Nllb::detectSource(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
                : 'auto';
            $translated = app(TranslationService::class)->from($source)->to($target)->translate($text);

            // Lock only during persistence, so inference never holds a database row lock.
            return DB::transaction(function () use ($postId, $viewer, $target, $hash, $source, $translated) {
                $post = $this->visiblePost($postId, $viewer, true);
                abort_unless(hash_equals($hash, hash('sha256', $post->content)), 409, 'The post changed during translation. Please try again.');

                PostTranslation::updateOrCreate(
                    ['post_id' => $postId, 'target_language' => $target],
                    ['content_hash' => $hash, 'source_language' => $source, 'translated_text' => $translated]
                );

                return $translated;
            });
        } finally {
            $lock->release();
        }
    }

    public static function canonicalTarget(string $target): string
    {
        return match (strtolower(trim($target))) {
            'zh', 'zh-cn', 'zh-hans' => 'zh',
            'en', 'en-us', 'en-gb' => 'en',
            default => strtolower(trim($target)),
        };
    }

    private function cached(int $postId, string $target, string $hash): ?PostTranslation
    {
        return PostTranslation::where('post_id', $postId)
            ->where('target_language', $target)->where('content_hash', $hash)->first();
    }

    private function visiblePost(int $postId, User $viewer, bool $lock = false): Post
    {
        $query = Post::activeById($postId)->whereHas('user', fn ($query) => $query->active());
        if ($lock) {
            $query->lockForUpdate();
        }
        $post = $query->firstOrFail();
        abort_if($viewer->id !== $post->user_id && (new BlockService($viewer, $post->user))->blockedAny(), 404);

        return $post;
    }
}
