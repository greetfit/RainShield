<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 40)->unique();
            $table->string('label', 80);
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        DB::table('payment_methods')->insert([
            ['name' => 'cash', 'label' => 'Cash', 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'bank', 'label' => 'Bank', 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'check', 'label' => 'Check', 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'card', 'label' => 'Card', 'description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
