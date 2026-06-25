<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionStage extends Model
{
    protected $fillable = ['name', 'slug', 'priority_level', 'is_active', 'description'];

    protected function casts(): array
    {
        return [
            'priority_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function options(bool $activeOnly = true)
    {
        return static::query()
            ->when($activeOnly, fn ($query) => $query->active())
            ->orderBy('priority_level')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'priority_level', 'is_active'])
            ->map(fn ($stage) => [
                'id' => $stage->id,
                'label' => $stage->name,
                'value' => $stage->slug,
                'priority' => $stage->priority_level,
                'is_active' => $stage->is_active,
            ])
            ->values();
    }
}
