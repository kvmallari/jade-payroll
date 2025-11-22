<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NightDifferentialSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'start_time',
        'end_time',
        'rate_multiplier',
        'description',
        'is_active'
    ];

    protected $casts = [
        'rate_multiplier' => 'decimal:4',
        'is_active' => 'boolean',
    ];

    /**
     * Get the company that owns this setting
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the current active night differential setting
     */
    public static function current()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Get the current night differential setting (regardless of active status)
     */
    public static function currentSetting()
    {
        return static::first();
    }
}
