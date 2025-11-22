# Multi-Company Instance Setup - Implementation Guide

## Summary

Each company created now gets a FRESH INSTALL instance with default configurations. When a System Admin logs in to their company, they see a clean slate ready to configure.

## What's Been Done

### 1. Database Migrations ✅

- Added `company_id` to `payroll_rate_configurations`
- Added `company_id` to `departments`, `positions`, `holidays`
- All settings tables now have company scoping

### 2. CompanyInitializationService ✅

Creates fresh defaults for each new company:

**Government Deductions (Inactive until configured):**

- SSS Contribution
- PhilHealth Contribution
- Pag-IBIG Contribution
- Withholding Tax

**Rate Multipliers (All set to 0%):**

- Regular Workday
- Rest Day
- Regular Holiday
- Special Holiday
- Suspension of Work

**Other Settings (Blank/0 values):**

- Grace Period (0 minutes)
- Night Differential (0%, inactive)
- Employer Settings (all NULL)

**Empty (Admin configures):**

- Departments & Positions
- Pay Schedules (tabs exist, schedules empty)
- Allowances
- Leaves
- Holidays
- Suspensions
- Time Logs

### 3. Models Updated

Need to add `company_id` to fillable arrays:

- ✅ `PayrollRateConfiguration`
- ⚠️ `Department`
- ⚠️ `Position`
- ⚠️ `Holiday`

### 4. Controllers Need Company Scoping

All setting controllers need to filter by company_id:

**Priority Controllers:**

1. `DeductionTaxSettingController` - Government deductions
2. `PayrollRateConfigurationController` - Rate multipliers
3. `EmployerSettingController` - Employer settings
4. `GracePeriodController` - Grace period
5. `NightDifferentialController` - Night differential
6. `AllowanceBonusSettingController` - Allowances
7. `PaidLeaveSettingController` - Leaves
8. `HolidayController` - Holidays
9. `DepartmentController` - Departments
10. `PositionController` - Positions
11. `PayScheduleController` - Pay schedules

**Pattern to Apply:**

```php
public function index()
{
    $user = Auth::user();
    $query = Model::query();

    // Company scoping
    if (!$user->isSuperAdmin()) {
        $query->where('company_id', $user->company_id);
    }

    $items = $query->get();
    // ...
}
```

## Testing

Create a new company and verify:

1. Government deductions created (inactive, 0 values)
2. Rate multipliers created (all 0%)
3. Settings created (blank/0)
4. No departments, positions, holidays, leaves, allowances

## Next Steps

1. Update all controller index/create/store methods with company scoping
2. Update models to include company_id in fillable
3. Update views to auto-assign company_id in forms
4. Test with multiple companies to ensure complete isolation

## Files Modified

- `app/Services/CompanyInitializationService.php`
- `database/migrations/2025_11_23_015859_add_company_id_to_payroll_rate_configurations_table.php`
- `database/migrations/2025_11_23_020137_add_company_id_to_departments_positions_holidays_suspensions.php`
- `app/Models/PayrollRateConfiguration.php`
