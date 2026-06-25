<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('profit_markup_type', 20)->default('percent')->after('profit_margin_percent');
            $table->decimal('profit_markup_amount', 12, 2)->default(0)->after('profit_markup_type');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['profit_markup_type', 'profit_markup_amount']);
        });
    }
};
