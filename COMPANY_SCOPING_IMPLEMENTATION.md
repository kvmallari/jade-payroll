# Company Scoping Implementation Guide

## Overview

This document outlines the implementation of company-specific scoping for all settings tables to ensure each company has isolated data.

## Database Changes

### Migration Created

**File**: `database/migrations/2025_11_23_100000_add_company_id_to_settings_tables.php`

Adds `company_id` to:

- `pay_schedules`
- `deduction_tax_settings`
- `allowance_bonus_settings`
- `holidays`

Existing records assigned to default company (ID 1).

### Tables Already with company_id

✅ departments
✅ positions  
✅ payroll_rate_configurations
✅ paid_leave_settings
✅ no_work_suspended_settings
✅ employer_settings
✅ grace_period_settings
✅ night_differential_settings
✅ bir_2316_settings

## Model Updates

### Models Updated

✅ PaySchedule - added `company_id` to fillable
✅ Holiday - added `company_id` to fillable
✅ Department - added `company_id` to fillable
✅ Position - added `company_id` to fillable

### Models Already Have company_id

✅ DeductionTaxSetting
✅ AllowanceBonusSetting
✅ PaidLeaveSetting
✅ NoWorkSuspendedSetting
✅ PayrollRateConfiguration

## Controller Updates Required

### Pattern to Follow

```php
// In index() method - Scope queries
$query = Model::query();
if (!Auth::user()->isSuperAdmin()) {
    $query->where('company_id', Auth::user()->company_id);
}

// In store() method - Auto-assign company_id
$validated['company_id'] = Auth::user()->company_id;
```

### Controllers That Need Updates

#### ✅ COMPLETED

1. **DepartmentController**
    - ✅ Added company scoping to index()
    - ✅ Added auto-assign company_id to store()

#### 🔄 IN PROGRESS

2. **PositionController**
    - index() - scope to company
    - create() - scope departments to company
    - store() - auto-assign company_id
    - edit() - scope departments to company

3. **Settings\PayScheduleController**
    - index() - scope to company
    - store() - auto-assign company_id
4. **Settings\HolidaySettingController**
    - index() - scope to company
    - store() - auto-assign company_id

5. **Settings\DeductionTaxSettingController**
    - index() - scope to company (if not already)
    - store() - auto-assign company_id (if not already)

6. **Settings\AllowanceBonusSettingController**
    - index() - scope to company (if not already)
    - store() - auto-assign company_id (if not already)

7. **Settings\PaidLeaveSettingController**
    - Verify company scoping exists

8. **Settings\NoWorkSuspendedSettingController**
    - Verify company scoping exists

9. **PayrollRateConfigurationController**
    - Verify company scoping exists

10. **TimeLogController**
    - Scopes through employee relationship (no direct change needed)

## Testing Checklist

After implementation, test with a new company:

### Fresh Installation Experience

- [ ] Departments page - blank
- [ ] Positions page - blank
- [ ] Pay Schedules - tabs present, no data
- [ ] Rate Multipliers - types present (Regular, Rest Day, Holiday, Suspension), rates at 0
- [ ] Deductions - government types present (SSS, PhilHealth, Pag-IBIG, Withholding Tax), unconfigured
- [ ] Allowances - blank
- [ ] Leaves - blank
- [ ] Holidays - blank
- [ ] Suspensions - blank
- [ ] Time Logs - blank

### Data Isolation

- [ ] Company A cannot see Company B's departments
- [ ] Company A cannot see Company B's positions
- [ ] Company A cannot see Company B's pay schedules
- [ ] Company A cannot see Company B's holidays
- [ ] Company A cannot see Company B's leave settings
- [ ] Company A cannot see Company B's allowances
- [ ] Company A cannot see Company B's suspensions

## Seeder Updates Needed

Create default government deductions for new companies:

- SSS (unconfigured)
- PhilHealth (unconfigured)
- Pag-IBIG (unconfigured)
- Withholding Tax (unconfigured)

Create default rate types for new companies:

- Regular (0%)
- Rest Day (0%)
- Holiday (0%)
- Suspension (0%)

## Migration Commands

```bash
php artisan migrate
```

## Rollback Plan

If issues occur:

```bash
php artisan migrate:rollback --step=1
```

This will remove company_id columns from the settings tables.
