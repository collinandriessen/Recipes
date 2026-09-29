<?php

namespace Database\Seeders;

use App\Models\Allergen;
use Illuminate\Database\Seeder;

class AllergenSeeder extends Seeder
{
    public function run(): void
    {
        $top9 = [
            'Milk', 'Eggs', 'Fish', 'Shellfish', 'Tree Nuts',
            'Peanuts', 'Wheat', 'Soybeans', 'Sesame',
        ];

        foreach ($top9 as $name) {
            Allergen::updateOrCreate(
                ['name' => $name],
                ['is_top9' => true]
            );
        }
    }
}
