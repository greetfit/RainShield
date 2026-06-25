<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'label', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function activeOptions()
    {
        return static::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['name', 'label'])
            ->map(fn (self $method) => [
                'value' => $method->name,
                'label' => $method->label,
            ])
            ->values();
    }
}
