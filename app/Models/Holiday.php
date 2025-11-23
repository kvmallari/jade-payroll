<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Holiday extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'description',
        'date',
        'type',
        'is_paid',
        'pay_applicable_to',
        'rate_multiplier',
        'is_double_pay',
        'double_pay_rate',
        'pay_rule',
        'is_recurring',
        'is_active',
        'year',
        'sort_order'
    ];

    protected $casts = [
        'date' => 'date',
        'is_paid' => 'boolean',
        'rate_multiplier' => 'decimal:2',
        'double_pay_rate' => 'decimal:2',
        'is_double_pay' => 'boolean',
        'is_recurring' => 'boolean',
        'is_active' => 'boolean',
        'year' => 'integer',
        'sort_order' => 'integer'
    ];

    protected $attributes = [
        'is_active' => true, // Default to active (will be auto-disabled if past date)
        'is_recurring' => false,
        'is_double_pay' => false,
        'is_paid' => true,
        'pay_applicable_to' => null,
        'type' => 'regular',
        'rate_multiplier' => 1.00,
        'double_pay_rate' => 2.00,
        'pay_rule' => 'full'
    ];

    /**
     * Check if this holiday is in the past
     */
    public function isPast()
    {
        return $this->date < now()->startOfDay();
    }
}
