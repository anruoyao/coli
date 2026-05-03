<?php

namespace Database\Seeders;

use App\Models\JobListing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class JobSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        JobListing::query()->delete();

        JobListing::factory()->count(200)->create();

        Schema::enableForeignKeyConstraints();
    }
}
