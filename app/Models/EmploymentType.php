<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmploymentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'has_benefits',
        'description',
        'is_active'
    ];

    protected $casts = [
        'has_benefits' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function employees()
    {
        return $this->hasMany(Employee::class, 'employment_type_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
