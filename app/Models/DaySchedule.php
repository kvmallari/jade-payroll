<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DaySchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'monday',
        'tuesday',
        'wednesday',
        'thursday',
        'friday',
        'saturday',
        'sunday',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'monday' => 'boolean',
        'tuesday' => 'boolean',
        'wednesday' => 'boolean',
        'thursday' => 'boolean',
        'friday' => 'boolean',
        'saturday' => 'boolean',
        'sunday' => 'boolean',
        'is_active' => 'boolean',
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

    public function getDaysDisplayAttribute()
    {
        $days = [];
        if ($this->monday) $days[] = 'Mon';
        if ($this->tuesday) $days[] = 'Tue';
        if ($this->wednesday) $days[] = 'Wed';
        if ($this->thursday) $days[] = 'Thu';
        if ($this->friday) $days[] = 'Fri';
        if ($this->saturday) $days[] = 'Sat';
        if ($this->sunday) $days[] = 'Sun';

        if (empty($days)) {
            return 'No days selected';
        }

        // Check for common patterns
        if (count($days) === 5 && !$this->saturday && !$this->sunday) {
            return 'Monday to Friday';
        }
        if (count($days) === 6 && !$this->sunday) {
            return 'Monday to Saturday';
        }
        if (count($days) === 7) {
            return 'All days';
        }

        return implode(', ', $days);
    }

    public function getWorkingDaysCountAttribute()
    {
        return collect([
            $this->monday,
            $this->tuesday,
            $this->wednesday,
            $this->thursday,
            $this->friday,
            $this->saturday,
            $this->sunday,
        ])->filter()->count();
    }

    /**
     * Check if a given day is a working day for this schedule
     * @param string $dayName (monday, tuesday, wednesday, etc.) or Carbon instance
     * @return bool
     */
    public function isWorkingDay($day)
    {
        if ($day instanceof \Carbon\Carbon) {
            $dayName = strtolower($day->format('l')); // 'monday', 'tuesday', etc.
        } else {
            $dayName = strtolower($day);
        }

        return match ($dayName) {
            'monday' => (bool) $this->monday,
            'tuesday' => (bool) $this->tuesday,
            'wednesday' => (bool) $this->wednesday,
            'thursday' => (bool) $this->thursday,
            'friday' => (bool) $this->friday,
            'saturday' => (bool) $this->saturday,
            'sunday' => (bool) $this->sunday,
            default => false,
        };
    }

    /**
     * Check if a given day is a rest day for this schedule
     * @param string $dayName or Carbon instance
     * @return bool
     */
    public function isRestDay($day)
    {
        return !$this->isWorkingDay($day);
    }

    /**
     * Get all working days as array of day names
     * @return array
     */
    public function getWorkingDays()
    {
        $workingDays = [];

        if ($this->monday) $workingDays[] = 'monday';
        if ($this->tuesday) $workingDays[] = 'tuesday';
        if ($this->wednesday) $workingDays[] = 'wednesday';
        if ($this->thursday) $workingDays[] = 'thursday';
        if ($this->friday) $workingDays[] = 'friday';
        if ($this->saturday) $workingDays[] = 'saturday';
        if ($this->sunday) $workingDays[] = 'sunday';

        return $workingDays;
    }

    /**
     * Get all rest days as array of day names
     * @return array
     */
    public function getRestDays()
    {
        $allDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $workingDays = $this->getWorkingDays();

        return array_diff($allDays, $workingDays);
    }
}
