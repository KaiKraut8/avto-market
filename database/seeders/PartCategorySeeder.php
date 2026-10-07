<?php

namespace Database\Seeders;

use App\Models\PartCategory;
use Illuminate\Database\Seeder;

class PartCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Volan', 'Bremza', 'Pedali', 'Sedezi', 'Key'] as $name) {
            PartCategory::firstOrCreate(['name' => $name]);
        }
    }
}
