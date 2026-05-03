<?php

namespace Database\Seeders;

use App\Enums\User\UserType;
use App\Enums\User\UserRole;
use App\Enums\User\UserStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('users')->where('id', '>', 1)->delete();

        $total = 50000;
        $chunk = 2000;
        $now = now()->toDateTimeString();
        $hashedPassword = Hash::make('password');

        for ($i = 0; $i < $total; $i += $chunk) {
            $users = [];

            for ($j = 0; $j < $chunk; $j++) {
                $index = $i + $j;
                $users[] = [
                    'username' => 'u' . $index . '_' . substr(uniqid(), -5),
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'email' => 'u' . $index . '@test.local',
                    'password' => $hashedPassword,
                    'remember_token' => null,
                    'tips' => json_encode([]),
                    'status' => UserStatus::ACTIVE->value,
                    'role' => UserRole::USER->value,
                    'type' => UserType::READER->value,
                    'last_active' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('users')->insert($users);

            if (($i + $chunk) % 10000 === 0) {
                $this->command->info('Inserted ' . ($i + $chunk) . ' / ' . $total . ' users');
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
