<?php

namespace App\Services\Translation;

use App\Models\BioTranslation;
use App\Models\User;
use App\Services\Relations\BlockService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BioTranslationService
{
    public function translate(int $profileId, User $viewer, string $target): string
    {
        $target = PostTranslationService::canonicalTarget($target);

        return $this->translateBio(
            $this->visibleUser($profileId, $viewer),
            $viewer,
            $target,
        );
    }

    public function translateWithSource(int $profileId, User $viewer, string $target): array
    {
        $translatedText = $this->translate($profileId, $viewer, $target);
        $profile = $this->visibleUser($profileId, $viewer);
        $translation = BioTranslation::query()
            ->where('user_id', $profileId)
            ->where('target_language', PostTranslationService::canonicalTarget($target))
            ->where('content_hash', hash('sha256', (string) $profile->bio))
            ->first();

        $sourceLanguage = $translation?->source_language;
        if (! $sourceLanguage || $sourceLanguage === 'auto') {
            $sourceLanguage = app(TranslationLanguageDetector::class)->detect((string) $profile->bio);
        }

        return [
            'translated_text' => $translatedText,
            'source_language' => $sourceLanguage,
        ];
    }

    private function translateBio(User $profile, User $viewer, string $target): string
    {
        $profileId = (int) $profile->id;
        $hash = hash('sha256', (string) $profile->bio);

        if ($cached = $this->cached($profileId, $target, $hash)) {
            return $cached->translated_text;
        }

        $lockStore = (string) config('services.translation.lock_store', config('cache.default'));
        $lock = Cache::store($lockStore)->lock(
            "bio-translation:{$profileId}:{$target}",
            max(60, (int) config('services.translation.timeout', 15) + 60),
        );
        if (! $lock->get()) {
            throw new TranslationServiceBusyException('This profile bio is already being translated.');
        }

        try {
            $profile = $this->visibleUser($profileId, $viewer);
            $hash = hash('sha256', (string) $profile->bio);

            if ($cached = $this->cached($profileId, $target, $hash)) {
                return $cached->translated_text;
            }

            $text = (string) $profile->bio;
            abort_if(trim($text) === '' || mb_strlen($text) > (int) config('services.translation.max_text_length', 2000), 422, 'Profile bio is empty or exceeds the configured translation length limit.');

            $detectedSource = app(TranslationLanguageDetector::class)->detect($text);
            $source = config('services.translation.service') === 'libretranslate' ? 'auto' : $detectedSource;
            $translated = app(TranslationService::class)->from($source)->to($target)->translate($text);

            return DB::transaction(function () use ($profileId, $viewer, $target, $hash, $detectedSource, $translated): string {
                $profile = $this->visibleUser($profileId, $viewer);
                if (! hash_equals($hash, hash('sha256', (string) $profile->bio))) {
                    throw new HttpException(409, 'The profile bio changed during translation. Please try again.');
                }

                BioTranslation::updateOrCreate(
                    ['user_id' => $profileId, 'target_language' => $target],
                    ['content_hash' => $hash, 'source_language' => $detectedSource, 'translated_text' => $translated],
                );

                return $translated;
            });
        } finally {
            $lock->release();
        }
    }

    private function cached(int $profileId, string $target, string $hash): ?BioTranslation
    {
        return BioTranslation::query()
            ->where('user_id', $profileId)
            ->where('target_language', $target)
            ->where('content_hash', $hash)
            ->first();
    }

    private function visibleUser(int $profileId, User $viewer): User
    {
        $profile = User::activeById($profileId)->whereNull('deleted_at')->firstOrFail();
        abort_if($viewer->id !== $profile->id && (new BlockService($viewer, $profile))->blockedAny(), 404);

        return $profile;
    }
}
