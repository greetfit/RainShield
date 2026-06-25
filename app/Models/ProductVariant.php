<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'name',
        'product_size_id', 'size',
        'product_layer_id', 'layer',
        'product_grade_id', 'grade',
        'sku', 'selling_price', 'profit_margin_percent', 'profit_markup_type', 'profit_markup_amount', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'profit_margin_percent' => 'decimal:2',
            'profit_markup_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function productLayer(): BelongsTo
    {
        return $this->belongsTo(ProductLayer::class);
    }

    public function productGrade(): BelongsTo
    {
        return $this->belongsTo(ProductGrade::class);
    }

    public function recipeMaterials(): HasMany
    {
        return $this->hasMany(RecipeMaterial::class);
    }

    public function recipeParts(): HasMany
    {
        return $this->hasMany(RecipePart::class);
    }

    public function finishedGood(): HasOne
    {
        return $this->hasOne(FinishedGood::class);
    }
}
