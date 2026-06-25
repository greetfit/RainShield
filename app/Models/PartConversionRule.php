<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartConversionRule extends Model
{
    protected $fillable = [
        'from_product_variant_id',
        'from_part_id',
        'to_product_variant_id',
        'to_part_id',
        'output_per_input',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'output_per_input' => 'decimal:3',
            'is_active' => 'boolean',
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
}
