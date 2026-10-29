<?php

namespace App\Jobs\User\Translation;

use App\Enums\Post\PostStatus;
use App\Models\Post;
use App\Services\Translation\PostTranslationService;
use App\Services\Translation\TranslationLanguageCatalog;
use App\Services\Translation\TranslationLanguageDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PreparePostTranslations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $postId)
    {
        $this->onQueue('translation');
    }

    public function handle(
        TranslationLanguageCatalog $catalog,
        TranslationLanguageDetector $detector,
    ): void {
        if (! $this->enabled()) {
            return;
        }

        $post = Post::query()
            ->whereKey($this->postId)
            ->where('status', PostStatus::ACTIVE)
            ->whereHas('user', fn ($query) => $query->active())
            ->first();

        if (! $post || trim((string) $post->content) === '') {
            return;
        }

        if (mb_strlen($post->content) > (int) config('services.translation.max_text_length', 2000)) {
            return;
        }

        $source = PostTranslationService::canonicalTarget($post->text_language ?: $detector->detect($post->content));
        $hash = hash('sha256', $post->content);

        foreach (array_keys($catalog->activeLanguages()) as $target) {
            $target = PostTranslationService::canonicalTarget($target);
            if ($target === $source) {
                continue;
            }

            TranslatePost::dispatch($post->id, $hash, $target)->afterCommit();
        }
    }

    private function enabled(): bool
    {
        return (bool) config('services.translation.enabled', true)
            && config('services.translation.service') === 'openai'
            && (bool) config('services.translation.pretranslate_enabled', false);
    }
}
