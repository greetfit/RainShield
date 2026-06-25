<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Rent',
            'Electricity',
            'Water',
            'Transport',
            'Repairs & Maintenance',
            'Meals & Tea',
            'Office Supplies',
            'Other',
        ] as $name) {
            ExpenseCategory::updateOrCreate(
                ['name' => $name],
                ['is_active' => true],
            );
        }
    }
}
