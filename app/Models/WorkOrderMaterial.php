<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderMaterial extends Model
{
    protected $fillable = ['work_order_id', 'raw_material_variant_id', 'quantity', 'unit_cost', 'total_cost'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
        ];
    }

    public function rawMaterialVariant(): BelongsTo
    {
        return $this->belongsTo(RawMaterialVariant::class);
    }
}
