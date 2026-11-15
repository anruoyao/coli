<?php

namespace Tests\Feature\Api\Throttle;

/**
 * routes/api/user/admin.php 的 api.key 中间件别名修复验证。
 *
 * 历史问题：该文件使用了未注册的别名 `api_key`（正确为 `api.key`，见 bootstrap/app.php），
 * 命中该路由会抛 Target class [api_key] does not exist（500）。
 */
class AdminApiKeyAliasTest extends ThrottleTestCase
{
    public function test_verification_endpoint_does_not_throw_middleware_alias_error(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user, 'sanctum');

        // 登录态走到 api.key 中间件：缺少 X-API-KEY 头应返回 401（非 500 的别名解析错误）
        $response = $this->postJson('/api/admin/verification/user/verify', [
            'user_id' => $user->id,
            'status'  => 'verified',
        ]);

        $this->assertSame(401, $response->status(), '修复后应命中 VerifyApiKey 返回 401，而非中间件别名未注册的 500');
    }

    public function test_regular_user_cannot_verify_an_account_with_the_shared_api_key(): void
    {
        $actor = $this->makeUser();
        $target = $this->makeUser();
        $apiKey = 'test-admin-api-key';
        config(['app.api_key' => $apiKey]);

        $this->actingAs($actor, 'sanctum')
            ->postJson('/api/admin/verification/user/verify', ['username' => $target->username], [
                'X-API-KEY' => $apiKey,
            ])
            ->assertForbidden();

        $this->assertFalse((bool) $target->fresh()->verified);
    }

    public function test_system_verification_requires_its_dedicated_key(): void
    {
        $target = $this->makeUser();
        config(['services.system_verification.api_key' => 'system-only-test-key']);

        $this->postJson('/api/system/verification/user/verify', ['username' => $target->username])
            ->assertUnauthorized();

        $this->postJson('/api/system/verification/user/verify', ['username' => $target->username], [
            'X-System-Verification-Key' => 'system-only-test-key',
        ])->assertOk();

        $this->assertTrue((bool) $target->fresh()->verified);
    }
}
