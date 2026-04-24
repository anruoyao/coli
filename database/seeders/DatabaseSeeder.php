<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\LocaleSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\CurrencySeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        (new CategorySeeder())->run();
        (new CurrencySeeder())->run();
        (new LocaleSeeder())->run();
    }
}
