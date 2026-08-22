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

namespace App\Enums\Product;

enum ProductStatus: string
{
	case DRAFT = 'draft';
	case ACTIVE = 'active';
	case INACTIVE = 'inactive';

	public function label(): string
	{
		return match ($this) {
			self::DRAFT => __('labels.status_labels.draft'),
			self::ACTIVE => __('labels.status_labels.active'),
			self::INACTIVE => __('labels.status_labels.inactive')
		};
	}

	public function emoji(): string
	{
		return match ($this) {
			self::DRAFT => '🗒️',
			self::ACTIVE => '🚀',
			self::INACTIVE => '❌',
		};
	}

	public function isActive(): bool
	{
		return $this === self::ACTIVE;
	}
}
