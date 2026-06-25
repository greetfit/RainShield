<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    use HasFactory;

    protected $table = 'staff';

    protected $fillable = ['user_id', 'name', 'phone', 'designation_id', 'designation', 'salary_type', 'monthly_salary', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function designationRecord(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }
}
