<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piece_rates', function (Blueprint $table) {
            $table->id();
            $table->string('stage', 20);                       // cutting | stitching | packing
            // Null = the default rate for this stage; otherwise an override for one variant.
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('rate', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piece_rates');
    }
};
