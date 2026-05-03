<?php

namespace App\Actions\Chat;

use App\Models\Message;

class DeleteMessageAction
{
	private Message $messageData;

	public function __construct(Message $messageData) {
		$this->messageData = $messageData;
	}

	public function execute() {
		$this->messageData->linkSnapshot()->delete();

		$this->messageData->reactions()->delete();

		$this->messageData->delete();
	}
}
