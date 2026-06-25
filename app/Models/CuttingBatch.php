<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CuttingBatch extends Model
{
    protected $fillable = [
        'code',
        'raw_material_variant_id',
        'material_quantity',
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
            'material_quantity' => 'decimal:3',
            'piece_rate' => 'decimal:2',
            'wage_amount' => 'decimal:2',
            'wage_paid_amount' => 'decimal:2',
            'cut_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function rawMaterialVariant(): BelongsTo
    {
        return $this->belongsTo(RawMaterialVariant::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function outputs(): HasMany
    {
        return $this->hasMany(CuttingBatchOutput::class);
    }
}
