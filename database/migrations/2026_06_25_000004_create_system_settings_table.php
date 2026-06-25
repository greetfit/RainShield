<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('system_settings')->insert([
            ['key' => 'currency_code', 'value' => 'LKR', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'currency_symbol', 'value' => 'Rs', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'timezone', 'value' => 'Asia/Colombo', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'date_format', 'value' => 'd/m/Y', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'time_format', 'value' => 'h:i A', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
