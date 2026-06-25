<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutting_yield_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('raw_material_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->decimal('yield_per_material_unit', 12, 3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['raw_material_variant_id', 'product_variant_id', 'part_id'], 'cutting_yield_rule_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutting_yield_rules');
    }
};
