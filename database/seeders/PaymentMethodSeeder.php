<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'cash' => 'Cash',
            'bank' => 'Bank',
            'check' => 'Check',
            'card' => 'Card',
        ] as $name => $label) {
            PaymentMethod::updateOrCreate(
                ['name' => $name],
                ['label' => $label, 'is_active' => true],
            );
        }
    }
}
