<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryCutting extends Model
{
    protected $fillable = [
        'code',
        'from_product_variant_id',
        'from_part_id',
        'input_quantity',
        'to_product_variant_id',
        'to_part_id',
        'expected_quantity',
        'good_quantity',
        'scrap_quantity',
        'piece_rate',
        'wage_amount',
        'wage_paid_amount',
        'staff_id',
        'cut_on',
        'started_at',
        'completed_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'input_quantity' => 'integer',
            'expected_quantity' => 'integer',
            'good_quantity' => 'integer',
            'scrap_quantity' => 'integer',
            'piece_rate' => 'decimal:2',
            'wage_amount' => 'decimal:2',
            'wage_paid_amount' => 'decimal:2',
            'cut_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function fromProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'from_product_variant_id');
    }

    public function fromPart(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'from_part_id');
    }

    public function toProductVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'to_product_variant_id');
    }

    public function toPart(): BelongsTo
    {
        return $this->belongsTo(Part::class, 'to_part_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
