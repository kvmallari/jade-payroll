<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GracePeriodSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'late_grace_minutes',
        'undertime_grace_minutes',
        'overtime_threshold_minutes',
        'is_active'
    ];

    protected $casts = [
        'late_grace_minutes' => 'integer',
        'undertime_grace_minutes' => 'integer',
        'overtime_threshold_minutes' => 'integer',
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
     * Get the current active grace period settings (always ID 1)
     */
    public static function current()
    {
        return self::find(1) ?? self::getDefault();
    }

    /**
     * Get default grace period settings
     */
    public static function getDefault()
    {
        return new self([
            'late_grace_minutes' => 0,
            'undertime_grace_minutes' => 0,
            'overtime_threshold_minutes' => 0,
            'is_active' => true,
        ]);
    }

    /**
     * Update the grace period settings (always updates ID 1)
     */
    public static function updateCurrent(array $data)
    {
        $setting = self::find(1);

        if ($setting) {
            // Update existing record
            $setting->update($data);
            return $setting;
        } else {
            // Create the single record if it doesn't exist
            return self::create(array_merge($data, [
                'id' => 1,
                'is_active' => true
            ]));
        }
    }
}
