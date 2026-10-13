<?php

namespace App\Http\Controllers\Api\User\Translator;

use Illuminate\Http\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\Rule;
use Throwable;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Translation\TranslationService;
use App\Services\Language\LangDetectionService;

class TranslatorController extends Controller
{
    use SupportsApiResponses;

    public function translate(Request $request)
    {
        $languages = ['zh', 'zh-cn', 'zh-hans', 'en', 'en-us', 'en-gb', 'ru', 'ja', 'ko', 'fr', 'de', 'es', 'pt', 'ar', 'it', 'tr', 'pl', 'uk'];
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:' . (int) config('services.translation.max_text_length', 2000)],
            'source' => ['nullable', 'string', Rule::in(array_merge(['auto'], $languages))],
            'target' => ['nullable', 'string', Rule::in($languages)],
        ]);

        if (! config('services.translation.enabled', true)) {
            return $this->responseError(['message' => 'Translation is disabled.'], 503);
        }

        try {
            $service = app(TranslationService::class);
            $source = $validated['source'] ?? config('services.translation.default_source', 'auto');
            if ($source === 'auto' && config('services.translation.service') === 'nllb') {
                $source = strtolower((new LangDetectionService($validated['text']))->detect());
            }
            $target = $validated['target'] ?? config('services.translation.default_target', 'en');
            $translatedText = $service->from($source)->to($target)->translate($validated['text']);

            return $this->responseSuccess(['data' => ['translated_text' => $translatedText]]);
        } catch (Throwable $e) {
            $cause = $e->getPrevious();
            if ($cause instanceof RequestException && $cause->response->status() === 413) {
                return $this->responseError(['message' => 'Text exceeds the configured translation token limit.'], 422);
            }
            if ($cause instanceof RequestException && in_array($cause->response->status(), [429, 503])) {
                return $this->responseError(['message' => 'Translation service is busy. Please try again shortly.'], $cause->response->status())
                    ->header('Retry-After', '5');
            }

            return $this->responseError(['message' => 'Translation service is temporarily unavailable.'], 503);
        }
    }
}
