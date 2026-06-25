<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCardPartMovement extends Model
{
    public const ISSUE = 'issue';
    public const RETURN_GOOD = 'return_good';
    public const RETURN_RECOVERABLE = 'return_recoverable';
    public const SCRAP = 'scrap';

    protected $fillable = [
        'job_card_id',
        'work_order_id',
        'product_variant_id',
        'part_id',
        'type',
        'quantity',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(Part::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
