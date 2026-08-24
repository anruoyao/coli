<?php
/*
|--------------------------------------------------------------------------
| ColibriPlus - The Social Network Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  ColibriPlus. All rights reserved.
|--------------------------------------------------------------------------
*/

namespace App\Enums\User;

use App\Models\User;
use App\Services\Relations\FollowService;

enum PrivacyPermit: string
{
	case ALL = 'all';
	case FOLLOWERS = 'followers';
	case NOBODY = 'nobody';
	case APPROVED = 'approved';

	public function nobody(): bool
	{
		return $this === self::NOBODY;
	}

	/**
	 * 判断 $actor 是否被允许对 $target 执行本设置项约束的操作。
	 * all: 任何人；nobody: 无人；followers/approved: 仅 $target 的（受批准后的）粉丝。
	 */
	public function allows(User $actor, User $target): bool
	{
		return match($this) {
			self::ALL => true,
			self::NOBODY => false,
			self::FOLLOWERS, self::APPROVED => (new FollowService($actor, $target))->isFollowing(),
		};
	}

	public static function followPermits(): array
	{
		return [
			self::ALL,
			self::APPROVED
		];
	}

	public function onlyApproved()
	{
		return $this === self::APPROVED;
	}
}