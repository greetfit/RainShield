<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PartStockMovement extends Model
{
    protected $fillable = [
        'product_variant_id',
        'part_id',
        'stock_type',
        'direction',
        'quantity',
        'unit_cost',
        'balance_quantity',
        'balance_average_cost',
        'reference_type',
        'reference_id',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
            'balance_quantity' => 'integer',
            'balance_average_cost' => 'decimal:4',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
