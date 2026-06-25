<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name');                     // display label, e.g. "Medium / Double / A"
            $table->string('size', 50)->nullable();     // Small, Medium, Large...
            $table->string('layer', 20)->nullable();    // single, double, or null (non-layered)
            $table->string('grade', 20)->nullable();    // A, B, C quality grade
            $table->string('sku', 60)->nullable()->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
