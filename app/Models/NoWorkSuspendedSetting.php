<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoWorkSuspendedSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'description',
        'date_from',
        'date_to',
        'time_from',
        'time_to',
        'type',
        'reason',
        'is_paid',
        'pay_rule',
        'pay_applicable_to',
        'status',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'time_from' => 'datetime:H:i',
        'time_to' => 'datetime:H:i',
        'is_paid' => 'boolean',
    ];

    /**
     * Get the company that owns this setting
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Scope to get only active records
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to get by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get current/ongoing suspensions
     */
    public function scopeCurrent($query)
    {
        $today = now()->toDateString();
        return $query->where('date_from', '<=', $today)
            ->where('date_to', '>=', $today)
            ->where('status', 'active');
    }

    /**
     * Scope to get by date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('date_from', [$startDate, $endDate])
                ->orWhereBetween('date_to', [$startDate, $endDate])
                ->orWhere(function ($sq) use ($startDate, $endDate) {
                    $sq->where('date_from', '<=', $startDate)
                        ->where('date_to', '>=', $endDate);
                });
        });
    }

    /**
     * Check if employee is affected by this suspension
     */
    public function isEmployeeAffected($employee)
    {
        switch ($this->scope) {
            case 'company_wide':
                return true;

            case 'department':
                return $this->affected_departments &&
                    in_array($employee->department_id, $this->affected_departments);

            case 'position':
                return $this->affected_positions &&
                    in_array($employee->position_id, $this->affected_positions);

            case 'specific_employees':
                return $this->affected_employees &&
                    in_array($employee->id, $this->affected_employees);

            default:
                return false;
        }
    }

    /**
     * Calculate pay rate for affected employee
     */
    public function calculatePayRate($employee, $regularDailyRate)
    {
        if (!$this->isEmployeeAffected($employee)) {
            return $regularDailyRate; // Not affected, full pay
        }

        switch ($this->pay_rule) {
            case 'no_pay':
                return 0;

            case 'half_pay':
                return $regularDailyRate * 0.5;

            case 'full_pay':
                return $regularDailyRate;

            case 'custom_rate':
                return $regularDailyRate * ($this->custom_pay_rate ?? 0);

            default:
                return 0;
        }
    }

    /**
     * Get the number of affected days
     */
    public function getAffectedDaysCount()
    {
        return $this->date_from->diffInDays($this->date_to) + 1;
    }

    /**
     * Check if suspension is partial day
     */
    public function isPartialDay()
    {
        return $this->type === 'partial_suspension' && $this->time_from && $this->time_to;
    }

    /**
     * Calculate partial day hours affected
     */
    public function getPartialHours()
    {
        if (!$this->isPartialDay()) {
            return 0;
        }

        $timeFrom = \Carbon\Carbon::createFromFormat('H:i', $this->time_from->format('H:i'));
        $timeTo = \Carbon\Carbon::createFromFormat('H:i', $this->time_to->format('H:i'));

        return $timeFrom->diffInHours($timeTo);
    }

    /**
     * Relationships
     */
    public function affectedDepartments()
    {
        if (!$this->affected_departments) return collect();

        return Department::whereIn('id', $this->affected_departments)->get();
    }

    public function affectedPositions()
    {
        if (!$this->affected_positions) return collect();

        return Position::whereIn('id', $this->affected_positions)->get();
    }

    public function affectedEmployees()
    {
        if (!$this->affected_employees) return collect();

        return Employee::whereIn('id', $this->affected_employees)->get();
    }
}
