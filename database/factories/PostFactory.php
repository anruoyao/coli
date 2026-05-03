<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Post;
use App\Enums\Post\PostType;
use App\Enums\Post\PostStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'user_id' => function () {
                return User::active()->inRandomOrder()->first()->id;
            },
            'content' => fake()->paragraphs(random_int(1, 5), true),
            'status' => PostStatus::ACTIVE->value,
            'type' => PostType::TEXT->value,
            'is_sensitive' => fake()->boolean(5),
            'views_count' => fake()->numberBetween(0, 5000),
            'bookmarks_count' => fake()->numberBetween(0, 500),
            'comments_count' => fake()->numberBetween(0, 200),
        ];
    }
}
