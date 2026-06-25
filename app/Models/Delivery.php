<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use HasFactory;

    protected $table = 'deliveries';

    protected $fillable = [
        'code', 'customer_name', 'product_variant_id', 'quantity',
        'dispatched_on', 'delivered_on', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'dispatched_on' => 'date',
            'delivered_on' => 'date',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    // Days from dispatch to delivery (null until delivered).
    public function getLeadTimeAttribute(): ?int
    {
        return $this->delivered_on
            ? $this->dispatched_on->diffInDays($this->delivered_on)
            : null;
    }
}
