<?php

namespace Tests\Unit\Translation;

use App\Services\Translation\Drivers\Nllb;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NllbDriverTest extends TestCase
{
    public function test_it_sends_nllb_payload_and_bearer_token(): void
    {
        config([
            'services.translation.api_url' => 'http://127.0.0.1:8301',
            'services.translation.api_key' => 'test-key',
            'services.translation.timeout' => 15,
            'services.translation.connect_timeout' => 2,
        ]);

        Http::fake(function ($request) {
            $this->assertSame('Bearer test-key', $request->header('Authorization')[0]);
            $this->assertSame([
                'text' => '你好',
                'source' => 'zho_Hans',
                'target' => 'eng_Latn',
            ], $request->data());

            return Http::response(['translated_text' => 'Hello'], 200);
        });

        $result = (new Nllb())->from('zh')->to('en')->translate('你好');

        $this->assertSame('Hello', $result);
    }

    public function test_short_chinese_and_supported_detection_never_produce_unmapped_codes(): void
    {
        $this->assertSame('zh', Nllb::detectSource('测试一下'));
        $this->assertSame('zh', Nllb::detectSource('哈哈'));
        $this->assertSame('ja', Nllb::detectSource('こんにちは'));
        $this->assertSame('ko', Nllb::detectSource('안녕하세요'));
        foreach (['test', 'Hello world', 'A short sentence.', '测试一下'] as $text) {
            $this->assertNotSame('auto', Nllb::languageCode(Nllb::detectSource($text)));
        }
        $this->assertSame('gle_Latn', Nllb::languageCode('ga'));
        $this->assertSame('glg_Latn', Nllb::languageCode('gl'));
    }

    public function test_it_rejects_unknown_language_codes(): void
    {
        $this->expectExceptionMessage('Unsupported translation language.');

        (new Nllb())->from('xx');
    }

    public function test_it_rejects_auto_source_after_driver_resolution(): void
    {
        $this->expectExceptionMessage('NLLB requires an explicit source language.');

        (new Nllb())->translate('hello');
    }
}
