<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Designation extends Model
{
    protected $fillable = ['name', 'description', 'priority_level', 'is_active'];

    protected function casts(): array
    {
        return [
            'priority_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }
}
