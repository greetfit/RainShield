<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_order_parts', function (Blueprint $table) {
            $table->unsignedInteger('quantity_cut')->default(0)->after('quantity');
            $table->unsignedInteger('quantity_damaged')->default(0)->after('quantity_cut');
        });
    }

    public function down(): void
    {
        Schema::table('work_order_parts', function (Blueprint $table) {
            $table->dropColumn(['quantity_cut', 'quantity_damaged']);
        });
    }
};
