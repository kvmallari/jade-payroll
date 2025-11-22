<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRateConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'type_name',
        'display_name',
        'regular_rate_multiplier',
        'overtime_rate_multiplier',
        'description',
        'is_active',
        'is_system',
        'sort_order',
    ];

    protected $casts = [
        'regular_rate_multiplier' => 'decimal:4',
        'overtime_rate_multiplier' => 'decimal:4',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Scope to get only active configurations
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get configurations ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    /**
     * Get default rate configurations
     */
    public static function getDefaults()
    {
        return [
            [
                'type_name' => 'regular_workday',
                'display_name' => 'Regular Workday',
                'regular_rate_multiplier' => 1.0000,
                'overtime_rate_multiplier' => 1.2500,
                'description' => 'Standard working day rates',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 1,
            ],
            [
                'type_name' => 'rest_day',
                'display_name' => 'Rest Day',
                'regular_rate_multiplier' => 1.3000,
                'overtime_rate_multiplier' => 1.6900,
                'description' => 'Rest day premium rates',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 2,
            ],
            [
                'type_name' => 'special_holiday',
                'display_name' => 'Special (Non-working) Holiday',
                'regular_rate_multiplier' => 1.3000,
                'overtime_rate_multiplier' => 1.6900,
                'description' => 'Special holiday premium rates',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 3,
            ],
            [
                'type_name' => 'regular_holiday',
                'display_name' => 'Regular Holiday',
                'regular_rate_multiplier' => 2.0000,
                'overtime_rate_multiplier' => 2.6000,
                'description' => 'Regular holiday premium rates',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 4,
            ],
            [
                'type_name' => 'rest_day_regular_holiday',
                'display_name' => 'Rest Day + Regular Holiday',
                'regular_rate_multiplier' => 2.6000,
                'overtime_rate_multiplier' => 3.3800,
                'description' => 'Rest day coinciding with regular holiday',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 5,
            ],
            [
                'type_name' => 'rest_day_special_holiday',
                'display_name' => 'Rest Day + Special Holiday',
                'regular_rate_multiplier' => 1.5000,
                'overtime_rate_multiplier' => 1.9500,
                'description' => 'Rest day coinciding with special holiday',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 6,
            ],
            [
                'type_name' => 'full_day_suspension',
                'display_name' => 'Full Day Suspension',
                'regular_rate_multiplier' => 1.0000,
                'overtime_rate_multiplier' => 0.0000,
                'description' => 'Full day suspension with fixed daily rate',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 7,
            ],
            [
                'type_name' => 'partial_suspension',
                'display_name' => 'Partial Suspension',
                'regular_rate_multiplier' => 1.0000,
                'overtime_rate_multiplier' => 1.2500,
                'description' => 'Partial suspension with fixed amount + worked hours',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 8,
            ],
        ];
    }

    /**
     * Create default configurations
     */
    public static function createDefaults()
    {
        foreach (self::getDefaults() as $config) {
            self::updateOrCreate(
                ['type_name' => $config['type_name']],
                $config
            );
        }
    }
}
