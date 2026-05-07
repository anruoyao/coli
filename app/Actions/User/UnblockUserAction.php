<?php

namespace App\Actions\User;

use App\Models\User;
use App\Models\UserBlock;

class UnblockUserAction
{
    private User $user;
    private User $targetUser;

    public function __construct(User $user, User $targetUser)
    {
        $this->user = $user;
        $this->targetUser = $targetUser;
    }

    public function execute(): bool
    {
        return UserBlock::where([
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
        ])->delete() > 0;
    }
}
