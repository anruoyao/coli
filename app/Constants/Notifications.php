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

namespace App\Constants;

class Notifications
{
	public const FOLLOWED_REQUESTED = 'user.followed-requested';
	public const FOLLOWED = 'user.followed';
	public const FOLLOW_ACCEPTED = 'user.follow-accepted';


	public const POST_COMMENTED = 'post.commented';
	public const POST_MENTIONED = 'post.mentioned';
	public const POST_REACTED = 'post.reacted';

	public const COMMENT_REACTED = 'comment.reacted';
	public const COMMENT_MENTIONED = 'comment.mentioned';

	public const STORY_MENTIONED = 'story.mentioned';

	// Important notifications
	public const ACCOUNT_LINKED = 'important.account-linked';
	public const WALLET_DEPOSIT = 'important.wallet-deposit';
	public const PAYMENT_RECEIVED = 'important.payment-received';
	
	public static function importantTypes(): array
	{
		return [
			self::ACCOUNT_LINKED,
			self::WALLET_DEPOSIT,
			self::PAYMENT_RECEIVED
		];
	}

	public static function mentionableTypes(): array
	{
		return [
			self::POST_MENTIONED,
			self::COMMENT_MENTIONED,
			self::STORY_MENTIONED
		];
	}
}
