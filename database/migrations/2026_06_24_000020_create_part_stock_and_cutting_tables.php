<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('part_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'part_id'], 'part_stock_balance_unique');
        });

        Schema::create('recoverable_part_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->timestamps();

            $table->unique(['product_variant_id', 'part_id'], 'recoverable_part_balance_unique');
        });

        Schema::create('part_stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->string('stock_type', 20); // good, recoverable, scrap
            $table->string('direction', 20); // in, out, adjustment, scrap
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('balance_quantity')->default(0);
            $table->nullableMorphs('reference');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cutting_batches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('raw_material_variant_id')->constrained()->cascadeOnDelete();
            $table->decimal('material_quantity', 12, 3);
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('cut_on')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('cutting_batch_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cutting_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('good_quantity')->default(0);
            $table->unsignedInteger('recoverable_quantity')->default(0);
            $table->unsignedInteger('scrap_quantity')->default(0);
            $table->timestamps();
        });

        Schema::create('part_conversion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('from_part_id')->constrained('parts')->cascadeOnDelete();
            $table->foreignId('to_product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('to_part_id')->constrained('parts')->cascadeOnDelete();
            $table->decimal('output_per_input', 10, 3)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['from_product_variant_id', 'from_part_id', 'to_product_variant_id', 'to_part_id'], 'part_conversion_rule_unique');
        });

        Schema::create('recovery_cuttings', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable()->unique();
            $table->foreignId('from_product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('from_part_id')->constrained('parts')->cascadeOnDelete();
            $table->unsignedInteger('input_quantity');
            $table->foreignId('to_product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('to_part_id')->constrained('parts')->cascadeOnDelete();
            $table->unsignedInteger('expected_quantity')->default(0);
            $table->unsignedInteger('good_quantity')->default(0);
            $table->unsignedInteger('scrap_quantity')->default(0);
            $table->foreignId('staff_id')->nullable()->constrained('staff')->nullOnDelete();
            $table->date('cut_on')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_cuttings');
        Schema::dropIfExists('part_conversion_rules');
        Schema::dropIfExists('cutting_batch_outputs');
        Schema::dropIfExists('cutting_batches');
        Schema::dropIfExists('part_stock_movements');
        Schema::dropIfExists('recoverable_part_balances');
        Schema::dropIfExists('part_stock_balances');
    }
};
