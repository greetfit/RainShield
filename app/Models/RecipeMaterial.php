<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeMaterial extends Model
{
    use HasFactory;

    protected $fillable = ['product_variant_id', 'raw_material_variant_id', 'quantity', 'unit'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3'];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function rawMaterialVariant(): BelongsTo
    {
        return $this->belongsTo(RawMaterialVariant::class);
    }
}
