<?php

namespace App\Console\Commands\Admin;

use App\Models\User;
use App\Enums\User\UserRole;
use Illuminate\Console\Command;
use App\Console\Traits\ValidatesInput;

class AddAdmin extends Command
{
    use ValidatesInput;
    
    protected $signature = 'admin:add';

    protected $description = 'This command assigns the admin role to the provided user.';

    public function handle()
    {
        $adminUsername = $this->askValid('Enter the username:', [
            'required',
            'string',
            'exists:users,username'
        ]);

        $adminData = User::where('username', $adminUsername)->first();

        if(! $adminData) {
            $this->error('User not found.');

            return false;
        }

        $adminData->update([
            'role' => UserRole::ADMIN
        ]);

        $this->info('Admin role assigned successfully.');

        return true;
    }
}
