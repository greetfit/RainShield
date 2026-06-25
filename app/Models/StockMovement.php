<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'raw_material_variant_id', 'direction', 'quantity', 'unit_cost',
        'reference_type', 'reference_id', 'note',
        'balance_quantity', 'balance_average_cost', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'balance_quantity' => 'decimal:3',
            'balance_average_cost' => 'decimal:4',
        ];
    }

    public function rawMaterialVariant(): BelongsTo
    {
        return $this->belongsTo(RawMaterialVariant::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }
}
