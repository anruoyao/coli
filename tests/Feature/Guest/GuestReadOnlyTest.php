<?php

namespace Tests\Feature\Guest;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * AC-5：访客 API 强制只读（写方法 405）。
 */
class GuestReadOnlyTest extends GuestTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->enableGuestMode();
    }

    #[DataProvider('writeMethodsProvider')]
    public function test_write_methods_are_not_allowed(string $method, string $uri): void
    {
        $response = match ($method) {
            'post' => $this->postJson($uri),
            'put' => $this->putJson($uri),
            'patch' => $this->patchJson($uri),
            'delete' => $this->deleteJson($uri),
        };

        $this->assertSame(405, $response->status(), "{$method} {$uri} should be 405");
    }

    public static function writeMethodsProvider(): array
    {
        return [
            'post feed' => ['post', '/api/guest/v1/feed'],
            'put feed' => ['put', '/api/guest/v1/feed'],
            'patch feed' => ['patch', '/api/guest/v1/feed'],
            'delete feed' => ['delete', '/api/guest/v1/feed'],
            'post post' => ['post', '/api/guest/v1/post/1'],
            'delete post' => ['delete', '/api/guest/v1/post/1'],
            'post profile' => ['post', '/api/guest/v1/profile/someone'],
        ];
    }
}
