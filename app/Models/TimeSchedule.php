<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'time_in',
        'time_out',
        'break_start',
        'break_end',
        'break_duration_minutes',
        'total_hours',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'time_in' => 'datetime:H:i',
        'time_out' => 'datetime:H:i',
        'break_start' => 'datetime:H:i',
        'break_end' => 'datetime:H:i',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTimeRangeAttribute()
    {
        return $this->time_in->format('g:i A') . ' - ' . $this->time_out->format('g:i A');
    }

    public function getTimeRangeDisplayAttribute()
    {
        return $this->time_in->format('g:i A') . ' - ' . $this->time_out->format('g:i A');
    }

    /**
     * Calculate the total working hours for this time schedule.
     * Returns hours as decimal (e.g., 8.5 for 8 hours 30 minutes).
     */
    public function calculateTotalHours()
    {
        if (!$this->time_in || !$this->time_out) {
            return 8.0; // Default 8 hours
        }

        // Calculate total scheduled minutes
        $timeIn = \Carbon\Carbon::parse($this->time_in);
        $timeOut = \Carbon\Carbon::parse($this->time_out);

        // Handle next day time out
        if ($timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        $totalScheduledMinutes = $timeIn->diffInMinutes($timeOut);

        // Subtract break time based on break configuration
        if ($this->break_start && $this->break_end) {
            // Fixed break: subtract the fixed break duration
            $breakStart = \Carbon\Carbon::parse($this->break_start);
            $breakEnd = \Carbon\Carbon::parse($this->break_end);

            // Handle next day break end
            if ($breakEnd->lt($breakStart)) {
                $breakEnd->addDay();
            }

            $breakMinutes = $breakStart->diffInMinutes($breakEnd);
            $totalScheduledMinutes -= $breakMinutes;
        } elseif ($this->break_duration_minutes && $this->break_duration_minutes > 0) {
            // Flexible break: subtract the flexible break duration
            $totalScheduledMinutes -= $this->break_duration_minutes;
        }
        // If no break configured, use full scheduled time

        // Convert minutes to hours and round to 2 decimal places
        return round(max(0, $totalScheduledMinutes) / 60, 2);
    }

    /**
     * Calculate the total working hours in minutes for this time schedule.
     * This will be used as the overtime threshold for employees on this schedule.
     */
    public function getOvertimeThresholdMinutes()
    {
        if (!$this->time_in || !$this->time_out) {
            return 480; // Default 8 hours = 480 minutes
        }

        // Calculate total scheduled minutes
        $timeIn = \Carbon\Carbon::parse($this->time_in);
        $timeOut = \Carbon\Carbon::parse($this->time_out);

        // Handle next day time out
        if ($timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        $totalScheduledMinutes = $timeIn->diffInMinutes($timeOut);

        // Subtract break time based on break configuration
        if ($this->break_start && $this->break_end) {
            // Fixed break: subtract the fixed break duration
            $breakStart = \Carbon\Carbon::parse($this->break_start);
            $breakEnd = \Carbon\Carbon::parse($this->break_end);

            // Handle next day break end
            if ($breakEnd->lt($breakStart)) {
                $breakEnd->addDay();
            }

            $breakMinutes = $breakStart->diffInMinutes($breakEnd);
            $totalScheduledMinutes -= $breakMinutes;
        } elseif ($this->break_duration_minutes && $this->break_duration_minutes > 0) {
            // Flexible break: subtract the flexible break duration
            $totalScheduledMinutes -= $this->break_duration_minutes;
        }
        // If no break configured, use full scheduled time

        return max(0, $totalScheduledMinutes);
    }
}
