<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\JobSeeder;
use Database\Seeders\PostSeeder;
use Database\Seeders\UserSeeder;
use Database\Seeders\LocaleSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CurrencySeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        (new CategorySeeder())->run();
        (new CurrencySeeder())->run();
        (new LocaleSeeder())->run();
        (new UserSeeder())->run();
        (new PostSeeder())->run();
        (new ProductSeeder())->run();
        (new JobSeeder())->run();
    }
}
