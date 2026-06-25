<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raw_material_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 3);      // amount per ONE finished garment
            $table->string('unit', 30);              // snapshot of the material's unit
            $table->timestamps();

            $table->unique(['product_variant_id', 'raw_material_variant_id'], 'recipe_material_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_materials');
    }
};
