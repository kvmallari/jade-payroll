<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Settings\PayScheduleSettingController;
use App\Http\Controllers\Settings\DeductionTaxSettingController;
use App\Http\Controllers\Settings\AllowanceBonusSettingController;
use App\Http\Controllers\Settings\PaidLeaveSettingController;
use App\Http\Controllers\Settings\HolidaySettingController;
use App\Http\Controllers\Settings\NoWorkSuspendedSettingController;
use App\Http\Controllers\Settings\EmployeeSettingController;
use App\Http\Controllers\Settings\EmployerSettingController;
use App\Http\Controllers\EmploymentTypeController;

Route::middleware(['auth', 'verified'])->prefix('settings')->name('settings.')->group(function () {

    // Employee Settings
    Route::get('employee', [EmployeeSettingController::class, 'index'])->name('employee.index');
    Route::post('employee', [EmployeeSettingController::class, 'update'])->name('employee.update');
    Route::post('employee/reset', [EmployeeSettingController::class, 'reset'])->name('employee.reset');
    Route::get('employee/next-number', [EmployeeSettingController::class, 'getNextEmployeeNumber'])->name('employee.next-number');

    // Employer Settings
    Route::get('employer', [EmployerSettingController::class, 'index'])->name('employer.index');
    Route::post('employer', [EmployerSettingController::class, 'update'])->name('employer.update');

    // Employment Type Management
    Route::resource('employment-types', EmploymentTypeController::class);

    // Pay Schedule Settings - DISABLED (using new PayScheduleController instead)
    // Route::get('pay-schedules', [PayScheduleSettingController::class, 'index'])->name('pay-schedules.index');
    // Route::get('pay-schedules/{paySchedule}', [PayScheduleSettingController::class, 'show'])->name('pay-schedules.show');
    // Route::get('pay-schedules/{paySchedule}/edit', [PayScheduleSettingController::class, 'edit'])->name('pay-schedules.edit');
    // Route::put('pay-schedules/{paySchedule}', [PayScheduleSettingController::class, 'update'])->name('pay-schedules.update');
    // Route::patch('pay-schedules/{paySchedule}/toggle', [PayScheduleSettingController::class, 'toggle'])
    //     ->name('pay-schedules.toggle');

    // Deduction/Tax Settings
    Route::resource('deductions', DeductionTaxSettingController::class);
    Route::patch('deductions/{deduction}/toggle', [DeductionTaxSettingController::class, 'toggle'])
        ->name('deductions.toggle');
    Route::post('deductions/calculate-preview', [DeductionTaxSettingController::class, 'calculatePreview'])
        ->name('deductions.calculate-preview');
    Route::get('deductions/philhealth/tax-table', [DeductionTaxSettingController::class, 'getPhilHealthTaxTable'])
        ->name('deductions.philhealth.tax-table');
    Route::get('deductions/sss/tax-table', [DeductionTaxSettingController::class, 'getSssTaxTable'])
        ->name('deductions.sss.tax-table');
    Route::get('deductions/pagibig/tax-table', [DeductionTaxSettingController::class, 'getPagibigTaxTable'])
        ->name('deductions.pagibig.tax-table');
    Route::get('deductions/withholding-tax/tax-table', [DeductionTaxSettingController::class, 'getWithholdingTaxTable'])
        ->name('deductions.withholding-tax.tax-table');

    // Allowance/Bonus Settings
    Route::resource('allowances', AllowanceBonusSettingController::class);
    Route::patch('allowances/{allowance}/toggle', [AllowanceBonusSettingController::class, 'toggle'])
        ->name('allowances.toggle');
    Route::post('allowances/calculate-preview', [AllowanceBonusSettingController::class, 'calculatePreview'])
        ->name('allowances.calculate-preview');

    // Paid Leave Settings
    Route::resource('leaves', PaidLeaveSettingController::class)->parameters(['leaves' => 'leave']);
    Route::patch('leaves/{leave}/toggle', [PaidLeaveSettingController::class, 'toggle'])
        ->name('leaves.toggle');
    Route::post('leaves/calculate-preview', [PaidLeaveSettingController::class, 'calculatePreview'])
        ->name('leaves.calculate-preview');

    // Holiday Settings
    Route::resource('holidays', HolidaySettingController::class);
    Route::patch('holidays/{holiday}/toggle', [HolidaySettingController::class, 'toggle'])
        ->name('holidays.toggle');
    Route::get('holidays/filter/year', [HolidaySettingController::class, 'filterByYear'])
        ->name('holidays.filter-year');
    Route::post('holidays/generate-recurring', [HolidaySettingController::class, 'generateRecurring'])
        ->name('holidays.generate-recurring');

    // Suspension Settings
    Route::resource('suspension', NoWorkSuspendedSettingController::class);
    Route::patch('suspension/{suspension}/activate', [NoWorkSuspendedSettingController::class, 'activate'])
        ->name('suspension.activate');
    Route::patch('suspension/{suspension}/complete', [NoWorkSuspendedSettingController::class, 'complete'])
        ->name('suspension.complete');
    Route::patch('suspension/{suspension}/cancel', [NoWorkSuspendedSettingController::class, 'cancel'])
        ->name('suspension.cancel');
    Route::patch('suspension/{suspension}/toggle', [NoWorkSuspendedSettingController::class, 'toggle'])
        ->name('suspension.toggle');

    // Time Logs Settings
    Route::get('time-logs', [\App\Http\Controllers\Settings\TimeLogSettingController::class, 'index'])
        ->name('time-logs.index');

    // Day and Time Schedule Management
    Route::resource('time-logs/day-schedules', \App\Http\Controllers\Settings\DayScheduleController::class, [
        'as' => 'time-logs'
    ]);
    Route::resource('time-logs/time-schedules', \App\Http\Controllers\Settings\TimeScheduleController::class, [
        'as' => 'time-logs'
    ]);

    // Break Period Management
    Route::post('time-logs/time-schedules/{timeSchedule}/break-periods', [\App\Http\Controllers\Settings\TimeScheduleController::class, 'updateBreakPeriods'])
        ->name('time-logs.time-schedules.break-periods.update');

    // Grace Period Settings
    Route::get('time-logs/grace-period', [\App\Http\Controllers\Settings\GracePeriodController::class, 'index'])
        ->name('time-logs.grace-period.index');
    Route::post('time-logs/grace-period', [\App\Http\Controllers\Settings\GracePeriodController::class, 'update'])
        ->name('time-logs.grace-period.update');

    // Night Differential Settings
    Route::post('time-logs/night-differential', [\App\Http\Controllers\Settings\NightDifferentialController::class, 'update'])
        ->name('time-logs.night-differential.update');
});
