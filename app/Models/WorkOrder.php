<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'product_variant_id', 'quantity', 'target_delivery_date', 'status',
        'released_at', 'completed_at', 'completed_quantity', 'rejected_quantity',
        'material_cost', 'completion_notes', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'target_delivery_date' => 'date',
            'released_at' => 'datetime',
            'completed_at' => 'datetime',
            'material_cost' => 'decimal:2',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkOrderMaterial::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(WorkOrderPart::class);
    }

    public function jobCards(): HasMany
    {
        return $this->hasMany(JobCard::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
