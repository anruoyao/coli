<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Schema::disableForeignKeyConstraints();

        Product::query()->delete();

        Product::factory()->count(200)->create();

        Schema::enableForeignKeyConstraints();
    }
}
