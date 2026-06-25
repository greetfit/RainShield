<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuttingBatchOutput extends Model
{
    protected $fillable = [
        'cutting_batch_id',
        'product_variant_id',
        'part_id',
        'yield_per_material_unit',
        'expected_quantity',
        'good_quantity',
        'recoverable_quantity',
        'scrap_quantity',
    ];

    protected function casts(): array
    {
        return [
            'expected_quantity' => 'integer',
            'yield_per_material_unit' => 'decimal:3',
            'good_quantity' => 'integer',
            'recoverable_quantity' => 'integer',
            'scrap_quantity' => 'integer',
        ];
    }

    public function cuttingBatch(): BelongsTo
    {
        return $this->belongsTo(CuttingBatch::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
