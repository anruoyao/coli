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

namespace App\Actions\Chat;

use App\Models\Message;

class MessageGlobalDeleteAction
{
	private Message $messageData;

	public function __construct(Message $messageData) {
		$this->messageData = $messageData;
	}

	public function execute()
	{
		$this->messageData->reactions()->delete();

		$this->messageData->linkSnapshot()->delete();

		$this->messageData->update([
			'content' => '',
			'is_deleted' => true
		]);
	}
}
