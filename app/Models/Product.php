<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const SOURCE_IN_HOUSE = 'in_house';
    public const SOURCE_OUTSOURCED = 'outsourced';
    public const SOURCE_BOTH = 'both';

    protected $fillable = ['name', 'product_category_id', 'source_type', 'category', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    public static function sourceOptions(): array
    {
        return [
            ['value' => self::SOURCE_IN_HOUSE, 'label' => 'Own production'],
            ['value' => self::SOURCE_OUTSOURCED, 'label' => 'Outsourced / resale'],
            ['value' => self::SOURCE_BOTH, 'label' => 'Own + outsourced'],
        ];
    }

    public function sourceLabel(): string
    {
        return collect(self::sourceOptions())->firstWhere('value', $this->source_type)['label'] ?? 'Own production';
    }
}
