<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finished_goods', function (Blueprint $table) {
            $table->decimal('average_cost', 14, 4)->default(0)->after('quantity');
        });

        Schema::table('finished_good_movements', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 4)->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('finished_good_movements', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });

        Schema::table('finished_goods', function (Blueprint $table) {
            $table->dropColumn('average_cost');
        });
    }
};
