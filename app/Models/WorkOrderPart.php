<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderPart extends Model
{
    protected $fillable = ['work_order_id', 'part_id', 'quantity', 'unit_cost', 'total_cost', 'quantity_cut', 'quantity_damaged'];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cost' => 'decimal:4',
            'total_cost' => 'decimal:2',
            'quantity_cut' => 'integer',
            'quantity_damaged' => 'integer',
        ];
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }
}
