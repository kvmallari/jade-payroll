<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BIR2316Setting extends Model
{
    protected $table = 'bir2316_settings';

    protected $fillable = [
        'company_id',
        'tax_year',
        'statutory_minimum_wage_per_day',
        'statutory_minimum_wage_per_month',
        'period_from',
        'period_to',
        'place_of_issue',
        'amount_paid_ctc',
        'date_signed_by_authorized_person',
        'date_signed_by_employee',
        'date_issued',
    ];

    protected $casts = [
        'tax_year' => 'integer',
        'statutory_minimum_wage_per_day' => 'decimal:2',
        'statutory_minimum_wage_per_month' => 'decimal:2',
        'amount_paid_ctc' => 'decimal:2',
        'date_signed_by_authorized_person' => 'date',
        'date_signed_by_employee' => 'date',
        'date_issued' => 'date',
    ];

    /**
     * Get the company that owns this setting
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get settings for a specific tax year
     */
    public static function getSettingsForYear($year)
    {
        return self::where('tax_year', $year)->first();
    }

    /**
     * Get or create settings for a specific tax year
     */
    public static function getOrCreateForYear($year)
    {
        return self::firstOrCreate(
            ['tax_year' => $year],
            [
                'statutory_minimum_wage_per_day' => null,
                'statutory_minimum_wage_per_month' => null,
                'period_from' => '01-01',
                'period_to' => '12-31',
                'place_of_issue' => '',
                'amount_paid_ctc' => null,
                'date_signed_by_authorized_person' => null,
                'date_signed_by_employee' => null,
                'date_issued' => null,
            ]
        );
    }
}
