<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');               // garments to produce
            $table->date('target_delivery_date')->nullable();
            $table->string('status', 20)->default('draft');    // draft|in_production|completed|cancelled
            $table->timestamp('released_at')->nullable();      // cutting started
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('completed_quantity')->nullable();
            $table->decimal('material_cost', 14, 2)->default(0); // snapshot at release
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
