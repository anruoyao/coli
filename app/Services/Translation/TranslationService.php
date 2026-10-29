<?php

namespace App\Services\Translation;

use Exception;
use App\Services\Translation\Drivers\Nllb;
use App\Services\Translation\Drivers\Libretranslate;
use App\Services\Translation\Drivers\OpenAi;
use App\Services\Translation\Drivers\Interfaces\TranslationDriverInterface;

class TranslationService
{
    private TranslationDriverInterface $driver;

    public function __construct()
    {
        if (! config('services.translation.enabled', true)) {
            throw new Exception('Translation is disabled.');
        }

        $this->driver = match (config('services.translation.service', 'nllb')) {
            'nllb' => new Nllb(),
            'libretranslate' => new Libretranslate(),
            'openai' => new OpenAi(),
            default => throw new Exception('Invalid translation service'),
        };
    }

    public function setDriver(TranslationDriverInterface $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    public function from(string $from): self
    {
        $this->driver->from($from);

        return $this;
    }

    public function to(string $to): self
    {
        $this->driver->to($to);

        return $this;
    }

    public function translate(string $text): string
    {
        return $this->driver->translate($text);
    }

    public function testConnection(): void
    {
        if (method_exists($this->driver, 'testConnection')) {
            $this->driver->testConnection();

            return;
        }

        $this->driver->from(config('services.translation.default_source', 'en'))
            ->to(config('services.translation.default_target', 'en'))
            ->translate('OK');
    }
}
