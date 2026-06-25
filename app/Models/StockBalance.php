<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockBalance extends Model
{
    use HasFactory;

    protected $fillable = ['raw_material_variant_id', 'quantity', 'average_cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'average_cost' => 'decimal:4',
        ];
    }

    public function rawMaterialVariant(): BelongsTo
    {
        return $this->belongsTo(RawMaterialVariant::class);
    }
}
