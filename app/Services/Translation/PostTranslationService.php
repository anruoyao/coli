<?php

namespace App\Services\Translation;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Services\Relations\BlockService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PostTranslationService
{
    public function translate(int $postId, User $viewer, string $target): string
    {
        $target = self::canonicalTarget($target);

        return $this->translatePost(
            $this->visiblePost($postId, $viewer),
            $target,
            fn (): Post => $this->visiblePost($postId, $viewer),
        );
    }

    public function translateWithSource(int $postId, User $viewer, string $target): array
    {
        $target = self::canonicalTarget($target);
        $translatedText = $this->translate($postId, $viewer, $target);
        $post = $this->visiblePost($postId, $viewer);
        $translation = PostTranslation::query()
            ->where('post_id', $postId)
            ->where('target_language', $target)
            ->where('content_hash', hash('sha256', $post->content))
            ->first();

        $sourceLanguage = $translation?->source_language;
        if (! $sourceLanguage || $sourceLanguage === 'auto') {
            $sourceLanguage = app(TranslationLanguageDetector::class)->detect($post->content);
        }

        return [
            'translated_text' => $translatedText,
            'source_language' => $sourceLanguage,
        ];
    }

    public function prepareTranslation(int $postId, string $target, string $expectedHash): void
    {
        $target = self::canonicalTarget($target);
        $post = Post::activeById($postId)
            ->whereHas('user', fn ($query) => $query->active())
            ->first();

        if (! $post || ! hash_equals($expectedHash, hash('sha256', $post->content))) {
            return;
        }

        $this->translatePost(
            $post,
            $target,
            fn (): ?Post => Post::activeById($postId)
                ->whereHas('user', fn ($query) => $query->active())
                ->first(),
            $expectedHash,
        );
    }

    private function translatePost(Post $post, string $target, callable $reloadPost, ?string $expectedHash = null): string
    {
        $postId = (int) $post->id;
        $hash = hash('sha256', $post->content);
        if ($expectedHash !== null && ! hash_equals($expectedHash, $hash)) {
            return '';
        }

        if ($cached = $this->cached($postId, $target, $hash)) {
            return $cached->translated_text;
        }

        $lockStore = (string) config('services.translation.lock_store', config('cache.default'));
        $lock = Cache::store($lockStore)->lock("post-translation:{$postId}:{$target}", max(60, (int) config('services.translation.timeout', 15) + 60));
        if (! $lock->get()) {
            throw new TranslationServiceBusyException('This post is already being translated.');
        }

        try {
            $post = $reloadPost();
            if (! $post) {
                if ($expectedHash !== null) {
                    return '';
                }

                throw new HttpException(404, 'Post not found.');
            }

            $hash = hash('sha256', $post->content);
            if ($expectedHash !== null && ! hash_equals($expectedHash, $hash)) {
                return '';
            }

            if ($cached = $this->cached($postId, $target, $hash)) {
                return $cached->translated_text;
            }

            $text = $post->content;
            abort_if(trim($text) === '' || mb_strlen($text) > (int) config('services.translation.max_text_length', 2000), 422, 'Text exceeds the configured translation length limit or is empty.');
            $detectedSource = app(TranslationLanguageDetector::class)->detect($text);
            $source = config('services.translation.service') === 'libretranslate' ? 'auto' : $detectedSource;
            $translated = app(TranslationService::class)->from($source)->to($target)->translate($text);

            return DB::transaction(function () use ($postId, $target, $hash, $detectedSource, $translated, $reloadPost, $expectedHash): string {
                $post = $reloadPost();
                if (! $post || ! hash_equals($hash, hash('sha256', $post->content))) {
                    if ($expectedHash !== null) {
                        return '';
                    }

                    throw new HttpException($post ? 409 : 404, 'The post changed during translation. Please try again.');
                }

                PostTranslation::updateOrCreate(
                    ['post_id' => $postId, 'target_language' => $target],
                    ['content_hash' => $hash, 'source_language' => $detectedSource, 'translated_text' => $translated]
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

    private function visiblePost(int $postId, User $viewer): Post
    {
        $post = Post::activeById($postId)->whereHas('user', fn ($query) => $query->active())->firstOrFail();
        abort_if($viewer->id !== $post->user_id && (new BlockService($viewer, $post->user))->blockedAny(), 404);

        return $post;
    }
}
