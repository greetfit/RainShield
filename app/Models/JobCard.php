<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCard extends Model
{
    protected $fillable = [
        'work_order_id', 'stage', 'staff_id', 'quantity_issued', 'quantity_received',
        'quantity_damaged', 'piece_rate', 'wage_amount', 'wage_paid_amount', 'status', 'issued_on',
        'started_at', 'completed_on', 'completed_at', 'duration_minutes', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'piece_rate' => 'decimal:2',
            'wage_amount' => 'decimal:2',
            'wage_paid_amount' => 'decimal:2',
            'issued_on' => 'date',
            'completed_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(JobCardReceipt::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(JobCardPayment::class);
    }

    public function partMovements(): HasMany
    {
        return $this->hasMany(JobCardPartMovement::class);
    }

    // Shortfall = pieces issued but not returned (null until completed).
    public function getShortfallAttribute(): ?int
    {
        return $this->quantity_received === null
            ? null
            : $this->pending_quantity;
    }

    public function getPendingQuantityAttribute(): int
    {
        return max(0, (int) $this->quantity_issued - (int) ($this->quantity_received ?? 0) - (int) ($this->quantity_damaged ?? 0));
    }

    public function getWageBalanceAttribute(): float
    {
        return round((float) ($this->wage_amount ?? 0) - (float) ($this->wage_paid_amount ?? 0), 2);
    }

    public function getWageStatusAttribute(): string
    {
        $balance = $this->wage_balance;

        if ($balance > 0.005) {
            return 'pending';
        }

        if ($balance < -0.005) {
            return 'overpaid';
        }

        return 'paid';
    }
}
