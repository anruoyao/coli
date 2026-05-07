<?php

namespace App\Actions\User;

use App\Models\User;
use App\Models\UserBlock;

class BlockUserAction
{
    private User $user;
    private User $targetUser;

    public function __construct(User $user, User $targetUser)
    {
        $this->user = $user;
        $this->targetUser = $targetUser;
    }

    public function execute(): UserBlock
    {
        return UserBlock::create([
            'user_id' => $this->user->id,
            'blocked_user_id' => $this->targetUser->id,
            'blocked_at' => now(),
        ]);
    }
}
