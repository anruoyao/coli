<?php

namespace App\Services\Translation\Drivers;

use Exception;
use Illuminate\Support\Facades\Http;
use App\Services\Translation\Drivers\Interfaces\TranslationDriverInterface;

class Nllb implements TranslationDriverInterface
{
    private string $from;
    private string $to;

    public function __construct()
    {
        $this->from = (string) config('services.translation.default_source', 'auto');
        $this->to = (string) config('services.translation.default_target', 'en');
    }

    public function from(string $from): self
    {
        $this->from = self::languageCode($from);

        return $this;
    }

    public function to(string $to): self
    {
        $this->to = self::languageCode($to);

        return $this;
    }

    public function translate(string $text): string
    {
        if ($this->from === 'auto') {
            throw new Exception('NLLB requires an explicit source language.');
        }

        try {
            $request = Http::acceptJson()
                ->connectTimeout((int) config('services.translation.connect_timeout', 2))
                ->timeout((int) config('services.translation.timeout', 15));

            if ($key = config('services.translation.api_key')) {
                $request = $request->withToken($key);
            }

            $response = $request->post(rtrim((string) config('services.translation.api_url'), '/') . '/translate', [
                'text' => $text,
                'source' => $this->from,
                'target' => $this->to,
            ]);
            $response->throw();
            $translated = $response->json('translated_text');

            if (! is_string($translated) || $translated === '') {
                throw new Exception('NLLB returned an invalid response.');
            }

            return $translated;
        } catch (Exception $e) {
            throw new Exception('Translation service is unavailable.', 0, $e);
        }
    }

    public static function languageCode(string $language): string
    {
        $language = strtolower(trim($language));
        $aliases = [
            'zh' => 'zho_Hans', 'zh-cn' => 'zho_Hans', 'zh-hans' => 'zho_Hans',
            'en' => 'eng_Latn', 'en-us' => 'eng_Latn', 'en-gb' => 'eng_Latn',
            'ru' => 'rus_Cyrl', 'ja' => 'jpn_Jpan', 'ko' => 'kor_Hang',
            'fr' => 'fra_Latn', 'de' => 'deu_Latn', 'es' => 'spa_Latn',
            'pt' => 'por_Latn', 'ar' => 'arb_Arab', 'it' => 'ita_Latn',
            'tr' => 'tur_Latn', 'pl' => 'pol_Latn', 'uk' => 'ukr_Cyrl',
            'auto' => 'auto',
        ];

        if (! isset($aliases[$language])) {
            throw new Exception('Unsupported translation language.');
        }

        return $aliases[$language];
    }
}
