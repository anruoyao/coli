<?php

namespace Tests\Feature\Marketing\Concerns;

use App\Models\User;
use App\Enums\User\UserType;
use App\Enums\NotificationType;
use App\Models\UserNotificationSettings;

/**
 * 营销功能测试的用户构造工具。
 *
 * 约定：测试中一律使用 example.com 测试邮箱（不触碰任何真实用户邮箱）；
 * 需要真实 SMTP 收件验证时由人工以 2195299055@qq.com 执行（见 admin 邮件测试）。
 */
trait CreatesUsers
{
    protected function makeUser(array $overrides = []): User
    {
        $user = User::create(array_merge([
            'first_name' => 'Test',
            'last_name' => 'User',
            'username' => 'tester_'.uniqid(),
            'email' => 'tester_'.uniqid().'@example.com',
            'password' => bcrypt('secret-password'),
            'language' => 'zh',
            'type' => UserType::READER->value,
            // users.tips 为非空 JSON 列（严格模式 MySQL 无默认值），必须显式提供
            'tips' => [],
        ], $overrides));

        return $user;
    }

    /**
     * @param array<string,bool> $emailOverrides $pushOverrides
     */
    protected function makeUserWithSettings(
        bool $emailPlatform = true,
        bool $pushPlatform = true,
        array $emailOverrides = [],
        array $pushOverrides = [],
    ): User {
        $user = $this->makeUser();

        $this->attachNotificationSettings($user, NotificationType::EMAIL, array_merge([
            'platform_notifications' => $emailPlatform,
        ], $emailOverrides));

        $this->attachNotificationSettings($user, NotificationType::PUSH, array_merge([
            'platform_notifications' => $pushPlatform,
        ], $pushOverrides));

        return $user;
    }

    protected function attachNotificationSettings(User $user, NotificationType $type, array $overrides = []): UserNotificationSettings
    {
        $defaults = [
            'user_id' => $user->id,
            'type' => $type,
            'direct_messages' => true,
            'reactions' => true,
            'comments' => true,
            'shared_posts' => true,
            'followers' => true,
            'follow_request' => true,
            'mentions' => true,
            'platform_notifications' => true,
        ];

        return UserNotificationSettings::create(array_merge($defaults, $overrides));
    }
}