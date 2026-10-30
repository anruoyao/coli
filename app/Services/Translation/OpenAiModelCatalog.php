<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class OpenAiModelCatalog
{
    /**
     * @return list<string>
     */
    public function list(string $apiUrl, string $apiKey, int $timeout, int $connectTimeout): array
    {
        $request = Http::acceptJson()
            ->timeout($timeout)
            ->connectTimeout($connectTimeout);

        if ($apiKey !== '') {
            $request = $request->withToken($apiKey);
        }

        $response = $request->get($this->modelsEndpoint($apiUrl))->throw();
        $data = $response->json('data');

        if (! is_array($data)) {
            throw new RuntimeException('Model provider returned an invalid model list.');
        }

        $models = [];
        foreach ($data as $model) {
            $id = is_array($model) ? ($model['id'] ?? null) : null;
            if (is_string($id) && trim($id) !== '') {
                $models[] = trim($id);
            }
        }

        return array_values(array_unique($models));
    }

    private function modelsEndpoint(string $apiUrl): string
    {
        $parts = parse_url(trim($apiUrl));
        if (! is_array($parts) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host'])) {
            throw new RuntimeException('Model provider URL is invalid.');
        }

        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        $path = (string) preg_replace('#/(?:chat/completions|models)$#i', '', $path);
        if (! preg_match('#/v1$#i', $path)) {
            $path .= '/v1';
        }

        $endpoint = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $endpoint .= ':' . $parts['port'];
        }

        return $endpoint . $path . '/models' . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
