<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PaySchedule extends Model
{
    use HasFactory;

    // Explicitly set table name to ensure we use pay_schedules (not pay_schedule_settings)
    protected $table = 'pay_schedules';

    protected $fillable = [
        'company_id',
        'name',
        'type',
        'description',
        'cutoff_periods',
        'move_if_holiday',
        'move_if_weekend',
        'move_direction',
        'is_active',
        'is_default',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'cutoff_periods' => 'array',
        'move_if_holiday' => 'boolean',
        'move_if_weekend' => 'boolean',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * The user who created this pay schedule
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The user who last updated this pay schedule
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Employees using this pay schedule
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'pay_schedule_id');
    }

    /**
     * Payrolls using this pay schedule
     */
    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class, 'pay_schedule_id');
    }

    /**
     * Scope to get only active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get schedules by type
     */
    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to get default schedules
     */
    public function scopeDefaults($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get the default schedule for a type
     */
    public static function getDefaultForType($type)
    {
        return static::where('type', $type)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all schedules grouped by type
     */
    public static function getAllGroupedByType()
    {
        return static::active()
            ->orderBy('type')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->groupBy('type');
    }

    /**
     * Calculate current pay period for this schedule
     */
    public function calculateCurrentPayPeriod($baseDate = null)
    {
        $baseDate = $baseDate ?: now();

        // Implementation will depend on the cutoff_periods structure
        // This is a placeholder - you'll need to implement the logic
        // based on your existing PayScheduleSetting calculations

        return [
            'start' => $baseDate->startOfMonth(),
            'end' => $baseDate->endOfMonth(),
            'pay_date' => $baseDate->endOfMonth(),
        ];
    }

    /**
     * Get formatted display name
     */
    public function getDisplayNameAttribute()
    {
        return $this->name . ' (' . ucfirst(str_replace('_', ' ', $this->type)) . ')';
    }

    /**
     * Get cutoff periods with validation
     */
    public function getValidatedCutoffPeriods()
    {
        $periods = $this->cutoff_periods ?? [];

        // Ensure we have the required structure based on type
        switch ($this->type) {
            case 'weekly':
                return $this->validateWeeklyCutoffs($periods);
            case 'semi_monthly':
                return $this->validateSemiMonthlyCutoffs($periods);
            case 'monthly':
                return $this->validateMonthlyCutoffs($periods);
            default:
                return [];
        }
    }

    /**
     * Validate weekly cutoff structure
     */
    private function validateWeeklyCutoffs($periods)
    {
        if (!isset($periods[0])) return [];

        $period = $periods[0];
        return [
            [
                'start_day' => $period['start_day'] ?? 'monday',
                'end_day' => $period['end_day'] ?? 'friday',
                'pay_day' => $period['pay_day'] ?? 'friday',
            ]
        ];
    }

    /**
     * Validate semi-monthly cutoff structure
     */
    private function validateSemiMonthlyCutoffs($periods)
    {
        $validated = [];

        // First cutoff
        if (isset($periods[0])) {
            $validated[] = [
                'start_day' => is_numeric($periods[0]['start_day'] ?? 1) ? (int)($periods[0]['start_day'] ?? 1) : ($periods[0]['start_day'] ?? 1),
                'end_day' => is_numeric($periods[0]['end_day'] ?? 15) ? (int)($periods[0]['end_day'] ?? 15) : ($periods[0]['end_day'] ?? 15),
                'pay_date' => is_numeric($periods[0]['pay_date'] ?? 16) ? (int)($periods[0]['pay_date'] ?? 16) : ($periods[0]['pay_date'] ?? 16),
            ];
        }

        // Second cutoff
        if (isset($periods[1])) {
            $validated[] = [
                'start_day' => is_numeric($periods[1]['start_day'] ?? 16) ? (int)($periods[1]['start_day'] ?? 16) : ($periods[1]['start_day'] ?? 16),
                'end_day' => is_numeric($periods[1]['end_day'] ?? 'EOD') ? (int)($periods[1]['end_day'] ?? 'EOD') : ($periods[1]['end_day'] ?? 'EOD'), // Support EOD for end of month
                'pay_date' => is_numeric($periods[1]['pay_date'] ?? 5) ? (int)($periods[1]['pay_date'] ?? 5) : ($periods[1]['pay_date'] ?? 5),
            ];
        }

        return $validated;
    }

    /**
     * Validate monthly cutoff structure
     */
    private function validateMonthlyCutoffs($periods)
    {
        if (!isset($periods[0])) return [];

        $period = $periods[0];
        return [
            [
                'start_day' => is_numeric($period['start_day'] ?? 1) ? (int)($period['start_day'] ?? 1) : ($period['start_day'] ?? 1),
                'end_day' => $period['end_day'] ?? 'EOD', // Support EOD for end of month
                'pay_date' => $period['pay_date'] ?? 'EOD', // Support EOD for pay date
            ]
        ];
    }

    /**
     * Boot method to handle model events
     */
    protected static function boot()
    {
        parent::boot();

        // When creating a new pay schedule
        static::creating(function ($model) {
            if (Auth::id()) {
                $model->created_by = Auth::id();
            }

            // If this is set as default, unset other defaults of same type
            if ($model->is_default) {
                static::where('type', $model->type)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        // When updating a pay schedule
        static::updating(function ($model) {
            if (Auth::id()) {
                $model->updated_by = Auth::id();
            }

            // If this is set as default, unset other defaults of same type
            if ($model->is_default && $model->isDirty('is_default')) {
                static::where('type', $model->type)
                    ->where('id', '!=', $model->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });

        // Prevent deleting default schedules
        static::deleting(function ($model) {
            if ($model->is_default) {
                throw new \Exception('Cannot delete default pay schedule. Please set another schedule as default first.');
            }

            if ($model->employees()->count() > 0) {
                throw new \Exception('Cannot delete pay schedule that has employees assigned to it.');
            }
        });
    }
}
