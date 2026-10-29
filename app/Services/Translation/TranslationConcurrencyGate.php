<?php

namespace App\Services\Translation;

use Closure;
use Illuminate\Support\Facades\Cache;

class TranslationConcurrencyGate
{
    public function run(Closure $callback): mixed
    {
        $storeName = (string) config('services.translation.lock_store', 'redis');
        $store = Cache::store($storeName);
        $limit = min(5, max(1, (int) config('services.translation.openai_concurrency', 5)));
        $ttl = max(60, (int) config('services.translation.timeout', 15) + 30);

        for ($slot = 0; $slot < $limit; $slot++) {
            $lock = $store->lock("translation:openai:slot:{$slot}", $ttl);
            if ($lock->get()) {
                try {
                    return $callback();
                } finally {
                    $lock->release();
                }
            }
        }

        throw new TranslationServiceBusyException('Translation service is at capacity.');
    }
}
