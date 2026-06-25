<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->string('item_type', 20)->default('raw_material')->after('purchase_id');
            $table->foreignId('product_variant_id')->nullable()->after('raw_material_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('raw_material_variant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('item_type');
            $table->foreignId('raw_material_variant_id')->nullable(false)->change();
        });
    }
};
