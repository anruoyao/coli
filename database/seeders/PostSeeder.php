<?php

namespace Database\Seeders;

use App\Enums\Post\PostStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PostSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        DB::table('posts')->delete();

        $total = 1000000;
        $chunk = 2000;
        $now = now()->toDateTimeString();

        $userIds = DB::table('users')->where('id', '>', 1)->pluck('id')->toArray();
        $userCount = count($userIds);

        if ($userCount === 0) {
            $this->command->error('No users found. Please run UserSeeder first.');

            return;
        }

        $faker = \Faker\Factory::create();

        for ($i = 0; $i < $total; $i += $chunk) {
            $posts = [];

            for ($j = 0; $j < $chunk; $j++) {
                $posts[] = [
                    'user_id' => $userIds[array_rand($userIds)],
                    'content' => $faker->paragraphs(random_int(1, 5), true),
                    'status' => PostStatus::ACTIVE->value,
                    'type' => 'text',
                    'is_sensitive' => random_int(1, 100) <= 5 ? 1 : 0,
                    'views_count' => random_int(0, 5000),
                    'bookmarks_count' => random_int(0, 500),
                    'comments_count' => random_int(0, 200),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('posts')->insert($posts);

            if (($i + $chunk) % 100000 === 0) {
                $this->command->info('Inserted ' . ($i + $chunk) . ' / ' . $total . ' posts');
            }
        }

        Schema::enableForeignKeyConstraints();
    }
}
