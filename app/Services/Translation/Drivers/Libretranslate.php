<?php

namespace App\Services\Translation\Drivers;

use Exception;
use Illuminate\Support\Facades\Http;
use App\Services\Translation\Drivers\Interfaces\TranslationDriverInterface;

class Libretranslate implements TranslationDriverInterface
{
    private string $from = 'auto';
    private string $to = 'en';

    public function from(string $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function to(string $to): self
    {
        $this->to = $to;

        return $this;
    }

    public function translate(string $text): string
    {
        try {
            $request = Http::connectTimeout((int) config('services.translation.connect_timeout', 2))
                ->timeout((int) config('services.translation.timeout', 15));

            $response = $request->post(rtrim((string) config('services.translation.api_url'), '/') . '/translate', [
                'q' => $text,
                'source' => $this->from,
                'target' => $this->to,
                'api_key' => config('services.translation.api_key', ''),
            ]);
            $response->throw();
            $responseData = $response->json();

            if (isset($responseData['error'])) {
                throw new Exception($responseData['error']);
            }

            if (! isset($responseData['translatedText'])) {
                throw new Exception('Translation service returned an invalid response.');
            }

            return (string) $responseData['translatedText'];
        } catch (Exception $e) {
            throw new Exception('Translation service is unavailable.', 0, $e);
        }
    }
}
