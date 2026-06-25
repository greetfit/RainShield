<?php

namespace Database\Seeders;

use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class UnitOfMeasureSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['piece', 'meter', 'spool', 'kg', 'roll', 'set', 'box'] as $unit) {
            UnitOfMeasure::updateOrCreate(
                ['name' => $unit],
                ['is_active' => true],
            );
        }
    }
}
