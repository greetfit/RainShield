<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterial extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'unit', 'alert_quantity', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'alert_quantity' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function variants(): HasMany
    {
        return $this->hasMany(RawMaterialVariant::class);
    }
}
