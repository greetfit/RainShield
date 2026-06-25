<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PieceRate extends Model
{
    protected $fillable = ['stage', 'staff_id', 'product_variant_id', 'rate'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:2'];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Resolve the rate for a stage, preferring a variant-specific override
     * over the stage default (null variant).
     */
    public static function resolve(string $stage, int $productVariantId, ?int $staffId = null): float
    {
        $query = static::where('stage', $stage);

        if ($staffId) {
            $staffVariant = (clone $query)
                ->where('staff_id', $staffId)
                ->where('product_variant_id', $productVariantId)
                ->value('rate');

            if ($staffVariant !== null) {
                return (float) $staffVariant;
            }

            $staffDefault = (clone $query)
                ->where('staff_id', $staffId)
                ->whereNull('product_variant_id')
                ->value('rate');

            if ($staffDefault !== null) {
                return (float) $staffDefault;
            }
        }

        $override = (clone $query)
            ->whereNull('staff_id')
            ->where('product_variant_id', $productVariantId)
            ->value('rate');

        if ($override !== null) {
            return (float) $override;
        }

        return (float) ((clone $query)
            ->whereNull('staff_id')
            ->whereNull('product_variant_id')
            ->value('rate') ?? 0);
    }

    public static function estimateForCosting(string $stage, int $productVariantId): float
    {
        $globalRate = static::resolve($stage, $productVariantId);

        if ($globalRate > 0) {
            return $globalRate;
        }

        $staffVariantRate = static::where('stage', $stage)
            ->whereNotNull('staff_id')
            ->where('product_variant_id', $productVariantId)
            ->max('rate');

        if ($staffVariantRate !== null) {
            return (float) $staffVariantRate;
        }

        return (float) (static::where('stage', $stage)
            ->whereNotNull('staff_id')
            ->whereNull('product_variant_id')
            ->max('rate') ?? 0);
    }
}
