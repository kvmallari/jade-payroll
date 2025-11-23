<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\TimeLogController;
use App\Http\Controllers\DTRController;
use App\Http\Controllers\GovernmentFormsController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\PayrollScheduleSettingsController;
use App\Http\Controllers\CashAdvanceController;
use App\Http\Controllers\PaidLeaveController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\EmploymentTypeController;
use App\Http\Controllers\TimeScheduleController;
use App\Http\Controllers\DayScheduleController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\CompanySelectorController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

Route::get('/', function () {
    // Show login page directly
    return app(\App\Http\Controllers\Auth\AuthenticatedSessionController::class)->create();
})->name('login');

// Dashboard
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Profile routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Company Selector (Super Admin only)
    Route::post('/company/select', [CompanySelectorController::class, 'setCompany'])->name('company.select');
    Route::post('/company/clear', [CompanySelectorController::class, 'clearCompany'])->name('company.clear');

    // Employee Management
    Route::middleware('can:view employees')->group(function () {
        // Employee summary generation
        Route::post('employees/generate-summary', [EmployeeController::class, 'generateSummary'])
            ->name('employees.generate-summary');

        Route::resource('employees', EmployeeController::class);
        // API endpoint for deduction calculation during employee creation
        Route::post('employees/calculate-deductions', [EmployeeController::class, 'calculateDeductions'])->name('employees.calculate-deductions');
        // API endpoint for checking duplicate employee numbers
        Route::post('employees/check-duplicate', [EmployeeController::class, 'checkDuplicate'])->name('employees.check-duplicate');
        // API endpoint for getting pay schedules by type
        Route::get('employees/pay-schedules/{type}', [EmployeeController::class, 'getPaySchedulesByType'])->name('employees.pay-schedules-by-type');
    });

    // Department Management
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::resource('departments', DepartmentController::class);
        Route::get('departments/{department}/positions', [DepartmentController::class, 'positions'])->name('departments.positions');
    });

    // Position Management
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::resource('positions', PositionController::class);
    });

    // Employment Type Management
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::resource('employment-types', EmploymentTypeController::class);
    });

    // Time Schedule Management
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::resource('time-schedules', TimeScheduleController::class);
    });

    // Day Schedule Management
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::resource('day-schedules', DayScheduleController::class);
    });

    // Government Forms
    Route::middleware('auth')->group(function () {
        Route::prefix('government-forms')->name('government-forms.')->group(function () {
            Route::get('/', [GovernmentFormsController::class, 'index'])->name('index');

            // BIR Forms
            Route::get('/bir-1601c', [GovernmentFormsController::class, 'bir1601C'])->name('bir-1601c');
            Route::get('/bir-2316', [GovernmentFormsController::class, 'bir2316EmployeeList'])->name('bir-2316.employees');
            Route::get('/bir-2316/settings', [GovernmentFormsController::class, 'bir2316Settings'])->name('bir-2316.settings');
            Route::post('/bir-2316/settings', [GovernmentFormsController::class, 'bir2316UpdateSettings'])->name('bir-2316.settings.update');
            Route::post('/bir-2316/generate-summary', [GovernmentFormsController::class, 'bir2316GenerateSummary'])->name('bir-2316.generate-summary');
            Route::get('/bir-2316/{employee}', [GovernmentFormsController::class, 'bir2316Individual'])->name('bir-2316.individual');
            Route::post('/bir-2316/{employee}/generate', [GovernmentFormsController::class, 'bir2316IndividualGenerate'])->name('bir-2316.individual.generate');
            Route::post('/bir-2316/{employee}/generate-filled', [GovernmentFormsController::class, 'bir2316IndividualGenerateFilled'])->name('bir-2316.individual.generate.filled');
            Route::get('/bir-2316/{employee}/debug-pdf', [GovernmentFormsController::class, 'bir2316DebugPDF'])->name('bir-2316.debug.pdf');
            Route::get('/bir-1604c', [GovernmentFormsController::class, 'bir1604C'])->name('bir-1604c');

            // Government Agency Forms
            Route::get('/sss-r3', [GovernmentFormsController::class, 'sssR3'])->name('sss-r3');
            Route::get('/philhealth-rf1', [GovernmentFormsController::class, 'philHealthRF1'])->name('philhealth-rf1');
            Route::get('/pagibig-mcrf', [GovernmentFormsController::class, 'pagibigMCRF'])->name('pagibig-mcrf');
        });

        // Reports
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/employer-shares', [ReportsController::class, 'employerShares'])->name('employer-shares');
            // Employer shares summary generation
            Route::post('/employer-shares/generate-summary', [ReportsController::class, 'generateEmployerSharesSummary'])
                ->name('employer-shares.generate-summary');
        });
    });

    // Employee Payslips - for employees to view their own payslips
    Route::middleware(['role:Employee'])->group(function () {
        Route::get('payslips', [PayrollController::class, 'employeePayslips'])->name('payslips.index');
        Route::post('payrolls/{payroll}/email-employee-payslip', [App\Http\Controllers\PayslipController::class, 'emailEmployeePayslip'])
            ->name('payslips.email-employee');
    });

    // Payroll Management
    Route::middleware('can:view payrolls')->group(function () {
        // View All Payrolls - shows all payrolls from different periods
        Route::get('payrolls', [PayrollController::class, 'index'])->name('payrolls.index');

        // Automated Payroll - schedule selection and auto-creation for active employees
        Route::get('payrolls/automation', [PayrollController::class, 'automationIndex'])->name('payrolls.automation.index');
        Route::get('payrolls/automation/{frequency}', [PayrollController::class, 'automationSchedules'])->name('payrolls.automation.schedules')->where('frequency', 'weekly|semi_monthly|monthly|daily');
        Route::get('payrolls/automation/{schedule}/{period}', [PayrollController::class, 'automationPeriodList'])->name('payrolls.automation.period')->where(['schedule' => '[A-Za-z0-9_-]+', 'period' => '1st|2nd|current|last']);
        Route::get('payrolls/automation/{schedule}/{period}/{id}', [PayrollController::class, 'showPeriodSpecificPayroll'])->name('payrolls.automation.period.show')->where(['schedule' => '[A-Za-z0-9_\-]+', 'period' => '1st|2nd|current|last', 'id' => '[0-9]+']);
        Route::get('payrolls/automation/create', [PayrollController::class, 'automationCreate'])->name('payrolls.automation.create');
        Route::post('payrolls/automation/store', [PayrollController::class, 'automationStore'])->name('payrolls.automation.store');
        Route::post('payrolls/automation/{schedule}/submit', [PayrollController::class, 'automationSubmit'])->name('payrolls.automation.submit')->where('schedule', '[A-Za-z0-9_\-]+');
        Route::get('payrolls/automation/{schedule}/last-payroll', [PayrollController::class, 'automationLastPayroll'])->name('payrolls.automation.last-payroll')->where('schedule', '[A-Za-z0-9_\-]+');
        Route::get('payrolls/automation/{schedule}', [PayrollController::class, 'automationList'])->name('payrolls.automation.list')->where('schedule', '[A-Za-z0-9_\-]+');

        // Unified Payroll Routes - for individual employee automation payrolls
        // Single route that handles both employee ID (drafts) and payroll ID (saved payrolls)
        Route::get('payrolls/automation/{schedule}/{id}', [PayrollController::class, 'showUnifiedPayroll'])->name('payrolls.automation.show')->where(['schedule' => '[A-Za-z0-9_\-]+', 'id' => '[0-9]+']);
        Route::post('payrolls/automation/{schedule}/{id}/process', [PayrollController::class, 'processUnifiedPayroll'])->name('payrolls.automation.process')->where(['schedule' => '[A-Za-z0-9_\-]+', 'id' => '[0-9]+']);
        Route::post('payrolls/automation/{schedule}/{id}/approve', [PayrollController::class, 'approveUnifiedPayroll'])->name('payrolls.automation.approve')->middleware('can:approve payrolls')->where(['schedule' => '[A-Za-z0-9_\-]+', 'id' => '[0-9]+']);
        Route::post('payrolls/automation/{schedule}/{id}/back-to-draft', [PayrollController::class, 'backToUnifiedDraft'])->name('payrolls.automation.back-to-draft')->where(['schedule' => '[A-Za-z0-9_\-]+', 'id' => '[0-9]+']);

        // // Test Dynamic Payroll Settings
        // Route::get('payrolls/test-dynamic', [PayrollController::class, 'testDynamic'])->name('payrolls.test-dynamic');

        // Manual payroll functionality removed

        // Existing payroll routes
        Route::get('payrolls/{payroll}', [PayrollController::class, 'show'])->name('payrolls.show');
        Route::get('payrolls/{payroll}/edit', [PayrollController::class, 'edit'])->name('payrolls.edit');
        Route::put('payrolls/{payroll}', [PayrollController::class, 'update'])->name('payrolls.update');
        Route::delete('payrolls/{payroll}', [PayrollController::class, 'destroy'])
            ->name('payrolls.destroy');
        // ->middleware('can:delete payrolls'); // Temporarily disabled
        Route::post('payrolls/{payroll}/recalculate', [PayrollController::class, 'recalculate'])
            ->name('payrolls.recalculate')
            ->middleware('can:edit payrolls');

        Route::post('payrolls/generate-from-dtr', [PayrollController::class, 'generateFromDTR'])
            ->name('payrolls.generate-from-dtr')
            ->middleware('can:create payrolls');
        Route::post('payrolls/generate-summary', [PayrollController::class, 'generateSummary'])
            ->name('payrolls.generate-summary')
            ->middleware('can:view payrolls');
        Route::post('payrolls/{payroll}/approve', [PayrollController::class, 'approve'])
            ->name('payrolls.approve')
            ->middleware('can:approve payrolls');
        Route::post('payrolls/{payroll}/process', [PayrollController::class, 'process'])
            ->name('payrolls.process')
            ->middleware('can:process payrolls');
        Route::post('payrolls/{payroll}/back-to-draft', [PayrollController::class, 'backToDraft'])
            ->name('payrolls.back-to-draft')
            ->middleware('can:edit payrolls');

        // Mark as Paid functionality
        Route::post('payrolls/{payroll}/mark-as-paid', [PayrollController::class, 'markAsPaid'])
            ->name('payrolls.mark-as-paid')
            ->middleware('can:mark payrolls as paid');
        Route::post('payrolls/{payroll}/unmark-as-paid', [PayrollController::class, 'unmarkAsPaid'])
            ->name('payrolls.unmark-as-paid')
            ->middleware('can:mark payrolls as paid');

        // Debug route for snapshots (can be removed in production)
        Route::get('payrolls/{payroll}/debug-snapshots', [PayrollController::class, 'debugSnapshots'])
            ->name('payrolls.debug-snapshots')
            ->middleware('can:view payrolls');
    });

    // Payslip routes - accessible to both HR and employees (authorization handled in controller)
    Route::get('payrolls/{payroll}/payslip', [PayrollController::class, 'payslip'])->name('payrolls.payslip');
    Route::get('payrolls/{payroll}/payslip/download', [PayrollController::class, 'payslipDownload'])->name('payrolls.payslip.download');

    // Payslip Management
    Route::middleware('can:view payslips')->group(function () {
        Route::get('payslips/{payrollDetail}', [App\Http\Controllers\PayslipController::class, 'show'])
            ->name('payslips.show');
        Route::get('payslips/{payrollDetail}/download', [App\Http\Controllers\PayslipController::class, 'download'])
            ->name('payslips.download')
            ->middleware('can:download payslips');
        Route::post('payslips/{payrollDetail}/email', [App\Http\Controllers\PayslipController::class, 'email'])
            ->name('payslips.email')
            ->middleware('can:email payslip');
        Route::post('payrolls/{payroll}/email-all-payslips', [App\Http\Controllers\PayslipController::class, 'emailAll'])
            ->name('payslips.email-all')
            ->middleware('can:email all payslips');
        Route::get('payrolls/{payroll}/download-all-payslips', [App\Http\Controllers\PayslipController::class, 'downloadAll'])
            ->name('payslips.download-all')
            ->middleware('can:download all payslips');
        Route::post('payrolls/bulk-email-approved', [App\Http\Controllers\PayslipController::class, 'bulkEmailApproved'])
            ->name('payslips.bulk-email-approved')
            ->middleware('can:email all payslips');
        Route::post('payrolls/{payroll}/email-individual', [App\Http\Controllers\PayslipController::class, 'emailIndividual'])
            ->name('payslips.email-individual')
            ->middleware('can:email payslip');
    });

    // Employee's own payslips
    Route::middleware('can:view own payslips')->group(function () {
        Route::get('my-payslips', [App\Http\Controllers\PayslipController::class, 'myPayslips'])
            ->name('my-payslips');
    });

    // Cash Advance Management
    Route::middleware(['auth', 'verified'])->group(function () {
        // AJAX route for checking employee eligibility - must come before resource routes
        Route::get('cash-advances/check-eligibility', [CashAdvanceController::class, 'checkEligibility'])
            ->name('cash-advances.check-eligibility');

        // AJAX route for getting employee payroll periods
        Route::post('cash-advances/employee-periods', [CashAdvanceController::class, 'getEmployeePayrollPeriods'])
            ->name('cash-advances.employee-periods');

        // AJAX route for getting employee pay schedule
        Route::post('cash-advances/employee-schedule', [CashAdvanceController::class, 'getEmployeePaySchedule'])
            ->name('cash-advances.employee-schedule');

        // AJAX route for checking employee active cash advances
        Route::post('cash-advances/check-active', [CashAdvanceController::class, 'checkEmployeeActiveAdvances'])
            ->name('cash-advances.check-active');

        // Cash advance summary generation
        Route::post('cash-advances/generate-summary', [CashAdvanceController::class, 'generateSummary'])
            ->name('cash-advances.generate-summary')
            ->middleware('can:view cash advances');

        Route::resource('cash-advances', CashAdvanceController::class);

        // Additional cash advance routes
        Route::post('cash-advances/{cashAdvance}/approve', [CashAdvanceController::class, 'approve'])
            ->name('cash-advances.approve')
            ->middleware('can:approve cash advances');
        Route::post('cash-advances/{cashAdvance}/reject', [CashAdvanceController::class, 'reject'])
            ->name('cash-advances.reject')
            ->middleware('can:approve cash advances');
    });

    // Paid Leave Management
    Route::middleware(['auth', 'verified'])->group(function () {
        // AJAX route for checking employee eligibility - must come before resource routes
        Route::get('paid-leaves/check-eligibility', [PaidLeaveController::class, 'checkEligibility'])
            ->name('paid-leaves.check-eligibility');

        // AJAX route for getting employee payroll periods
        Route::post('paid-leaves/employee-periods', [PaidLeaveController::class, 'getEmployeePayrollPeriods'])
            ->name('paid-leaves.employee-periods');

        // AJAX route for getting employee pay schedule
        Route::post('paid-leaves/employee-schedule', [PaidLeaveController::class, 'getEmployeePaySchedule'])
            ->name('paid-leaves.employee-schedule');

        // AJAX route for checking employee active paid leaves
        Route::post('paid-leaves/check-active', [PaidLeaveController::class, 'checkEmployeeActiveLeaves'])
            ->name('paid-leaves.check-active');

        // Paid leave summary generation
        Route::post('paid-leaves/generate-summary', [PaidLeaveController::class, 'generateSummary'])
            ->name('paid-leaves.generate-summary')
            ->middleware('can:view paid leaves');

        // Get employee leave balances
        Route::post('paid-leaves/employee-balances', [PaidLeaveController::class, 'getEmployeeLeaveBalances'])
            ->name('paid-leaves.employee-balances');

        // Get employee daily rate
        Route::post('paid-leaves/employee-daily-rate', [PaidLeaveController::class, 'getEmployeeDailyRate'])
            ->name('paid-leaves.employee-daily-rate');

        Route::resource('paid-leaves', PaidLeaveController::class)->parameters([
            'paid-leaves' => 'paidLeave'
        ]);

        // Additional paid leave routes
        Route::post('paid-leaves/{paidLeave}/approve', [PaidLeaveController::class, 'approve'])
            ->name('paid-leaves.approve')
            ->middleware('can:approve paid leaves');
        Route::post('paid-leaves/{paidLeave}/reject', [PaidLeaveController::class, 'reject'])
            ->name('paid-leaves.reject')
            ->middleware('can:approve paid leaves');
    });

    // DTR (Daily Time Record) Management
    Route::middleware('can:view time logs')->group(function () {
        // DTR Management Routes
        Route::prefix('dtr')->name('dtr.')->middleware('can:view time logs')->group(function () {
            Route::get('/', [DTRController::class, 'index'])->name('index');
            Route::get('/test-create', [DTRController::class, 'testCreate'])->name('test-create'); // Debug route
            Route::get('/debug/{id}', function ($id) {
                try {
                    $dtrRaw = DB::table('d_t_r_s')->where('id', $id)->first();
                    $employee = DB::table('employees')->where('id', $dtrRaw->employee_id)->first();
                    $user = DB::table('users')->where('id', $employee->user_id)->first();

                    return response()->json([
                        'dtr_raw' => $dtrRaw,
                        'employee' => $employee,
                        'user' => $user,
                        'status' => 'success'
                    ]);
                } catch (\Exception $e) {
                    return response()->json([
                        'error' => $e->getMessage(),
                        'status' => 'error'
                    ]);
                }
            })->name('debug'); // Debug route
            Route::get('/period-management', [DTRController::class, 'periodManagement'])->name('period-management');
            Route::post('/create', [DTRController::class, 'create'])->name('create');
            Route::post('/create-instant', [DTRController::class, 'createInstant'])->name('create-instant');
            Route::post('/store', [DTRController::class, 'store'])->name('store');

            // DTR Import routes
            Route::get('/import', [DTRController::class, 'importForm'])
                ->name('import')
                ->middleware('can:import time logs');
            Route::post('/import', [DTRController::class, 'import'])
                ->name('import.process')
                ->middleware('can:import time logs');
            Route::get('/export/template', [DTRController::class, 'exportTemplate'])
                ->name('export-template')
                ->middleware('can:import time logs');

            Route::get('/{dtr}', [DTRController::class, 'show'])->name('show');
            Route::get('/{dtr}/edit', [DTRController::class, 'edit'])->name('edit');
            Route::put('/{dtr}', [DTRController::class, 'update'])->name('update');
            Route::delete('/{dtr}', [DTRController::class, 'destroy'])->name('destroy');
            Route::get('/{dtr}/pdf', [DTRController::class, 'pdf'])->name('pdf');
        });

        // DTR main routes (old system - keep for compatibility)
        Route::get('time-logs', [TimeLogController::class, 'index'])->name('time-logs.index');
        Route::get('time-logs/dtr-batch/{dtrId}', [TimeLogController::class, 'showDTRBatch'])->name('time-logs.dtr-batch');
        Route::delete('time-logs/dtr-batch/{dtrId}', [TimeLogController::class, 'destroyDTRBatch'])->name('time-logs.destroy-dtr-batch');
        Route::get('time-logs/dtr-batch/{dtrId}/payroll', [TimeLogController::class, 'showPayroll'])->name('time-logs.dtr-batch-payroll');
        Route::get('time-logs/create', [TimeLogController::class, 'create'])->name('time-logs.create');
        Route::get('time-logs/create-bulk', [TimeLogController::class, 'createBulk'])->name('time-logs.create-bulk');
        Route::get('time-logs/create-bulk/employee/{employee_id}', [TimeLogController::class, 'createBulkForEmployee'])->name('time-logs.create-bulk-employee');
        Route::post('time-logs/store-bulk', [TimeLogController::class, 'storeBulk'])->name('time-logs.store-bulk');
        Route::post('time-logs', [TimeLogController::class, 'store'])->name('time-logs.store');
        Route::get('time-logs/{timeLog}', [TimeLogController::class, 'show'])->name('time-logs.show');
        Route::get('time-logs/{timeLog}/edit', [TimeLogController::class, 'edit'])->name('time-logs.edit');
        Route::put('time-logs/{timeLog}', [TimeLogController::class, 'update'])->name('time-logs.update');
        Route::delete('time-logs/{timeLog}', [TimeLogController::class, 'destroy'])->name('time-logs.destroy');
        Route::get('time-logs/{employee}/dtr', [TimeLogController::class, 'showDTR'])->name('time-logs.show-dtr');
        Route::get('time-logs/{employee}/simple-dtr', [TimeLogController::class, 'simpleDTR'])->name('time-logs.simple-dtr');
        Route::post('time-logs/update-time-entry', [TimeLogController::class, 'updateTimeEntry'])->name('time-logs.update-time-entry');
        Route::post('time-logs/recalculate-employee', [TimeLogController::class, 'recalculateTimeLogsForEmployee'])->name('time-logs.recalculate-employee');
        Route::post('time-logs/{timeLog}/recalculate', [TimeLogController::class, 'recalculateTimeLog'])->name('time-logs.recalculate');

        // DTR Import routes
        Route::get('time-logs/import/form', [TimeLogController::class, 'importForm'])
            ->name('time-logs.import-form')
            ->middleware('can:import time logs');
        Route::post('time-logs/import', [TimeLogController::class, 'import'])
            ->name('time-logs.import')
            ->middleware('can:import time logs');
        Route::get('time-logs/export/template', [TimeLogController::class, 'exportTemplate'])
            ->name('time-logs.export-template')
            ->middleware('can:import time logs');
    });

    // Settings routes
    Route::middleware('can:edit settings')->group(function () {
        // System Settings (System Admin only)
        Route::get('system-settings', [SystemSettingsController::class, 'index'])->name('system-settings.index');
        Route::put('system-settings', [SystemSettingsController::class, 'update'])->name('system-settings.update');
        Route::post('system-settings/toggle-theme', [SystemSettingsController::class, 'toggleTheme'])->name('system-settings.toggle-theme');

        // Pay Schedule Settings (New Multiple Schedule System)
        Route::resource('settings/pay-schedules', \App\Http\Controllers\Settings\PayScheduleController::class)
            ->names([
                'index' => 'settings.pay-schedules.index',
                'create' => 'settings.pay-schedules.create',
                'store' => 'settings.pay-schedules.store',
                'show' => 'settings.pay-schedules.show',
                'edit' => 'settings.pay-schedules.edit',
                'update' => 'settings.pay-schedules.update',
                'destroy' => 'settings.pay-schedules.destroy'
            ]);

        // Additional pay schedule routes
        Route::patch('settings/pay-schedules/{paySchedule}/toggle', [\App\Http\Controllers\Settings\PayScheduleController::class, 'toggle'])
            ->name('settings.pay-schedules.toggle');

        // Legacy Payroll Schedule Settings (keep for backward compatibility)
        Route::get('payroll-schedule-settings', [PayrollScheduleSettingsController::class, 'index'])->name('payroll-schedule-settings.index');
        Route::get('payroll-schedule-settings/{payrollScheduleSetting}/edit', [PayrollScheduleSettingsController::class, 'edit'])->name('payroll-schedule-settings.edit');
        Route::put('payroll-schedule-settings/{payrollScheduleSetting}', [PayrollScheduleSettingsController::class, 'update'])->name('payroll-schedule-settings.update');

        // Payroll Rate Configurations  
        Route::resource('settings/rate-multiplier', \App\Http\Controllers\PayrollRateConfigurationController::class)->parameters([
            'rate-multiplier' => 'payrollRateConfiguration'
        ])->names([
            'index' => 'payroll-rate-configurations.index',
            'create' => 'payroll-rate-configurations.create',
            'store' => 'payroll-rate-configurations.store',
            'show' => 'payroll-rate-configurations.show',
            'edit' => 'payroll-rate-configurations.edit',
            'update' => 'payroll-rate-configurations.update',
            'destroy' => 'payroll-rate-configurations.destroy'
        ]);
        Route::post('settings/rate-multiplier/initialize-defaults', [\App\Http\Controllers\PayrollRateConfigurationController::class, 'initializeDefaults'])->name('payroll-rate-configurations.initialize-defaults');
        Route::post('settings/rate-multiplier/{payrollRateConfiguration}/toggle', [\App\Http\Controllers\PayrollRateConfigurationController::class, 'toggle'])->name('payroll-rate-configurations.toggle');

        // General Settings
        Route::get('settings/payroll', [SettingsController::class, 'payroll'])->name('settings.payroll');
        Route::post('settings/payroll', [SettingsController::class, 'updatePayroll'])->name('settings.payroll.update');
        Route::post('settings/payroll/test', [SettingsController::class, 'testAutoPayroll'])->name('settings.payroll.test');

        // Employee Settings
        Route::get('settings/employee', [SettingsController::class, 'employeeSettings'])->name('settings.employee');
        Route::post('settings/employee', [SettingsController::class, 'updateEmployeeSettings'])->name('settings.employee.update');
        Route::get('settings/employee/next-number', [SettingsController::class, 'getNextEmployeeNumber'])->name('settings.employee.next-number');
        Route::post('employees/check-duplicate', [EmployeeController::class, 'checkDuplicate'])->name('employees.check-duplicate');
        Route::post('employees/calculate-deductions', [EmployeeController::class, 'calculateDeductions'])->name('employees.calculate-deductions');
        Route::post('settings/time-schedules', [SettingsController::class, 'storeTimeSchedule'])->name('settings.time-schedules.store');
        Route::post('settings/day-schedules', [SettingsController::class, 'storeDaySchedule'])->name('settings.day-schedules.store');
    });

    // Employee's own time logs
    Route::middleware('can:view own time logs')->group(function () {
        Route::get('my-time-logs', [TimeLogController::class, 'myTimeLogs'])->name('my-time-logs');
        Route::post('my-time-logs', [TimeLogController::class, 'storeMyTimeLog'])->name('my-time-logs.store');
    });

    // Employee Payslips
    Route::middleware('can:view own payslips')->group(function () {
        Route::get('my-payslips', [PayrollController::class, 'm yPayslips'])->name('payrolls.my-payslips');
    });

    // Leave Requests
    Route::middleware('can:view own leave requests')->group(function () {});

    // User Management (Super Admin and System Administrator)
    Route::middleware('role:Super Admin|System Administrator')->group(function () {
        // User summary generation
        Route::post('users/generate-summary', [\App\Http\Controllers\UserController::class, 'generateSummary'])
            ->name('users.generate-summary');

        Route::resource('users', \App\Http\Controllers\UserController::class);

        // Company Management (Super Admin only)
        Route::middleware('role:Super Admin')->group(function () {
            Route::post('companies/{company}/toggle', [\App\Http\Controllers\CompanyController::class, 'toggle'])
                ->name('companies.toggle');
            Route::resource('companies', \App\Http\Controllers\CompanyController::class);
        });
    });
});

require __DIR__ . '/auth.php';
require __DIR__ . '/settings.php';
