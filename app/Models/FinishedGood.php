<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinishedGood extends Model
{
    protected $fillable = ['product_variant_id', 'quantity', 'average_cost', 'alert_quantity'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'average_cost' => 'decimal:4',
            'alert_quantity' => 'integer',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
