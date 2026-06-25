<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    public const STATUS_FINAL = 'final';
    public const STATUS_VOID = 'void';

    protected $fillable = [
        'invoice_no', 'customer_id', 'pos_session_id', 'sold_at', 'status', 'payment_status',
        'payment_method', 'subtotal', 'discount', 'tax', 'shipping', 'total',
        'paid', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'tax' => 'decimal:2',
            'shipping' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function posSession(): BelongsTo
    {
        return $this->belongsTo(PosSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function getDueAttribute(): float
    {
        $returned = $this->relationLoaded('returns')
            ? (float) $this->returns->sum('total_amount')
            : (float) $this->returns()->sum('total_amount');

        return round(max(0, (float) $this->total - $returned - (float) $this->paid), 2);
    }
}
