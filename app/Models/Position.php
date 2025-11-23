<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Position extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'company_id',
        'department_id',
        'title',
        'description',
        'base_salary',
        'salary_type',
        'is_active',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Activity log options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'base_salary', 'salary_type', 'is_active'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the department that owns the position.
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the employees for the position.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * Scope to filter active positions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
