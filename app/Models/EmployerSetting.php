<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployerSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'registered_business_name',
        'tax_identification_number',
        'rdo_code',
        'sss_employer_number',
        'philhealth_employer_number',
        'hdmf_employer_number',
        'registered_address',
        'postal_zip_code',
        'landline_mobile',
        'office_business_email',
        'signatory_name',
        'signatory_designation',
    ];

    /**
     * Get the company that owns this setting
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the singleton instance of employer settings
     */
    public static function getSettings()
    {
        return static::first() ?: new static();
    }

    /**
     * Update or create employer settings
     */
    public static function updateSettings(array $data)
    {
        $settings = static::first();

        if ($settings) {
            $settings->update($data);
        } else {
            $settings = static::create($data);
        }

        return $settings;
    }
}
