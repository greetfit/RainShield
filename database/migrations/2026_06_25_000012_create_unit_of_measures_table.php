<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_of_measures', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $units = collect(['piece', 'meter', 'spool', 'kg', 'roll', 'set', 'box']);

        if (Schema::hasTable('raw_materials')) {
            $units = $units
                ->merge(DB::table('raw_materials')->whereNotNull('unit')->pluck('unit'))
                ->filter()
                ->map(fn ($unit) => strtolower(trim((string) $unit)))
                ->unique()
                ->values();
        }

        $now = now();
        DB::table('unit_of_measures')->insert($units->map(fn ($unit) => [
            'name' => $unit,
            'description' => null,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_of_measures');
    }
};
