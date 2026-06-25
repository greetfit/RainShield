<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinishedGoodMovement extends Model
{
    protected $fillable = [
        'product_variant_id', 'direction', 'quantity', 'unit_cost',
        'reference_type', 'reference_id', 'note',
        'balance_quantity', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
            'balance_quantity' => 'integer',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
