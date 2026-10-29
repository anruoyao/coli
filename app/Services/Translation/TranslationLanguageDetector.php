<?php

namespace App\Services\Translation;

use App\Services\Translation\Drivers\Nllb;

class TranslationLanguageDetector
{
    public function detect(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return Nllb::detectSource($text);
    }
}
