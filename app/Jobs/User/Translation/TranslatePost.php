<?php

namespace App\Jobs\User\Translation;

use App\Models\Post;
use App\Services\Translation\PostTranslationService;
use App\Services\Translation\TranslationConfigRefresher;
use App\Services\Translation\TranslationServiceBusyException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranslatePost implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;
    public int $timeout = 120;
    public int $uniqueFor = 3600;

    public function __construct(
        public int $postId,
        public string $contentHash,
        public string $target,
    ) {
        $this->onQueue('translation');
    }

    public function uniqueId(): string
    {
        return "translate-post:{$this->postId}:{$this->contentHash}:{$this->target}";
    }

    public function backoff(): array
    {
        return [15, 60, 300, 900];
    }

    public function handle(PostTranslationService $translations, TranslationConfigRefresher $configRefresher): void
    {
        $configRefresher->refresh();

        if (! config('services.translation.enabled', true) || config('services.translation.service') !== 'openai') {
            return;
        }

        try {
            $translations->prepareTranslation($this->postId, $this->target, $this->contentHash);
        } catch (Throwable $exception) {
            if (! $this->shouldRetry($exception)) {
                Log::warning('Post translation was not retried because the provider rejected the request.', [
                    'post_id' => $this->postId,
                    'target' => $this->target,
                    'status' => $exception instanceof RequestException ? $exception->response->status() : null,
                ]);
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof TranslationServiceBusyException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            $status = $exception->response->status();

            return $status === 408 || $status === 429 || $status >= 500;
        }

        return ! str_contains(strtolower($exception->getMessage()), 'invalid response')
            && ! str_contains(strtolower($exception->getMessage()), 'oversized response')
            && ! str_contains(strtolower($exception->getMessage()), 'explanatory text');
    }
}
