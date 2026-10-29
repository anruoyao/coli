<?php

namespace App\Http\Controllers\Api\User\Translator;

use Illuminate\Http\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Validation\Rule;
use Throwable;
use App\Http\Controllers\Controller;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Translation\TranslationService;
use App\Services\Translation\PostTranslationService;
use App\Services\Translation\Drivers\Nllb;
use App\Services\Translation\TranslationLanguageCatalog;
use App\Services\Translation\TranslationServiceBusyException;

class TranslatorController extends Controller
{
    use SupportsApiResponses;

    public function translate(Request $request)
    {
        $languages = app(TranslationLanguageCatalog::class)->supportedTargets();
        $validated = $request->validate([
            'text' => $request->filled('post_id') ? ['nullable', 'string'] : ['required', 'string', 'max:' . (int) config('services.translation.max_text_length', 2000)],
            'source' => ['nullable', 'string', Rule::in(array_merge(['auto'], $languages))],
            'target' => ['nullable', 'string', Rule::in($languages)],
            'post_id' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! config('services.translation.enabled', true)) {
            return $this->responseError(['message' => 'Translation is disabled.'], 503);
        }

        try {
            $target = $validated['target'] ?? config('services.translation.default_target', 'en');
            if (! empty($validated['post_id'])) {
                $translatedText = app(PostTranslationService::class)->translate((int) $validated['post_id'], $request->user('sanctum'), $target);

                return $this->responseSuccess(['data' => ['translated_text' => $translatedText]]);
            }

            $service = app(TranslationService::class);
            $source = $validated['source'] ?? config('services.translation.default_source', 'auto');
            if ($source === 'auto' && config('services.translation.service') === 'nllb') {
                $source = Nllb::detectSource(html_entity_decode($validated['text'], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
            $translatedText = $service->from($source)->to($target)->translate($validated['text']);

            return $this->responseSuccess(['data' => ['translated_text' => $translatedText]]);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            throw $e;
        } catch (Throwable $e) {
            $cause = $e->getPrevious();
            $requestException = $e instanceof RequestException ? $e : ($cause instanceof RequestException ? $cause : null);
            if ($requestException instanceof RequestException && $requestException->response->status() === 413) {
                return $this->responseError(['message' => 'Text exceeds the configured translation token limit.'], 422);
            }
            if ($e instanceof TranslationServiceBusyException || ($requestException instanceof RequestException && in_array($requestException->response->status(), [429, 503]))) {
                $status = $e instanceof TranslationServiceBusyException ? 429 : $requestException->response->status();

                return $this->responseError(['message' => 'Translation service is busy. Please try again shortly.'], $status)
                    ->header('Retry-After', '5');
            }

            return $this->responseError(['message' => 'Translation service is temporarily unavailable.'], 503);
        }
    }
}
