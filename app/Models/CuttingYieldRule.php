<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuttingYieldRule extends Model
{
    protected $fillable = [
        'raw_material_variant_id',
        'product_variant_id',
        'part_id',
        'yield_per_material_unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'yield_per_material_unit' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function rawMaterialVariant(): BelongsTo
    {
        return $this->belongsTo(RawMaterialVariant::class);
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
