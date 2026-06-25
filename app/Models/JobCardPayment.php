<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobCardPayment extends Model
{
    protected $fillable = [
        'job_card_id', 'job_card_receipt_id', 'paid_on', 'amount',
        'method', 'reference', 'source', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function jobCard(): BelongsTo
    {
        return $this->belongsTo(JobCard::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(JobCardReceipt::class, 'job_card_receipt_id');
    }
}
