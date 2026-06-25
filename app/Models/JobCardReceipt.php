<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobCardReceipt extends Model
{
    protected $fillable = [
        'job_card_id', 'received_on', 'quantity_received', 'quantity_damaged',
        'started_at', 'received_at', 'duration_minutes', 'wage_amount',
        'wage_paid_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'started_at' => 'datetime',
            'received_at' => 'datetime',
            'wage_amount' => 'decimal:2',
            'wage_paid_amount' => 'decimal:2',
        ];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(JobCardPayment::class);
    }
}
