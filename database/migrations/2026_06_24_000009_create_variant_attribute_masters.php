<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['product_sizes', 'product_layers', 'product_grades'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        Schema::table('product_variants', function (Blueprint $table) {
            $table->foreignId('product_size_id')->nullable()->after('name')->constrained('product_sizes')->nullOnDelete();
            $table->foreignId('product_layer_id')->nullable()->after('size')->constrained('product_layers')->nullOnDelete();
            $table->foreignId('product_grade_id')->nullable()->after('layer')->constrained('product_grades')->nullOnDelete();
        });

        $this->backfill('size', 'product_sizes', 'product_size_id');
        $this->backfill('layer', 'product_layers', 'product_layer_id');
        $this->backfill('grade', 'product_grades', 'product_grade_id');
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_grade_id');
            $table->dropConstrainedForeignId('product_layer_id');
            $table->dropConstrainedForeignId('product_size_id');
        });

        Schema::dropIfExists('product_grades');
        Schema::dropIfExists('product_layers');
        Schema::dropIfExists('product_sizes');
    }

    private function backfill(string $sourceColumn, string $masterTable, string $targetColumn): void
    {
        DB::table('product_variants')
            ->whereNotNull($sourceColumn)
            ->where($sourceColumn, '<>', '')
            ->select($sourceColumn)
            ->distinct()
            ->orderBy($sourceColumn)
            ->get()
            ->each(function ($variantValue) use ($sourceColumn, $masterTable, $targetColumn): void {
                $id = DB::table($masterTable)->insertGetId([
                    'name' => $variantValue->{$sourceColumn},
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('product_variants')
                    ->where($sourceColumn, $variantValue->{$sourceColumn})
                    ->update([$targetColumn => $id]);
            });
    }
};
