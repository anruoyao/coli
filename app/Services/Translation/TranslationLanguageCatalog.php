<?php

namespace App\Services\Translation;

use App\Models\Locale;
use Throwable;

class TranslationLanguageCatalog
{
    public function activeLanguages(): array
    {
        try {
            return Locale::query()
                ->where('status', true)
                ->orderBy('id')
                ->get(['alpha_2_code', 'name'])
                ->mapWithKeys(fn (Locale $locale): array => [
                    PostTranslationService::canonicalTarget($locale->alpha_2_code) => [
                        'code' => PostTranslationService::canonicalTarget($locale->alpha_2_code),
                        'name' => $locale->name,
                    ],
                ])
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public function supportedTargets(): array
    {
        $legacy = ['zh', 'zh-cn', 'zh-hans', 'en', 'en-us', 'en-gb', 'ru', 'ja', 'ko', 'fr', 'de', 'es', 'pt', 'ar', 'it', 'tr', 'pl', 'uk'];
        if (config('services.translation.service') !== 'openai') {
            return $legacy;
        }

        return array_keys($this->activeLanguages());
    }

    public function targetName(string $target): string
    {
        $canonical = PostTranslationService::canonicalTarget($target);
        $active = $this->activeLanguages();

        return $active[$canonical]['name'] ?? $canonical;
    }
}
