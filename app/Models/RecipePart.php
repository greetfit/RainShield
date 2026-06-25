<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipePart extends Model
{
    use HasFactory;

    protected $fillable = ['product_variant_id', 'part_id', 'quantity_per_garment'];

    protected function casts(): array
    {
        return ['quantity_per_garment' => 'integer'];
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
