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
     * Get the singleton instance of employer settings for the current user's company
     */
    public static function getSettings($companyId = null)
    {
        $query = static::query();

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->first() ?: new static(['company_id' => $companyId]);
    }

    /**
     * Update or create employer settings for a specific company
     */
    public static function updateSettings(array $data, $companyId = null)
    {
        if ($companyId) {
            $settings = static::where('company_id', $companyId)->first();
        } else {
            $settings = static::first();
        }

        if ($settings) {
            $settings->update($data);
        } else {
            $data['company_id'] = $companyId;
            $settings = static::create($data);
        }

        return $settings;
    }
}
