<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Payroll Details: {{ $payroll->payroll_number }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    {{ $payroll->period_start->format('M d') }} - {{ $payroll->period_end->format('M d, Y') }}
                </p>
            </div>
            <div class="flex space-x-2">
                @can('edit payrolls')
                @if($payroll->canBeEdited())
                <a href="{{ route('payrolls.edit', $payroll) }}" 
                   class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Edit Payroll
                </a>
                @endif
                @endcan
                <a href="{{ route('payrolls.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Back to Payrolls
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-80 flex justify-center items-center z-50" style="display: none; backdrop-filter: blur(5px); opacity: 0; transition: opacity 0.2s ease-in-out;">
        <div class="bg-white p-8 rounded-xl text-center max-w-md mx-4 shadow-2xl">
            <div class="loading-spinner mx-auto mb-4"></div>
            <div class="text-lg font-semibold text-gray-800 mb-2" id="loadingText">Sending Email...</div>
            <div class="text-sm text-gray-600" id="loadingSubtext">Please wait while we process your request.</div>
        </div>
    </div>

    <div class="py-6">
        <div class="max-w-9xl mx-auto sm:px-6 lg:px-8 space-y-6">
               
            <!-- Payroll Summary -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-row gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg flex-1 h-20 flex flex-col justify-center text-center">
                            @php
                                $totalBasicPay = 0; // This will be updated by JavaScript to match Basic column exactly
                            @endphp
                            <div class="text-2xl font-bold text-blue-600" id="totalBasicDisplay">₱{{ number_format($totalBasicPay, 2) }}</div>
                            <div class="text-sm text-blue-800">Total Regular</div>
                        </div>
                        <div class="bg-yellow-50 p-4 rounded-lg flex-1 h-20 flex flex-col justify-center text-center">
                            @php
                                $totalHolidayPay = 0; // This will be updated by JavaScript to match Holiday column exactly
                            @endphp
                            <div class="text-2xl font-bold text-yellow-600" id="totalHolidayDisplay">₱{{ number_format($totalHolidayPay, 2) }}</div>
                            <div class="text-sm text-yellow-800">Total Holiday</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg flex-1 h-20 flex flex-col justify-center text-center">
                            @php
                                $totalRestDayPay = 0;
                                
                                if ($payroll->status === 'draft') {
                                    // DRAFT: Use dynamic calculation from payBreakdownByEmployee
                                    foreach($payroll->payrollDetails as $detail) {
                                        $restDayPay = $payBreakdownByEmployee[$detail->employee_id]['rest_day_pay'] ?? 0;
                                        $totalRestDayPay += $restDayPay;
                                    }
                                } else {
                                    // PROCESSING/APPROVED: Use stored static data from database
                                    $totalRestDayPay = $payroll->payrollDetails->sum('rest_day_pay');
                                }
                            @endphp
                            <div class="text-2xl font-bold text-cyan-600" id="totalRestDisplay">₱{{ number_format($totalRestDayPay, 2) }}</div>
                            <div class="text-sm text-gray-800">Total Rest</div>
                        </div>
                        <div class="bg-green-50 p-4 rounded-lg flex-1 h-20 flex flex-col justify-center text-center">
                            @php
                                $totalGrossPay = 0; // This will be updated by JavaScript to match Gross Pay column exactly
                            @endphp
                            <div class="text-2xl font-bold text-green-600" id="totalGrossDisplay">₱{{ number_format($totalGrossPay, 2) }}</div>
                            <div class="text-sm text-green-800">Total Gross</div>
                        </div>
                      
                        <div class="bg-red-50 p-4 rounded-lg flex-1 h-20 flex flex-col justify-center text-center">
                            @php
                                // Calculate actual total deductions using the same logic as employee details
                                $actualTotalDeductions = 0;
                                
                                foreach($payroll->payrollDetails as $detail) {
                                    $detailDeductionTotal = 0;
                                    
                                    // Check if this payroll uses dynamic calculations
                                    if(isset($isDynamic) && $isDynamic && isset($deductionSettings) && $deductionSettings->isNotEmpty()) {
                                        // Use dynamic calculation like in employee details section
                                        foreach($deductionSettings as $setting) {
                                            $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->basic_pay ?? 0;
                                            
                                            // Calculate the SAME gross pay as in the Gross Pay column for this employee
                                            $basicPayForGross = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->basic_pay ?? 0;
                                            $holidayPayForGross = $payBreakdownByEmployee[$detail->employee_id]['holiday_pay'] ?? $detail->holiday_pay ?? 0;
                                            $restPayForGross = 0;
                                            $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                            if (isset($employeeBreakdown['rest_day'])) {
                                                $restBreakdown = $employeeBreakdown['rest_day'];
                                                $restPayForGross = ($restBreakdown['regular_hours'] ?? 0) * ($detail->hourly_rate ?? 0) * 1.3; // Use calculated hourly rate
                                                $restPayForGross += ($restBreakdown['overtime_hours'] ?? 0) * ($detail->hourly_rate ?? 0) * 1.69; // Use calculated hourly rate
                                            }
                                            $overtimePay = $detail->overtime_pay ?? 0;
                                            
                                            // Calculate DYNAMIC allowances (same logic as Allowances column)
                                            $allowances = 0;
                                            if (isset($allowanceSettings) && $allowanceSettings->isNotEmpty()) {
                                                foreach($allowanceSettings as $allowanceSetting) {
                                                    // Check if this allowance setting applies to this employee's benefit status
                                                    if (!$allowanceSetting->appliesTo($detail->employee)) {
                                                        continue; // Skip this setting for this employee
                                                    }
                                                    
                                                    $calculatedAllowanceAmount = 0;
                                                    if($allowanceSetting->calculation_type === 'percentage') {
                                                        $calculatedAllowanceAmount = ($basicPay * $allowanceSetting->rate_percentage) / 100;
                                                    } elseif($allowanceSetting->calculation_type === 'fixed_amount') {
                                                        $calculatedAllowanceAmount = $allowanceSetting->fixed_amount;
                                                    } elseif($allowanceSetting->calculation_type === 'automatic') {
                                                        // Use the model's calculateAmount method for automatic calculation
                                                        $breakdownData = ['basic' => [], 'holiday' => []];
                                                        $calculatedAllowanceAmount = $allowanceSetting->calculateAmount($basicPay, $detail->employee->daily_rate, null, $detail->employee, $breakdownData);
                                                        
                                                        // Apply frequency-based calculation for daily allowances
                                                        if ($allowanceSetting->frequency === 'daily') {
                                                            // Calculate actual working days for this employee
                                                            $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                                            $workingDays = 0;
                                                            
                                                            // Count working days from DTR data
                                                            if (isset($employeeBreakdown['regular_workday'])) {
                                                                $regularBreakdown = $employeeBreakdown['regular_workday'];
                                                                $workingDays += ($regularBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                            }
                                                            if (isset($employeeBreakdown['special_holiday'])) {
                                                                $specialBreakdown = $employeeBreakdown['special_holiday'];
                                                                $workingDays += ($specialBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                            }
                                                            if (isset($employeeBreakdown['regular_holiday'])) {
                                                                $regularHolidayBreakdown = $employeeBreakdown['regular_holiday'];
                                                                $workingDays += ($regularHolidayBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                            }
                                                            if (isset($employeeBreakdown['rest_day'])) {
                                                                $restBreakdown = $employeeBreakdown['rest_day'];
                                                                $workingDays += ($restBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                            }
                                                            
                                                            // Apply max days limit if set
                                                            $maxDays = $allowanceSetting->max_days_per_period ?? $workingDays;
                                                            $applicableDays = min($workingDays, $maxDays);
                                                            
                                                            $calculatedAllowanceAmount = $allowanceSetting->fixed_amount * $applicableDays;
                                                        }
                                                    } elseif($allowanceSetting->calculation_type === 'daily_rate_multiplier') {
                                                        $dailyRate = $detail->employee->daily_rate ?? 0;
                                                        $multiplier = $allowanceSetting->multiplier ?? 1;
                                                        $calculatedAllowanceAmount = $dailyRate * $multiplier;
                                                    }
                                                    
                                                    // Apply minimum and maximum limits
                                                    if ($allowanceSetting->minimum_amount && $calculatedAllowanceAmount < $allowanceSetting->minimum_amount) {
                                                        $calculatedAllowanceAmount = $allowanceSetting->minimum_amount;
                                                    }
                                                    if ($allowanceSetting->maximum_amount && $calculatedAllowanceAmount > $allowanceSetting->maximum_amount) {
                                                        $calculatedAllowanceAmount = $allowanceSetting->maximum_amount;
                                                    }
                                                    
                                                    // Apply distribution method for summary calculation (to match individual columns)
                                                    if ($calculatedAllowanceAmount > 0) {
                                                        $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                                        $distributedAmount = $allowanceSetting->calculateDistributedAmount(
                                                            $calculatedAllowanceAmount,
                                                            $payroll->period_start,
                                                            $payroll->period_end,
                                                            $employeePaySchedule,
                                                            $payroll->pay_schedule ?? null
                                                        );
                                                        $allowances += $distributedAmount;
                                                    }
                                                }
                                            } else {
                                                // Fallback to stored value if no active settings
                                                $allowances = $detail->allowances ?? 0;
                                            }
                                            
                                            // Calculate DYNAMIC bonuses (same logic as Bonuses column)
                                            $bonuses = 0;
                                            if (isset($bonusSettings) && $bonusSettings->isNotEmpty()) {
                                                foreach($bonusSettings as $bonusSetting) {
                                                    // Check if this bonus setting applies to this employee's benefit status
                                                    if (!$bonusSetting->appliesTo($detail->employee)) {
                                                        continue; // Skip this setting for this employee
                                                    }
                                                    
                                                    $calculatedBonusAmount = 0;
                                                    if($bonusSetting->calculation_type === 'percentage') {
                                                        $calculatedBonusAmount = ($basicPay * $bonusSetting->rate_percentage) / 100;
                                                    } elseif($bonusSetting->calculation_type === 'fixed_amount') {
                                                        $calculatedBonusAmount = $bonusSetting->fixed_amount;
                                                    } elseif($bonusSetting->calculation_type === 'automatic') {
                                                        // Use the model's calculateAmount method for automatic calculation
                                                        // IMPORTANT: Use proper breakdown data for 13th month calculation (will be calculated later in individual columns)
                                                        // For now, use empty breakdown - the correct calculation will happen in the BONUSES column
                                                        $breakdownData = ['basic' => [], 'holiday' => []];
                                                        $calculatedBonusAmount = $bonusSetting->calculateAmount($basicPay, $detail->employee->daily_rate, null, $detail->employee, $breakdownData);
                                                    }
                                                    
                                                    // Apply distribution method for summary calculation (to match individual columns)
                                                    if ($calculatedBonusAmount > 0) {
                                                        $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                                        $distributedAmount = $bonusSetting->calculateDistributedAmount(
                                                            $calculatedBonusAmount,
                                                            $payroll->period_start,
                                                            $payroll->period_end,
                                                            $employeePaySchedule,
                                                            $payroll->pay_schedule ?? null
                                                        );
                                                        $bonuses += $distributedAmount;
                                                    }
                                                }
                                            } else {
                                                // Fallback to stored value if no active settings
                                                $bonuses = $detail->bonuses ?? 0;
                                            }
                                            
                                            // Calculate DYNAMIC incentives (same logic as Incentives column)
                                            $incentives = 0;
                                            if (isset($incentiveSettings) && $incentiveSettings->isNotEmpty()) {
                                                foreach($incentiveSettings as $incentiveSetting) {
                                                    // Check if this incentive setting applies to this employee's benefit status
                                                    if (!$incentiveSetting->appliesTo($detail->employee)) {
                                                        continue; // Skip this setting for this employee
                                                    }
                                                    
                                                    // Check if this incentive requires perfect attendance
                                                    if ($incentiveSetting->requires_perfect_attendance) {
                                                        // Check if employee has perfect attendance for this payroll period
                                                        if (!$incentiveSetting->hasPerfectAttendance($detail->employee, $payroll->period_start, $payroll->period_end)) {
                                                            continue; // Skip this incentive if perfect attendance not met
                                                        }
                                                    }
                                                    
                                                    $calculatedIncentiveAmount = $incentiveSetting->fixed_amount ?? 0;
                                                    
                                                    // Apply distribution method for summary calculation (to match individual columns)
                                                    if ($calculatedIncentiveAmount > 0) {
                                                        $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                                        $distributedAmount = $incentiveSetting->calculateDistributedAmount(
                                                            $calculatedIncentiveAmount,
                                                            $payroll->period_start,
                                                            $payroll->period_end,
                                                            $employeePaySchedule,
                                                            $payroll->pay_schedule ?? null
                                                        );
                                                        $incentives += $distributedAmount;
                                                    }
                                                }
                                            } else {
                                                // Fallback to stored value if no active settings
                                                $incentives = $detail->incentives ?? 0;
                                            }
                                            
                                            // Calculate total gross pay like in the Gross Pay column
                                            $calculatedGrossPayForSummary = $basicPayForGross + $holidayPayForGross + $restPayForGross + $overtimePay + $allowances + $bonuses + $incentives;
                                            
                                            // Calculate taxable income for this detail (same logic as in detail columns)
                                            $taxableIncomeForSummary = $basicPayForGross + $holidayPayForGross + $restPayForGross + $overtimePay;
                                            if (isset($allowanceBonusSettings) && $allowanceBonusSettings->isNotEmpty()) {
                                                foreach($allowanceBonusSettings as $abSetting) {
                                                    // Check if this allowance/bonus setting applies to this employee's benefit status
                                                    if (!$abSetting->appliesTo($detail->employee)) {
                                                        continue; // Skip this setting for this employee
                                                    }
                                                    
                                                    if ($abSetting->is_taxable) {
                                                        $calculatedAllowanceAmount = $abSetting->calculateAmount(
                                                            $basicPay, // Use calculated basic pay for the period
                                                            $detail->employee->daily_rate ?? 0, // dailyRate
                                                            null, // workingDays (will be calculated inside if needed)
                                                            $detail->employee // employee object
                                                        );
                                                        $taxableIncomeForSummary += $calculatedAllowanceAmount;
                                                    }
                                                }
                                            }
                                            
                                            // Auto-detect pay frequency from payroll period using dynamic pay schedule settings
                                            $payFrequency = \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                                                $payroll->period_start,
                                                $payroll->period_end
                                            );
                                            
                                            $calculatedAmount = $setting->calculateDeduction(
                                                $basicPay, 
                                                $overtimePay, 
                                                $bonuses, 
                                                $allowances, 
                                                $calculatedGrossPayForSummary, // grossPay
                                                $taxableIncomeForSummary, // taxableIncome
                                                null, // netPay
                                                $detail->employee->calculateMonthlyBasicSalary($payroll->period_start, $payroll->period_end), // monthlyBasicSalary - DYNAMIC
                                                $payFrequency // payFrequency
                                            );
                                            
                                            // Apply deduction distribution logic to match backend calculations
                                            if ($calculatedAmount > 0) {
                                                $calculatedAmount = $setting->calculateDistributedAmount(
                                                    $calculatedAmount,
                                                    $payroll->period_start,
                                                    $payroll->period_end,
                                                    $detail->employee->pay_schedule ?? $payFrequency,
                                                    $payroll->pay_schedule ?? null
                                                );
                                            }
                                            
                                            $detailDeductionTotal += $calculatedAmount;
                                        }
                                    } else {
                                        // Use stored values for non-dynamic payrolls (excluding late/undertime as they're already accounted for in hours)
                                        $detailDeductionTotal = $detail->sss_contribution + $detail->philhealth_contribution + $detail->pagibig_contribution + $detail->withholding_tax + $detail->cash_advance_deductions + $detail->other_deductions;
                                    }
                                    
                                    $actualTotalDeductions += $detailDeductionTotal;
                                }
                                
                                // Debug output (remove this after fixing)
                                $firstDetail = $payroll->payrollDetails->first();
                                if ($firstDetail) {
                                    $debugComponents = [
                                        'SSS' => $firstDetail->sss_contribution,
                                        'PhilHealth' => $firstDetail->philhealth_contribution,
                                        'PagIBIG' => $firstDetail->pagibig_contribution,
                                        'isDynamic' => isset($isDynamic) ? ($isDynamic ? 'Y' : 'N') : 'NULL',
                                        'hasSettings' => isset($deductionSettings) ? $deductionSettings->count() : 'NULL',
                                    ];
                                }
                            @endphp
                            <div class="text-2xl font-bold text-red-600" id="totalDeductionsDisplay">₱{{ number_format($actualTotalDeductions, 2) }}</div>
                            <div class="text-sm text-red-800">Total Deductions</div>
                            
                           
                        </div>
                        <div class="bg-purple-50 p-4 rounded-lg flex-1 h-20 flex flex-col justify-center text-center">
                            @php
                                $correctNetPay = 0; // This will be updated by JavaScript to match Net Pay column exactly
                            @endphp
                            <div class="text-2xl font-bold text-purple-600" id="totalNetDisplay">₱{{ number_format($correctNetPay, 2) }}</div>
                            <div class="text-sm text-purple-800">Total Net</div>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-row ">
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900">Status</h4>
                            <div class="mt-1 flex items-center space-x-2">
                                @if($payroll->is_paid)
                                    {{-- Show both Approved and Paid when payroll is paid --}}
                                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                        Approved
                                    </span>
                                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                        Paid
                                    </span>
                                @else
                                    {{-- Show only the current status when not paid --}}
                                    <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full 
                                        {{ $payroll->status == 'approved' ? 'bg-blue-100 text-blue-800' : 
                                            ($payroll->status == 'processing' ? 'bg-yellow-100 text-yellow-800' : 
                                             ($payroll->status == 'cancelled' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800')) }}">
                                        {{ ucfirst($payroll->status) }}
                                    </span>
                                @endif
                                @if(isset($isDynamic))
                                    @if($isDynamic)
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-blue-50 text-blue-700">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                            Dynamic
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-50 text-gray-700">
                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                            </svg>
                                            Locked
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900">Payroll Frequency</h4>
                            @php
                                // Get the first employee's pay schedule to determine the frequency display
                                $firstEmployee = $payroll->payrollDetails->first()?->employee;
                                $paySchedule = $firstEmployee?->pay_schedule ?? 'weekly';
                                
                                // Pay frequency display logic
                                $payFrequencyDisplay = '';
                                $periodStart = \Carbon\Carbon::parse($payroll->period_start);
                                $periodEnd = \Carbon\Carbon::parse($payroll->period_end);
                                
                                switch ($paySchedule) {
                                    case 'semi_monthly':
                                    case 'semi-monthly':
                                    case 'SEMI-1':
                                    case 'SEMI-2': 
                                    case 'SEMI-3':
                                        // Determine cutoff based on actual schedule configuration
                                        $cutoff = '1st'; // default
                                        
                                        // Try to get the actual schedule to determine correct cutoff
                                        $actualSchedule = null;
                                        if ($payroll->pay_schedule && $payroll->pay_schedule !== 'semi_monthly') {
                                            // New system - find by schedule name (e.g., SEMI-1, SEMI-2)
                                            $actualSchedule = \App\Models\PaySchedule::where('name', $payroll->pay_schedule)->first();
                                            if ($actualSchedule && isset($actualSchedule->cutoff_periods) && count($actualSchedule->cutoff_periods) >= 2) {
                                                // Check which period this payroll falls into
                                                $periods = $actualSchedule->cutoff_periods;
                                                $startDay = $periodStart->day;
                                                $endDay = $periodEnd->day;
                                                
                                                // Check if this matches the first period configuration
                                                $firstPeriodStart = is_numeric($periods[0]['start_day']) ? (int)$periods[0]['start_day'] : 1;
                                                $firstPeriodEnd = is_numeric($periods[0]['end_day']) ? (int)$periods[0]['end_day'] : 15;
                                                
                                                // Check if this matches the second period configuration  
                                                $secondPeriodStart = is_numeric($periods[1]['start_day']) ? (int)$periods[1]['start_day'] : 16;
                                                
                                                // Determine cutoff based on period start day matching configuration
                                                // Handle cross-month periods properly (e.g., SEMI-2: 21-5 and 6-20)
                                                if ($firstPeriodEnd < $firstPeriodStart) {
                                                    // First period is cross-month (e.g., 21 to 5)
                                                    if ($startDay >= $firstPeriodStart || $startDay <= $firstPeriodEnd) {
                                                        $cutoff = '1st';
                                                    } else {
                                                        $cutoff = '2nd';
                                                    }
                                                } else {
                                                    // Standard same-month periods
                                                    if ($startDay >= $secondPeriodStart || ($startDay > $firstPeriodEnd)) {
                                                        $cutoff = '2nd';
                                                    } else {
                                                        $cutoff = '1st';
                                                    }
                                                }
                                            }
                                        } else {
                                            // Legacy system - use simple day check
                                            $cutoff = $periodStart->day <= 15 ? '1st' : '2nd';
                                        }
                                        
                                        $payFrequencyDisplay = "Semi-Monthly - {$cutoff} Cutoff";
                                        break;
                                        
                                    case 'monthly':
                                    case 'MONTH-1':
                                        $monthName = $periodStart->format('F');
                                        $payFrequencyDisplay = "Monthly - {$monthName}";
                                        break;
                                        
                                    case 'weekly':
                                    case 'WEEK-1':
                                        // Use same simple calculation as schedule overview
                                        $dayOfMonth = $periodStart->day;
                                        $weekNumber = (int) ceil($dayOfMonth / 7);
                                        
                                        $weekOrdinal = match($weekNumber) {
                                            1 => '1st',
                                            2 => '2nd', 
                                            3 => '3rd',
                                            4 => '4th',
                                            default => $weekNumber . 'th'
                                        };
                                        $payFrequencyDisplay = "Weekly - {$weekOrdinal}";
                                        break;
                                        
                                    case 'daily':
                                    case 'DAILY-1':
                                        // Use day name like schedule overview
                                        $dayName = $periodStart->format('l'); // Monday, Tuesday, etc.
                                        $payFrequencyDisplay = "Daily - {$dayName}";
                                        break;
                                        
                                    default:
                                        $payFrequencyDisplay = ucfirst(str_replace('_', '-', $paySchedule));
                                        break;
                                }
                            @endphp
                            <p class="mt-1 text-sm text-gray-600">{{ $payFrequencyDisplay }}</p>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900">Type</h4>
                            <p class="mt-1 text-sm text-gray-600">{{ ucfirst(str_replace('_', ' ', $payroll->payroll_type)) }}</p>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900">Payroll Period</h4>
                            <p class="mt-1 text-sm text-gray-600">{{ $payroll->period_start->format('M d') }} - {{ $payroll->period_end->format('M d, Y') }}</p>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900">Pay Date</h4>
                            <p class="mt-1 text-sm text-gray-600">{{ $payroll->pay_date->format('M d, Y') }}</p>
                        </div>
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-gray-900">Monthly Basic Pay</h4>
                            @php
                                // Calculate monthly basic pay for the current month using the Employee model method
                                $firstEmployee = $payroll->payrollDetails->first()?->employee;
                                $monthlyBasicPay = 0;
                                
                                if ($firstEmployee) {
                                    // Get current month's start and end dates for MBS calculation
                                    $currentMonth = \Carbon\Carbon::now();
                                    $currentMonthStart = $currentMonth->copy()->startOfMonth();
                                    $currentMonthEnd = $currentMonth->copy()->endOfMonth();
                                    
                                    // Use the Employee model's calculateMonthlyBasicSalary method with current month dates
                                    $monthlyBasicPay = $firstEmployee->calculateMonthlyBasicSalary($currentMonthStart, $currentMonthEnd);
                                }
                            @endphp
                            <p class="mt-1 text-sm text-gray-600">₱{{ number_format($monthlyBasicPay, 2) }}</p>
                        </div>
                    </div>

                    {{-- Payment Information Section --}}
                    @if($payroll->is_paid)
                    <div class="mt-6 bg-green-50 rounded-lg p-4">
                        <h4 class="text-sm font-medium text-green-900 mb-3 flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Payment Information
                        </h4>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                {{-- <p class="text-sm text-green-700">
                                    <span class="font-medium">Marked as paid on:</span><br>
                                    {{ $payroll->marked_paid_at->format('M d, Y g:i A') }}
                                </p> --}}
                                @if($payroll->markedPaidBy)
                                <p class="text-sm text-green-700 mt-1 ">
                                    <span class="font-medium">Marked by:</span>
                                    {{ $payroll->markedPaidBy->name }} on {{ $payroll->marked_paid_at->format('M d, Y g:i A') }}
                                </p>
                                @endif
                            </div>
                              @if($payroll->payment_proof_files && count($payroll->payment_proof_files) > 0)
                        <div >
                            <p class="text-sm font-medium text-green-900 mb-2">Payment Proof Files:</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                                @foreach($payroll->payment_proof_files as $file)
                                <a href="{{ asset('storage/' . $file['file_path']) }}" 
                                   target="_blank"
                                   class="flex items-center p-2 text-sm text-green-700 bg-white rounded border border-green-200 hover:bg-green-50">
                                    @if(in_array(strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png']))
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 002 2z"></path>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                    @endif
                                    <span class="truncate">{{ $file['original_name'] }}</span>
                                </a>
                                @endforeach
                            </div>
                        </div>
                        @endif
                            @if($payroll->payment_notes)
                            <div>
                                <p class="text-sm text-green-700">
                                    <span class="font-medium">Payment Notes:</span>
                                    {{ $payroll->payment_notes }}
                                </p>
                            </div>
                            @endif
                        </div>

                      
                    </div>
                    @endif

                   
{{-- 
                    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Created By</h4>
                            <p class="mt-1 text-sm text-gray-600">{{ $payroll->creator->name }} on {{ $payroll->created_at->format('M d, Y g:i A') }}</p>
                        </div>
                        @if($payroll->approver)
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Approved By</h4>
                            <p class="mt-1 text-sm text-gray-600">{{ $payroll->approver->name }} on {{ $payroll->approved_at->format('M d, Y g:i A') }}</p>
                        </div>
                        @endif
                    </div> --}}

                    <!-- Action Buttons -->
                    <div class="mt-6 flex space-x-3">
                        @can('process payrolls')
                        @if($payroll->status == 'draft')
                        @if(isset($schedule) && isset($employee))
                        {{-- Automation payroll - use unified process route --}}
                        <form method="POST" action="{{ route('payrolls.automation.process', ['schedule' => $schedule, 'id' => $employee]) }}" class="inline">
                            @csrf
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    onclick="return confirm('Submit this payroll for processing? This will save it to the database with locked data snapshots.')">
                                Submit for Processing
                            </button>
                        </form>
                        @else
                        {{-- Regular payroll - use standard process route --}}
                        <form method="POST" action="{{ route('payrolls.process', $payroll) }}" class="inline">
                            @csrf
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    onclick="return confirm('Submit this payroll for processing?')">
                                Submit for Processing
                            </button>
                        </form>
                        @endif
                        @endif
                        @endcan

                        <!-- View Payslip Button - Show only if not draft and not processing -->
                        @if($payroll->status != 'draft' && $payroll->status != 'processing')
                        <a href="{{ route('payrolls.payslip', $payroll) }}" 
                           target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            View Payslip
                        </a>
                        @endif

                        @can('approve payrolls')
                        @if($payroll->status == 'processing' && !auth()->user()->hasRole('HR Staff'))
                        @if(isset($schedule) && isset($employee))
                        {{-- Automation payroll - use unified approve route --}}
                        <form method="POST" action="{{ route('payrolls.automation.approve', ['schedule' => $schedule, 'id' => $employee]) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:bg-purple-700 active:bg-purple-900 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    onclick="return confirm('Approve this payroll?')">
                                Approve Payroll
                            </button>
                        </form>
                        @else
                        {{-- Regular payroll - use standard approve route --}}
                        <form method="POST" action="{{ route('payrolls.approve', $payroll) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:bg-purple-700 active:bg-purple-900 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    onclick="return confirm('Approve this payroll?')">
                                Approve Payroll
                            </button>
                        </form>
                        @endif
                        @endif
                        @endcan

                        @can('edit payrolls')
                        @if($payroll->status == 'processing')
                        @if(isset($schedule) && isset($employee))
                        {{-- Automation payroll - use unified back-to-draft route --}}
                        <form method="POST" action="{{ route('payrolls.automation.back-to-draft', ['schedule' => $schedule, 'id' => $employee]) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    onclick="return confirm('Move this payroll back to draft? This will delete the saved payroll and return to dynamic calculations.')">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                                </svg>
                                Back to Draft
                            </button>
                        </form>
                        @else
                        {{-- Regular payroll - use standard back-to-draft route --}}
                        <form method="POST" action="{{ route('payrolls.back-to-draft', $payroll) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:bg-yellow-700 active:bg-yellow-900 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    onclick="return confirm('Move this payroll back to draft? This will clear all snapshots and make it dynamic again.')">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"></path>
                                </svg>
                                Back to Draft
                            </button>
                        </form>
                        @endif
                        @endif
                        @endcan

                       
                        {{-- Mark as Paid/Unpaid buttons --}}
                        @can('mark payrolls as paid')
                        @if($payroll->canBeMarkedAsPaid())
                        <button type="button"
                                onclick="openMarkAsPaidModal()"
                                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Mark as Paid
                        </button>
                        @elseif($payroll->canBeUnmarkedAsPaid())
                        <form method="POST" action="{{ route('payrolls.unmark-as-paid', $payroll) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700 focus:bg-orange-700 active:bg-orange-900 focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    onclick="return confirm('Are you sure you want to unmark this payroll as paid? This will reverse all deduction calculations.')">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.866-.833-2.598 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                </svg>
                                Unmark as Paid
                            </button>
                        </form>
                        @endif
                        @endcan

                        {{-- @can('delete payrolls')
                        @if(!($payroll->payroll_type === 'automated' && in_array($payroll->status, ['draft', 'processing'])) && ($payroll->canBeEdited() || ($payroll->status === 'approved' && auth()->user()->can('delete approved payrolls'))))
                        <form method="POST" action="{{ route('payrolls.destroy', $payroll) }}" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    onclick="return confirm('Are you sure you want to delete this {{ $payroll->status }} payroll? This action cannot be undone.')">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Delete Payroll
                            </button>
                        </form>
                        @endif
                        @endcan --}}
                    </div>
                </div>
            </div>

            <!-- Employee Payroll Details -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Employee Payroll Details</h3>
                    
                    <div class="overflow-x-auto">   
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Employee
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Regular
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Holiday
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Rest
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Overtime
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Allowances
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Bonuses
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Incentives
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Gross Pay
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Deductions
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Net Pay
                                    </th>
                                    {{-- @if($payroll->status == 'approved')
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Payslip
                                    </th>
                                    @endif --}}
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($payroll->payrollDetails as $detail)
                                @php
                                    // Fetch snapshot data once for this employee (for processing/locked payrolls)
                                    $employeeSnapshot = null;
                                    if ($payroll->status !== 'draft') {
                                        $employeeSnapshot = \App\Models\PayrollSnapshot::where('payroll_id', $payroll->id)
                                                                                      ->where('employee_id', $detail->employee_id)
                                                                                      ->first();
                                    }
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 flex items-center gap-2">
                                                    <span>{{ $detail->employee->first_name }} {{ $detail->employee->last_name }}</span>
                                                    @if($detail->employee->benefits_status === 'with_benefits')
                                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-green-100 text-green-800">
                                                            Premium
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-800">
                                                            Basic
                                                        </span>
                                                    @endif
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $detail->employee->employee_number }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ $detail->employee->position->title ?? 'No Position' }}
                                                </div>
                                                @if($detail->employee->fixed_rate && $detail->employee->rate_type)
                                                <div class="flex items-center gap-1 ">
                                                    <span class="text-sm text-blue-700">
                                                        ₱{{ number_format($detail->employee->fixed_rate, 2) }}/{{ ucfirst(str_replace('_', ' ', $detail->employee->rate_type)) }}
                                                    </span>
                                                    <span class="inline-flex items-center p-1 text-xs font-medium rounded-full bg-blue-50 text-blue-700">
                                                        Fixed Rate
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-1">
                                                    @php
                                                        // Calculate Basic Pay for the specific payroll period using Employee model method
                                                        $employee = $detail->employee;
                                                        $periodStart = \Carbon\Carbon::parse($payroll->period_start);
                                                        $periodEnd = \Carbon\Carbon::parse($payroll->period_end);
                                                        
                                                        $basicPayForPeriod = $employee->calculateBasicPayForPeriod($periodStart, $periodEnd);
                                                    @endphp
                                                    <span class="text-sm font-small text-yellow-700">
                                                        ₱{{ number_format($basicPayForPeriod, 2) }}
                                                    </span>
                                                    <span class="inline-flex items-center p-1 text-xs font-small rounded-full bg-yellow-50 text-yellow-700">
                                                        Basic Pay
                                                    </span>
                                                </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        @php 
                                            if ($payroll->status === 'draft') {
                                                // DRAFT: Use dynamic calculation from payBreakdownByEmployee
                                                $payBreakdown = $payBreakdownByEmployee[$detail->employee_id] ?? [
                                                    'basic_pay' => 0, 
                                                    'holiday_pay' => 0,
                                                    'rest_day_pay' => 0,
                                                    'overtime_pay' => 0
                                                ];
                                            } else {
                                                // PROCESSING/APPROVED: Use stored data from snapshot first, fallback to database
                                                if ($employeeSnapshot) {
                                                    $payBreakdown = [
                                                        'basic_pay' => $employeeSnapshot->regular_pay ?? 0, 
                                                        'holiday_pay' => $employeeSnapshot->holiday_pay ?? 0,
                                                        'rest_day_pay' => $employeeSnapshot->rest_day_pay ?? 0,
                                                        'overtime_pay' => $employeeSnapshot->overtime_pay ?? 0
                                                    ];
                                                } else {
                                                    // Fallback to database values
                                                    $payBreakdown = [
                                                        'basic_pay' => $detail->regular_pay ?? 0, 
                                                        'holiday_pay' => $detail->holiday_pay ?? 0,
                                                        'rest_day_pay' => $detail->rest_day_pay ?? 0,
                                                        'overtime_pay' => $detail->overtime_pay ?? 0
                                                    ];
                                                }
                                            }
                                            
                                            // For draft mode, calculate basic pay from actual DTR data
                                            if ($payroll->status === 'draft') {
                                                $basicPay = 0; // Calculate from actual DTR data
                                            } else {
                                                $basicPay = $payBreakdown['basic_pay'];
                                            }
                                            
                                            // Get breakdown data
                                            $basicBreakdownData = [];
                                            $basicRegularHours = 0;
                                            
                                            if ($payroll->status === 'draft') {
                                                // DRAFT: Use timeBreakdowns data like Overtime column
                                                $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                                $hourlyRate = $detail->hourly_rate ?? 0; // Use calculated hourly rate from detail
                                                $basicPay = 0;
                                                
                                                // Get night differential settings for dynamic rate
                                                $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                                                
                                                // Regular workday hours - split into regular and regular+ND
                                                if (isset($employeeBreakdown['regular_workday'])) {
                                                    $regularHours = $employeeBreakdown['regular_workday']['regular_hours'] ?? 0;
                                                    $nightDiffRegularHours = $employeeBreakdown['regular_workday']['night_diff_regular_hours'] ?? 0;
                                                    
                                                    // Get rate config for regular workday
                                                    $rateConfig = $employeeBreakdown['regular_workday']['rate_config'] ?? null;
                                                    $regularMultiplier = $rateConfig ? $rateConfig->regular_rate_multiplier : 1.01;
                                                    
                                                    // Regular Workday (without ND)
                                                    if ($regularHours > 0) {
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $regularHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $regularMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $percentageDisplay = number_format($regularMultiplier * 100, 0) . '%';
                                                        
                                                        $basicBreakdownData['Regular Workday'] = [
                                                            'hours' => $regularHours,
                                                            'rate' => $hourlyRate,
                                                            'multiplier' => $regularMultiplier,
                                                            'percentage' => $percentageDisplay,
                                                            'amount' => $amount,
                                                        ];
                                                        $basicRegularHours += $regularHours;
                                                        $basicPay += $amount;
                                                    }
                                                    
                                                    // Regular Workday + ND
                                                    if ($nightDiffRegularHours > 0) {
                                                        // Combined rate: regular rate + night differential bonus
                                                        $combinedMultiplier = $regularMultiplier + ($nightDiffMultiplier - 1);
                                                        
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $nightDiffRegularHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $ndAmount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $ndPercentageDisplay = number_format($combinedMultiplier * 100, 0) . '%';
                                                        
                                                        $basicBreakdownData['Regular Workday+ND'] = [
                                                            'hours' => $nightDiffRegularHours,
                                                            'rate' => $hourlyRate,
                                                            'multiplier' => $combinedMultiplier,
                                                            'percentage' => $ndPercentageDisplay,
                                                            'amount' => $ndAmount,
                                                        ];
                                                        $basicRegularHours += $nightDiffRegularHours;
                                                        $basicPay += $ndAmount;
                                                    }
                                                }
                                                
                                                // Use suspension breakdowns from controller (with proper suspension settings logic)
                                                if (isset($suspensionBreakdown) && !empty($suspensionBreakdown)) {
                                                    foreach ($suspensionBreakdown as $type => $data) {
                                                        $suspensionHours = $data['hours'] ?? 0;
                                                        $suspensionMinutes = $data['minutes'] ?? 0;
                                                        
                                                        // Convert minutes to hours if hours is 0 (for suspension calculations)
                                                        if ($suspensionHours == 0 && $suspensionMinutes > 0) {
                                                            $suspensionHours = $suspensionMinutes / 60;
                                                        }
                                                        
                                                        $basicBreakdownData[$type] = [
                                                            'hours' => $suspensionHours,
                                                            'rate' => $data['rate'] ?? 0,
                                                            'days' => $data['days'] ?? 0,
                                                            'minutes' => $suspensionMinutes, // Include minutes for display
                                                            'dynamic_multiplier' => $data['dynamic_multiplier'] ?? 1.0, // Include dynamic multiplier
                                                            'multiplier' => $data['multiplier'] ?? 1.0, // Include pay rule multiplier
                                                            'fixed_amount' => $data['fixed_amount'] ?? 0,
                                                            'time_log_amount' => $data['time_log_amount'] ?? 0,
                                                            'amount' => $data['amount'] ?? 0,
                                                        ];
                                                        $basicRegularHours += $suspensionHours; // Add suspension hours to total
                                                        $basicPay += $data['amount'] ?? 0;
                                                    }
                                                }
                                            } else {
                                                // PROCESSING/APPROVED: Use breakdown data from snapshot
                                                $basicBreakdownData = [];
                                                if ($employeeSnapshot && $employeeSnapshot->basic_breakdown) {
                                                    $rawBreakdownData = is_string($employeeSnapshot->basic_breakdown) 
                                                        ? json_decode($employeeSnapshot->basic_breakdown, true) 
                                                        : $employeeSnapshot->basic_breakdown;
                                                    
                                                    // Ensure percentage is added to snapshot data for consistent display
                                                    foreach ($rawBreakdownData as $type => $data) {
                                                        $basicBreakdownData[$type] = [
                                                            'hours' => $data['hours'],
                                                            'amount' => $data['amount'],
                                                            'rate' => $data['rate'] ?? 0,
                                                            'multiplier' => $data['multiplier'] ?? 1.0,
                                                            'minutes' => $data['minutes'] ?? 0, // Include minutes for display
                                                            'dynamic_multiplier' => $data['dynamic_multiplier'] ?? 1.0, // Include dynamic multiplier
                                                            'fixed_amount' => $data['fixed_amount'] ?? 0, // Include fixed amount for partial suspension
                                                            'time_log_amount' => $data['time_log_amount'] ?? 0, // Include time log amount for partial suspension
                                                            'percentage' => isset($data['multiplier']) ? number_format($data['multiplier'] * 100, 0) . '%' : (isset($data['percentage']) ? $data['percentage'] : '100%')
                                                        ];
                                                    }
                                                }
                                                $basicRegularHours = array_sum(array_column($basicBreakdownData, 'hours'));
                                                // Use calculated total from breakdown instead of stored regular_pay
                                                $basicPay = array_sum(array_column($basicBreakdownData, 'amount'));
                                            }
                                        @endphp
                                        
                                        <div>
                                            @if(!empty($basicBreakdownData))
                                                <!-- Show Basic Pay breakdown -->
                                                @foreach($basicBreakdownData as $type => $data)
                                                    <div class="text-xs text-gray-500 mb-1">
                                                        @if($type == 'Paid Suspension')
                                                            <!-- Special handling for Paid Suspension -->
                                                            @php
                                                                $suspensionMinutes = isset($data['minutes']) ? $data['minutes'] : ($data['hours'] * 60);
                                                                $hourlyRate = $data['rate'] ?? 0;
                                                                $multiplier = $data['multiplier'] ?? 1.10;
                                                                $amount = $data['amount'] ?? 0;
                                                                $isFixedRate = isset($data['fixed_amount']); // Check if this is a fixed daily rate suspension
                                                            @endphp
                                                            
                                                            <div class="mb-1">
                                                                @if($isFixedRate || $suspensionMinutes == 0)
                                                                    <!-- Fixed daily rate suspension - show only amount -->
                                                                    <span>Paid Suspension: ₱{{ number_format($amount, 2) }}</span>
                                                                @else
                                                                    <!-- Time-based suspension (legacy or partial) - show minutes and percentage -->
                                                                    <span>Paid Suspension: {{ number_format($suspensionMinutes, 0) }}m</span>
                                                                    <div class="text-xs text-gray-600">
                                                                        {{ number_format($multiplier * 100, 0) }}% = ₱{{ number_format($amount, 2) }}
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @elseif($type == 'Paid Partial Suspension')
                                                            <!-- Special handling for Paid Partial Suspension -->
                                                            @php
                                                                $fixedAmount = $data['fixed_amount'] ?? 0;
                                                                $timeLogAmount = $data['time_log_amount'] ?? 0;
                                                                $totalAmount = $data['amount'] ?? 0;
                                                                $actualTimeLogHours = $timeLogAmount > 0 ? ($timeLogAmount / ($hourlyRate ?: 1)) : 0;
                                                                $timeLogMinutes = round($actualTimeLogHours * 60);
                                                            @endphp
                                                            
                                                            <div class="mb-1">
                                                                <span>Paid Partial Suspension: {{ number_format($timeLogMinutes, 0) }}m</span>
                                                                <div class="text-xs text-gray-600">
                                                                    100% = ₱{{ number_format($timeLogAmount, 2) }} + ₱{{ number_format($fixedAmount, 2) }}
                                                                </div>
                                                            </div>
                                                        @elseif($type == 'Full Suspension')
                                                            <!-- New handling for Full Suspension with fixed daily rate -->
                                                            @php
                                                                $amount = $data['amount'] ?? 0;
                                                                $dailyRate = $data['rate'] ?? 0;
                                                                $multiplier = $data['multiplier'] ?? 1.0;
                                                            @endphp
                                                            
                                                            <div class="mb-1">
                                                                <span >Full Suspension: <span class="text-xs text-gray-600">₱{{ number_format($amount, 2) }}
                                                                    </span></span>
                                                            </div>
                                                        @elseif($type == 'Partial Suspension')
                                                            <!-- New handling for Partial Suspension with minutes and dynamic rate multiplier -->
                                                            @php
                                                                $fixedAmount = $data['fixed_amount'] ?? 0;
                                                                $timeLogAmount = $data['time_log_amount'] ?? 0;
                                                                $totalAmount = $data['amount'] ?? 0;
                                                                $minutes = $data['minutes'] ?? 0;
                                                                $dynamicMultiplier = $data['dynamic_multiplier'] ?? 1.0;
                                                                $payRuleMultiplier = $data['multiplier'] ?? 1.0;
                                                                $hourlyRate = $data['rate'] ?? 0; // Now using hourly rate directly from controller
                                                                $displayMultiplier = ($dynamicMultiplier * 100);
                                                            @endphp
                                                            
                                                            <div class="mb-1">
                                                                <span>Partial Suspension: {{ $minutes }}m</span>
                                                                <div class="text-xs text-gray-600">
                                                                    {{ number_format($displayMultiplier, 0) }}% = ₱{{ number_format($timeLogAmount, 2) }} + ₱{{ number_format($fixedAmount, 2) }}
                                                                </div>
                                                                <!-- Hidden element for basic pay total calculation -->
                                                                <div class="basic-pay-amount" data-basic-amount="{{ $totalAmount }}" style="display: none;"></div>
                                                            </div>
                                                        @elseif(isset($data['description']) && str_contains($data['description'], 'Regular Suspension'))
                                                            <!-- Legacy handling for combined Regular Workday + Suspension (should not be used with new logic) -->
                                                            @php
                                                                $lines = explode(PHP_EOL, $data['description']);
                                                                $workdayHours = $data['workday_hours'] ?? 0;
                                                                $suspensionHours = $data['suspension_hours'] ?? 0;
                                                                $totalMinutes = isset($data['minutes']) ? $data['minutes'] : ($data['hours'] * 60);
                                                                $hourlyRate = $data['rate'] ?? 0;
                                                                $multiplier = $data['multiplier'] ?? 1;
                                                                $adjustedRate = $hourlyRate * $multiplier;
                                                            @endphp
                                                            
                                                            @if($workdayHours > 0)
                                                                <div class="mb-1">
                                                                    <span>Regular Workday: {{ number_format($workdayHours * 60, 0) }}m</span>
                                                                    <div class="text-xs text-gray-600">
                                                                        {{ number_format($multiplier * 100, 0) }}% = ₱{{ number_format($workdayHours * $adjustedRate, 2) }}
                                                                    </div>
                                                                </div>
                                                            @endif
                                                            
                                                            @if($suspensionHours > 0)
                                                                <div class="mb-1">
                                                                    <span>Regular Suspension {{ number_format($suspensionHours * 60, 0) }}m</span>
                                                                    <div class="text-xs text-gray-600">
                                                                        {{ number_format($multiplier * 100, 0) }}% = ₱{{ number_format($suspensionHours * $adjustedRate, 2) }}
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        @else
                                                            <!-- Regular display for other types -->
                                                            <span>{{ $type }}: {{ isset($data['minutes']) ? number_format($data['minutes'], 0) . 'm' : number_format($data['hours'] * 60, 0) . 'm' }}</span>
                                                            <div class="text-xs text-gray-600">
                                                                @if(isset($data['percentage']))
                                                                    {{ $data['percentage'] }} = ₱{{ number_format($data['amount'] ?? 0, 2) }}
                                                                @elseif(str_contains($type, '+ND') && isset($data['multiplier']))
                                                                    {{ number_format($data['multiplier'] * 100, 0) }}% = ₱{{ number_format($data['amount'] ?? 0, 2) }}
                                                                @elseif(str_contains($type, '+ND'))
                                                                    110% = ₱{{ number_format($data['amount'] ?? 0, 2) }}
                                                                @else
                                                                    ₱{{ number_format($data['rate'] ?? 0, 2) }}/hr 
                                                                    @if(isset($data['rate_per_minute']))
                                                                        <br>(₱{{ number_format($data['rate_per_minute'], 4) }}/min)
                                                                    @endif
                                                                    = ₱{{ number_format($data['amount'] ?? 0, 2) }}
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                <div class="text-xs border-t pt-1">
                                                    <?php 
                                                        // Round total minutes properly without adding extra 0.5
                                                        $totalMinutes = round($basicRegularHours * 60);
                                                        $hours = intval($totalMinutes / 60);
                                                        $minutes = $totalMinutes % 60;
                                                    ?>
                                                    <div class="text-gray-500">Total: {{ $hours }}h {{ $minutes }}m</div>
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-500">
                                                    <?php 
                                                        // Round total minutes properly without adding extra 0.5
                                                        $totalMinutes = round($basicRegularHours * 60);
                                                        $hours = intval($totalMinutes / 60);
                                                        $minutes = $totalMinutes % 60;
                                                    ?>
                                                    <div class="text-gray-500">{{ $hours }}h {{ $minutes }}m</div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="font-bold text-blue-600 basic-pay-amount" data-basic-amount="{{ $basicPay }}">₱{{ number_format($basicPay, 2) }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        @php 
                                            // Use holiday breakdown data from controller (works for both draft and locked payrolls)
                                            $employeeHolidayBreakdown = [];
                                            $totalHolidayRegularHours = 0;
                                            $holidayPay = 0;
                                            
                                            // Use the holidayBreakdown passed from controller
                                            if (isset($holidayBreakdown) && !empty($holidayBreakdown)) {
                                                $employeeHolidayBreakdown = $holidayBreakdown;
                                                
                                                // Calculate totals from controller's breakdown data
                                                foreach ($holidayBreakdown as $type => $data) {
                                                    $totalHolidayRegularHours += $data['hours'] ?? 0;
                                                    $holidayPay += $data['amount'] ?? 0;
                                                }
                                            } else {
                                                // Fallback: Get holiday pay from payBreakdownByEmployee
                                                $holidayPay = $payBreakdownByEmployee[$detail->employee_id]['holiday_pay'] ?? 0;
                                            }
                                        @endphp
                                        
                                        <div>
                                            @if(!empty($employeeHolidayBreakdown))
                                                <!-- Show individual holiday type breakdowns in consistent order -->
                                                @php
                                                    // Define consistent display order to match draft mode: Special Holiday first, then Regular Holiday
                                                    $orderedHolidayTypes = [
                                                        'Special Holiday',
                                                        'Special Holiday+ND', 
                                                        'Regular Holiday',
                                                        'Regular Holiday+ND'
                                                    ];
                                                    
                                                    // Sort breakdown by the defined order
                                                    $sortedHolidayBreakdown = [];
                                                    foreach ($orderedHolidayTypes as $type) {
                                                        if (isset($employeeHolidayBreakdown[$type])) {
                                                            $sortedHolidayBreakdown[$type] = $employeeHolidayBreakdown[$type];
                                                        }
                                                    }
                                                    // Add any remaining types not in the ordered list
                                                    foreach ($employeeHolidayBreakdown as $type => $data) {
                                                        if (!isset($sortedHolidayBreakdown[$type])) {
                                                            $sortedHolidayBreakdown[$type] = $data;
                                                        }
                                                    }
                                                @endphp
                                                @foreach($sortedHolidayBreakdown as $type => $data)
                                                    <div class="text-xs text-gray-500 mb-1">
                                                        @if(isset($data['fixed_amount']) && isset($data['time_log_amount']))
                                                            <!-- Hybrid Holiday calculation display -->
                                                            @php
                                                                $fixedAmount = $data['fixed_amount'] ?? 0;
                                                                $timeLogAmount = $data['time_log_amount'] ?? 0;
                                                                $totalAmount = $data['amount'] ?? 0;
                                                                $minutes = $data['minutes'] ?? ($data['hours'] * 60);
                                                            @endphp
                                                            <span>{{ $type }}: {{ number_format($minutes, 0) }}m</span>
                                                            <div class="text-xs text-gray-600">
                                                                @php
                                                                    $dynamicMultiplier = $data['dynamic_multiplier'] ?? 1.0;
                                                                    $displayMultiplier = ($dynamicMultiplier * 100);
                                                                @endphp
                                                                @if($fixedAmount > 0 && $timeLogAmount > 0)
                                                                    {{ number_format($displayMultiplier, 0) }}% = ₱{{ number_format($timeLogAmount, 2) }} + ₱{{ number_format($fixedAmount, 2) }}
                                                                @elseif($fixedAmount > 0)
                                                                    {{ number_format($displayMultiplier, 0) }}% = ₱0.00 + ₱{{ number_format($fixedAmount, 2) }}
                                                                @elseif($timeLogAmount > 0)
                                                                    {{ number_format($displayMultiplier, 0) }}% = ₱{{ number_format($timeLogAmount, 2) }} + ₱0.00
                                                                @endif
                                                            </div>
                                                        @else
                                                            <!-- Legacy Holiday calculation display -->
                                                            <span>{{ $type }}: {{ isset($data['minutes']) ? number_format($data['minutes'], 0) . 'm' : number_format($data['hours'] * 60, 0) . 'm' }}</span>
                                                            <div class="text-xs text-gray-600">
                                                                {{ $data['percentage'] ?? 'N/A' }} = ₱{{ number_format($data['amount'], 2) }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                                
                                                <div class="text-xs border-t pt-1">
                                                    <?php 
                                                        // Round total minutes properly without adding extra 0.5
                                                        $totalMinutes = round($totalHolidayRegularHours * 60);
                                                        $hours = intval($totalMinutes / 60);
                                                        $minutes = $totalMinutes % 60;
                                                    ?>
                                                    <div class="text-gray-500">Total: {{ $hours }}h {{ $minutes }}m</div>
                                                </div>
                                            @else
                                                <div class="text-gray-400">0h 0m</div>
                                            @endif
                                        </div>
                                        <div class="font-bold text-yellow-600 holiday-pay-amount" data-holiday-amount="{{ $holidayPay }}">₱{{ number_format($holidayPay, 2) }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        @php 
                                            $restDayBreakdown = [];
                                            $totalRestRegularHours = 0;
                                            $restDayPay = 0; // Calculate this properly
                                            
                                            if ($payroll->status === 'draft') {
                                                // DRAFT: Use timeBreakdowns data like Overtime column
                                                $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                                $hourlyRate = $detail->hourly_rate ?? 0; // Use calculated hourly rate from detail
                                                $restDayPay = 0;
                                                
                                                // Get night differential settings for dynamic rate
                                                $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                                                
                                                // Rest day hours - split into regular and regular+ND
                                                if (isset($employeeBreakdown['rest_day'])) {
                                                    $regularHours = $employeeBreakdown['rest_day']['regular_hours'] ?? 0;
                                                    $nightDiffRegularHours = $employeeBreakdown['rest_day']['night_diff_regular_hours'] ?? 0;
                                                    
                                                    $rateConfig = $employeeBreakdown['rest_day']['rate_config'];
                                                    $displayName = $rateConfig ? $rateConfig->display_name : 'Rest Day';
                                                    $regularMultiplier = $rateConfig ? $rateConfig->regular_rate_multiplier : 1.2;
                                                    
                                                    // Rest Day (without ND)
                                                    if ($regularHours > 0) {
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $regularHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $regularMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $percentageDisplay = number_format($regularMultiplier * 100, 0) . '%';
                                                        
                                                        $restDayBreakdown[$displayName] = [
                                                            'hours' => $regularHours,
                                                            'amount' => $amount,
                                                            'rate' => $hourlyRate,
                                                            'multiplier' => $regularMultiplier,
                                                            'percentage' => $percentageDisplay
                                                        ];
                                                        $totalRestRegularHours += $regularHours;
                                                        $restDayPay += $amount;
                                                    }
                                                    
                                                    // Rest Day + ND
                                                    if ($nightDiffRegularHours > 0) {
                                                        // Combined rate: rest day rate + night differential bonus
                                                        $combinedMultiplier = $regularMultiplier + ($nightDiffMultiplier - 1);
                                                        
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $nightDiffRegularHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $ndAmount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $ndPercentageDisplay = number_format($combinedMultiplier * 100, 0) . '%';
                                                        
                                                        $restDayBreakdown['Rest Day+ND'] = [
                                                            'hours' => $nightDiffRegularHours,
                                                            'amount' => $ndAmount,
                                                            'rate' => $hourlyRate,
                                                            'multiplier' => $combinedMultiplier,
                                                            'percentage' => $ndPercentageDisplay
                                                        ];
                                                        $totalRestRegularHours += $nightDiffRegularHours;
                                                        $restDayPay += $ndAmount;
                                                    }
                                                }
                                                
                                                // For both draft and processing, prioritize PayrollDetail stored hours if available
                                                if (empty($restDayBreakdown) && isset($detail->rest_day_hours) && $detail->rest_day_hours > 0) {
                                                    $totalRestRegularHours = $detail->rest_day_hours;
                                                }
                                            } else {
                                                // PROCESSING/APPROVED: Use breakdown data from snapshot
                                                $restBreakdownData = [];
                                                if ($employeeSnapshot && $employeeSnapshot->rest_breakdown) {
                                                    $restBreakdownData = is_string($employeeSnapshot->rest_breakdown) 
                                                        ? json_decode($employeeSnapshot->rest_breakdown, true) 
                                                        : $employeeSnapshot->rest_breakdown;
                                                    
                                                    foreach ($restBreakdownData as $type => $data) {
                                                        $restDayBreakdown[$type] = [
                                                            'hours' => $data['hours'],
                                                            'amount' => $data['amount'],
                                                            'rate' => $data['rate'],
                                                            'percentage' => number_format($data['multiplier'] * 100, 0) . '%'
                                                        ];
                                                        $totalRestRegularHours += $data['hours'];
                                                        $restDayPay += $data['amount']; // Sum up amounts from snapshot
                                                    }
                                                }
                                            }
                                        @endphp
                                        
                                        <div>
                                            @if(!empty($restDayBreakdown))
                                                <!-- Show individual rest day type breakdowns -->
                                                @foreach($restDayBreakdown as $type => $data)
                                                    <div class="text-xs text-gray-500 mb-1">
                                                       
                                                            <span>{{ $type }}: {{ isset($data['minutes']) ? number_format($data['minutes'], 0) . 'm' : number_format($data['hours'] * 60, 0) . 'm' }}</span>
                                                     
                                                        <div class="text-xs text-gray-600">
                                                            {{ $data['percentage'] }} = ₱{{ number_format($data['amount'], 2) }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                                
                                                <div class="text-xs border-t pt-1">
                                                    <?php 
                                                        // Round total minutes properly without adding extra 0.5
                                                        $totalMinutes = round($totalRestRegularHours * 60);
                                                        $hours = intval($totalMinutes / 60);
                                                        $minutes = $totalMinutes % 60;
                                                    ?>
                                                    <div class="text-gray-500">Total: {{ $hours }}h {{ $minutes }}m</div>
                                                   
                                                </div>
                                            @else
                                                @if($totalRestRegularHours > 0)
                                                    <?php 
                                                        // Round total minutes properly without adding extra 0.5
                                                        $totalMinutes = round($totalRestRegularHours * 60);
                                                        $hours = intval($totalMinutes / 60);
                                                        $minutes = $totalMinutes % 60;
                                                    ?>
                                                    <div class="text-xs text-gray-500">{{ $hours }}h {{ $minutes }}m</div>
                                                @else
                                                    <div class="text-gray-400">0h 0m</div>
                                                @endif
                                            @endif
                                        </div>
                                        <div class="font-bold text-cyan-600 rest-pay-amount" data-rest-amount="{{ $restDayPay }}">₱{{ number_format($restDayPay, 2) }}</div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        @php 
                                            // Use backend calculation for overtime pay
                                            $overtimePay = $payBreakdown['overtime_pay'] ?? 0;
                                            $totalOvertimeHours = 0;
                                            $overtimeBreakdown = [];
                                            
                                            if ($payroll->status === 'draft') {
                                                // DRAFT: Calculate overtime breakdown dynamically with CORRECT amounts
                                                $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                                $hourlyRate = $detail->hourly_rate ?? 0; // Use calculated hourly rate from detail
                                                $calculatedOvertimeTotal = 0;
                                                
                                                // Regular workday overtime - split into regular OT and OT+ND
                                                if (isset($employeeBreakdown['regular_workday'])) {
                                                    $regularOTHours = $employeeBreakdown['regular_workday']['regular_overtime_hours'] ?? 0;
                                                    $nightDiffOTHours = $employeeBreakdown['regular_workday']['night_diff_overtime_hours'] ?? 0;
                                                    $rateConfig = $employeeBreakdown['regular_workday']['rate_config'];
                                                    $overtimeMultiplier = $rateConfig ? ($rateConfig->overtime_rate_multiplier ?? 1.25) : 1.25;
                                                    
                                                    // Get night differential settings for dynamic rate
                                                    $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                    $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                                                    
                                                    // Regular Workday OT (without ND)
                                                    if ($regularOTHours > 0) {
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $regularOTHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $overtimeBreakdown[] = [
                                                            'name' => 'Regular Workday OT',
                                                            'hours' => $regularOTHours,
                                                            'amount' => $amount,
                                                            'percentage' => number_format($overtimeMultiplier * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $regularOTHours;
                                                        $calculatedOvertimeTotal += $amount;
                                                    }
                                                    
                                                    // Regular Workday OT + ND
                                                    if ($nightDiffOTHours > 0) {
                                                        // Combined rate: overtime rate + night differential bonus
                                                        $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);
                                                        
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $nightDiffOTHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $overtimeBreakdown[] = [
                                                            'name' => 'Regular Workday OT+ND',
                                                            'hours' => $nightDiffOTHours,
                                                            'amount' => $amount,
                                                            'percentage' => number_format($combinedMultiplier * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $nightDiffOTHours;
                                                        $calculatedOvertimeTotal += $amount;
                                                    }
                                                }
                                                
                                                // Special holiday overtime - split into regular OT and OT+ND
                                                if (isset($employeeBreakdown['special_holiday'])) {
                                                    $regularOTHours = $employeeBreakdown['special_holiday']['regular_overtime_hours'] ?? 0;
                                                    $nightDiffOTHours = $employeeBreakdown['special_holiday']['night_diff_overtime_hours'] ?? 0;
                                                    $rateConfig = $employeeBreakdown['special_holiday']['rate_config'];
                                                    $overtimeMultiplier = $rateConfig ? ($rateConfig->overtime_rate_multiplier ?? 1.69) : 1.69;
                                                    
                                                    // Get night differential settings for dynamic rate
                                                    $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                    $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                                                    
                                                    // Special Holiday OT (without ND)
                                                    if ($regularOTHours > 0) {
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $regularOTHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $overtimeBreakdown[] = [
                                                            'name' => 'Special Holiday OT',
                                                            'hours' => $regularOTHours,
                                                            'amount' => $amount,
                                                            'percentage' => number_format($overtimeMultiplier * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $regularOTHours;
                                                        $calculatedOvertimeTotal += $amount;
                                                    }
                                                    
                                                    // Special Holiday OT + ND
                                                    if ($nightDiffOTHours > 0) {
                                                        // Combined rate: overtime rate + night differential bonus
                                                        $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);
                                                        
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $nightDiffOTHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $overtimeBreakdown[] = [
                                                            'name' => 'Special Holiday OT+ND',
                                                            'hours' => $nightDiffOTHours,
                                                            'amount' => $amount,
                                                            'percentage' => number_format($combinedMultiplier * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $nightDiffOTHours;
                                                        $calculatedOvertimeTotal += $amount;
                                                    }
                                                }
                                                
                                                // Regular holiday overtime - split into regular OT and OT+ND
                                                if (isset($employeeBreakdown['regular_holiday'])) {
                                                    $regularOTHours = $employeeBreakdown['regular_holiday']['regular_overtime_hours'] ?? 0;
                                                    $nightDiffOTHours = $employeeBreakdown['regular_holiday']['night_diff_overtime_hours'] ?? 0;
                                                    $rateConfig = $employeeBreakdown['regular_holiday']['rate_config'];
                                                    $overtimeMultiplier = $rateConfig ? ($rateConfig->overtime_rate_multiplier ?? 2.6) : 2.6;
                                                    
                                                    // Get night differential settings for dynamic rate
                                                    $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                    $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                                                    
                                                    // Regular Holiday OT (without ND)
                                                    if ($regularOTHours > 0) {
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $regularOTHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $overtimeBreakdown[] = [
                                                            'name' => 'Regular Holiday OT',
                                                            'hours' => $regularOTHours,
                                                            'amount' => $amount,
                                                            'percentage' => number_format($overtimeMultiplier * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $regularOTHours;
                                                        $calculatedOvertimeTotal += $amount;
                                                    }
                                                    
                                                    // Regular Holiday OT + ND
                                                    if ($nightDiffOTHours > 0) {
                                                        // Combined rate: overtime rate + night differential bonus
                                                        $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);
                                                        
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $nightDiffOTHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $overtimeBreakdown[] = [
                                                            'name' => 'Regular Holiday OT+ND',
                                                            'hours' => $nightDiffOTHours,
                                                            'amount' => $amount,
                                                            'percentage' => number_format($combinedMultiplier * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $nightDiffOTHours;
                                                        $calculatedOvertimeTotal += $amount;
                                                    }
                                                }
                                                
                                                // Rest day overtime - split into regular OT and OT+ND
                                                if (isset($employeeBreakdown['rest_day'])) {
                                                    $regularOTHours = $employeeBreakdown['rest_day']['regular_overtime_hours'] ?? 0;
                                                    $nightDiffOTHours = $employeeBreakdown['rest_day']['night_diff_overtime_hours'] ?? 0;
                                                    $rateConfig = $employeeBreakdown['rest_day']['rate_config'];
                                                    $overtimeMultiplier = $rateConfig ? ($rateConfig->overtime_rate_multiplier ?? 1.69) : 1.69;
                                                    
                                                    // Get night differential settings for dynamic rate
                                                    $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                    $nightDiffMultiplier = $nightDiffSetting ? $nightDiffSetting->rate_multiplier : 1.10;
                                                    
                                                    // Rest Day OT (without ND)
                                                    if ($regularOTHours > 0) {
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $regularOTHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $overtimeMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $overtimeBreakdown[] = [
                                                            'name' => 'Rest Day OT',
                                                            'hours' => $regularOTHours,
                                                            'amount' => $amount,
                                                            'percentage' => number_format($overtimeMultiplier * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $regularOTHours;
                                                        $calculatedOvertimeTotal += $amount;
                                                    }
                                                    
                                                    // Rest Day OT + ND
                                                    if ($nightDiffOTHours > 0) {
                                                        // Combined rate: overtime rate + night differential bonus
                                                        $combinedMultiplier = $overtimeMultiplier + ($nightDiffMultiplier - 1);
                                                        
                                                        // Use consistent calculation: hourly rate * multiplier, truncate to 4 decimals, then multiply by minutes
                                                        $actualMinutes = $nightDiffOTHours * 60;
                                                        $roundedMinutes = round($actualMinutes);
                                                        $adjustedHourlyRate = $hourlyRate * $combinedMultiplier;
                                                        $ratePerMinute = $adjustedHourlyRate / 60; // Truncate to 4 decimals
                                                        $amount = round($ratePerMinute * $roundedMinutes, 2); // Round final amount to 2 decimals
                                                        
                                                        $overtimeBreakdown[] = [
                                                            'name' => 'Rest Day OT+ND',
                                                            'hours' => $nightDiffOTHours,
                                                            'amount' => $amount,
                                                            'percentage' => number_format($combinedMultiplier * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $nightDiffOTHours;
                                                        $calculatedOvertimeTotal += $amount;
                                                    }
                                                }
                                                
                                                // Override the backend overtime pay with our correct calculation for display
                                                $overtimePay = $calculatedOvertimeTotal;
                                            } else {
                                                // PROCESSING/APPROVED: Use breakdown data from snapshot - NO CALCULATION!
                                                $overtimeBreakdownData = [];
                                                if ($employeeSnapshot && $employeeSnapshot->overtime_breakdown) {
                                                    $overtimeBreakdownData = is_string($employeeSnapshot->overtime_breakdown) 
                                                        ? json_decode($employeeSnapshot->overtime_breakdown, true) 
                                                        : $employeeSnapshot->overtime_breakdown;
                                                    
                                                    foreach ($overtimeBreakdownData as $type => $data) {
                                                        $overtimeBreakdown[] = [
                                                            'name' => $type,
                                                            'hours' => $data['hours'],
                                                            'amount' => $data['amount'],
                                                            'percentage' => number_format($data['multiplier'] * 100, 0) . '%'
                                                        ];
                                                        $totalOvertimeHours += $data['hours'];
                                                    }
                                                    // Use the total from snapshots
                                                    $overtimePay = array_sum(array_column($overtimeBreakdown, 'amount'));
                                                } else {
                                                    // Fallback to the simple overtime pay from snapshot
                                                    $overtimePay = $detail->overtime_pay ?? 0;
                                                }
                                            }
                                        @endphp
                                        
                                        <div>
                                            @if(!empty($overtimeBreakdown))
                                                @foreach($overtimeBreakdown as $ot)
                                                    <div class="text-xs text-gray-500 mb-1">
                                                        <span>{{ $ot['name'] }}: {{ isset($ot['minutes']) ? number_format($ot['minutes'], 0) . 'm' : number_format($ot['hours'] * 60, 0) . 'm' }}</span>
                                                        <div class="text-xs text-gray-600">
                                                            {{ $ot['percentage'] }} = ₱{{ number_format($ot['amount'], 2) }}
                                                        </div>
                                                    </div>
                                                @endforeach
                                                
                                                <div class="text-xs border-t pt-1">
                                                    <?php 
                                                        // Round total minutes properly without adding extra 0.5
                                                        $totalMinutes = round($totalOvertimeHours * 60);
                                                        $hours = intval($totalMinutes / 60);
                                                        $minutes = $totalMinutes % 60;
                                                    ?>
                                                    <div class="text-gray-500">Total: {{ $hours }}h {{ $minutes }}m</div>
                                                </div>
                                            @else
                                                <div class="text-gray-400">0h 0m</div>
                                            @endif
                                        </div>
                                        <div class="font-bold text-orange-600">₱{{ number_format($overtimePay, 2) }}</div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        <div class="space-y-1">
                                            @php
                                                // Always calculate allowances dynamically in DRAFT mode
                                                $dynamicAllowancesTotal = 0;
                                                $allowanceBreakdownDisplay = [];
                                            @endphp
                                            
                                            @if(isset($isDynamic) && $isDynamic && $allowanceSettings->isNotEmpty())
                                                <!-- DRAFT MODE: Show Dynamic Calculations -->
                                                @foreach($allowanceSettings as $setting)
                                                    @php
                                                        // Check if this allowance setting applies to this employee's benefit status
                                                        if (!$setting->appliesTo($detail->employee)) {
                                                            continue; // Skip this setting for this employee
                                                        }
                                                        
                                                        // Calculate actual amount for display based on setting configuration
                                                        $displayAmount = 0;
                                                        if($setting->calculation_type === 'percentage') {
                                                            $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                                            $displayAmount = ($basicPay * $setting->rate_percentage) / 100;
                                                        } elseif($setting->calculation_type === 'fixed_amount') {
                                                            $displayAmount = $setting->fixed_amount;
                                                        } elseif($setting->calculation_type === 'automatic') {
                                                            // Use the model's calculateAmount method for automatic calculation
                                                            $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                                            $displayAmount = $setting->calculateAmount($basicPay, $detail->employee->daily_rate, null, $detail->employee);
                                                            
                                                            // Apply frequency-based calculation for daily allowances
                                                            if ($setting->frequency === 'daily') {
                                                                // Calculate actual working days for this employee in this period
                                                                $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                                                $workingDays = 0;
                                                                
                                                                // Count working days from DTR data
                                                                if (isset($employeeBreakdown['regular_workday'])) {
                                                                    $regularBreakdown = $employeeBreakdown['regular_workday'];
                                                                    $workingDays += ($regularBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                                }
                                                                if (isset($employeeBreakdown['special_holiday'])) {
                                                                    $specialBreakdown = $employeeBreakdown['special_holiday'];
                                                                    $workingDays += ($specialBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                                }
                                                                if (isset($employeeBreakdown['regular_holiday'])) {
                                                                    $regularHolidayBreakdown = $employeeBreakdown['regular_holiday'];
                                                                    $workingDays += ($regularHolidayBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                                }
                                                                if (isset($employeeBreakdown['rest_day'])) {
                                                                    $restBreakdown = $employeeBreakdown['rest_day'];
                                                                    $workingDays += ($restBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                                }
                                                                
                                                                // Apply max days limit if set
                                                                $maxDays = $setting->max_days_per_period ?? $workingDays;
                                                                $applicableDays = min($workingDays, $maxDays);
                                                                
                                                                $displayAmount = $setting->fixed_amount * $applicableDays;
                                                            }
                                                        } elseif($setting->calculation_type === 'daily_rate_multiplier') {
                                                            $dailyRate = $detail->employee->daily_rate ?? 0;
                                                            $multiplier = $setting->multiplier ?? 1;
                                                            $displayAmount = $dailyRate * $multiplier;
                                                        }
                                                        
                                                        // Apply minimum and maximum limits
                                                        if ($setting->minimum_amount && $displayAmount < $setting->minimum_amount) {
                                                            $displayAmount = $setting->minimum_amount;
                                                        }
                                                        if ($setting->maximum_amount && $displayAmount > $setting->maximum_amount) {
                                                            $displayAmount = $setting->maximum_amount;
                                                        }
                                                        
                                        // Apply distribution method
                                        $distributedAmount = 0;
                                        if ($displayAmount > 0) {
                                            $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                            $distributedAmount = $setting->calculateDistributedAmount(
                                                $displayAmount,
                                                $payroll->period_start,
                                                $payroll->period_end,
                                                $employeePaySchedule,
                                                $payroll->pay_schedule ?? null
                                            );
                                        }                                                        // Add to breakdown and total
                                                        if ($distributedAmount > 0) {
                                                            $allowanceBreakdownDisplay[] = [
                                                                'name' => $setting->name,
                                                                'amount' => $distributedAmount
                                                            ];
                                                            $dynamicAllowancesTotal += $distributedAmount;
                                                        }
                                                    @endphp
                                                @endforeach
                                                
                                                <!-- Display breakdown -->
                                                @foreach($allowanceBreakdownDisplay as $item)
                                                    <div class="text-xs text-gray-500">
                                                        <span>{{ $item['name'] }}:</span>
                                                        <span>₱{{ number_format($item['amount'], 2) }}</span>
                                                    </div>
                                                @endforeach
                                                
                                                <!-- Display dynamic total -->
                                                <div class="font-bold text-green-600">
                                                    ₱{{ number_format($dynamicAllowancesTotal, 2) }}
                                                </div>
                                                <div class="text-xs text-green-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        Current settings
                                                    </span>
                                                </div>
                                            @elseif($detail->allowances > 0 || ($employeeSnapshot && $employeeSnapshot->allowances_breakdown))
                                                <!-- PROCESSING/APPROVED: Show Breakdown from Snapshot -->
                                                @php
                                                    // Get allowances breakdown from the fetched snapshot
                                                    $allowancesBreakdown = [];
                                                    $snapshotAllowancesTotal = 0;
                                                    if ($employeeSnapshot && $employeeSnapshot->allowances_breakdown) {
                                                        $allowancesBreakdown = is_string($employeeSnapshot->allowances_breakdown) 
                                                            ? json_decode($employeeSnapshot->allowances_breakdown, true) 
                                                            : $employeeSnapshot->allowances_breakdown;
                                                        
                                                        // Calculate total from breakdown (this is the CORRECT distributed amount)
                                                        foreach ($allowancesBreakdown as $allowance) {
                                                            if (isset($allowance['amount']) && $allowance['amount'] > 0) {
                                                                $snapshotAllowancesTotal += $allowance['amount'];
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                
                                                @if(!empty($allowancesBreakdown))
                                                    @foreach($allowancesBreakdown as $allowance)
                                                        @if(isset($allowance['amount']) && $allowance['amount'] > 0)
                                                            <div class="text-xs text-gray-500">
                                                                <span>{{ $allowance['name'] ?? $allowance['code'] }}:</span>
                                                                <span>₱{{ number_format($allowance['amount'], 2) }}</span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                                
                                                <div class="font-bold text-green-600">
                                                    ₱{{ number_format($snapshotAllowancesTotal, 2) }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                        </svg>
                                                        Locked snapshot
                                                    </span>
                                                </div>
                                            @else
                                                <!-- No allowances or zero amount -->
                                                <div class="text-gray-400">₱0.00</div>
                                                @if(isset($isDynamic) && $isDynamic)
                                                    <div class="text-xs text-green-500">
                                                        <span class="inline-flex items-center">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                            </svg>
                                                            Current settings
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="text-xs text-gray-500">
                                                        <span class="inline-flex items-center">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                            </svg>
                                                            Locked snapshot
                                                        </span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <!-- Bonuses Column -->
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        <div class="space-y-1">
                                            @if(isset($isDynamic) && $isDynamic && isset($bonusSettings) && $bonusSettings->isNotEmpty())
                                                <!-- DYNAMIC PAYROLL: Calculate and display from settings -->
                                                @php
                                                    $dynamicBonusTotal = 0;
                                                    $hasDynamicBonuses = false;
                                                @endphp
                                                @foreach($bonusSettings as $setting)
                                                    @php
                                                        // Check if this bonus setting applies to this employee's benefit status
                                                        if (!$setting->appliesTo($detail->employee)) {
                                                            continue; // Skip this setting for this employee
                                                        }
                                                        $hasDynamicBonuses = true;
                                                        
                                        // Calculate actual amount for display
                                        $displayAmount = 0;
                                        if($setting->calculation_type === 'percentage') {
                                            $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                            $displayAmount = ($basicPay * $setting->rate_percentage) / 100;
                                        } elseif($setting->calculation_type === 'fixed_amount') {
                                            $displayAmount = $setting->fixed_amount;
                                        } elseif($setting->calculation_type === 'automatic') {
                                            // Use the model's calculateAmount method for automatic calculation
                                            $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                            
                                            // Prepare breakdown data for 13th month calculation
                                            $breakdownData = [
                                                'basic' => isset($basicBreakdownData) ? $basicBreakdownData : [],
                                                'holiday' => isset($employeeHolidayBreakdown) ? $employeeHolidayBreakdown : []
                                            ];
                                            
                                            $displayAmount = $setting->calculateAmount($basicPay, $detail->employee->daily_rate, null, $detail->employee, $breakdownData);
                                        }                                                        // Apply distribution method
                                                        $distributedAmount = 0;
                                                        if ($displayAmount > 0) {
                                                            $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                                            $distributedAmount = $setting->calculateDistributedAmount(
                                                                $displayAmount,
                                                                $payroll->period_start,
                                                                $payroll->period_end,
                                                                $employeePaySchedule,
                                                                $payroll->pay_schedule ?? null
                                                            );
                                                        }
                                                        
                                                        $dynamicBonusTotal += $distributedAmount;
                                                    @endphp
                                                    @if($distributedAmount > 0)
                                                        <div class="text-xs text-gray-500">
                                                            <span>{{ $setting->name }}:</span>
                                                            <span>₱{{ number_format($distributedAmount, 2) }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                                
                                                <div class="font-bold text-blue-600">
                                                    ₱{{ number_format($dynamicBonusTotal, 2) }}
                                                </div>
                                                <div class="text-xs text-blue-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        Current settings
                                                    </span>
                                                </div>
                                            @elseif($detail->bonuses > 0 || ($employeeSnapshot && $employeeSnapshot->bonuses_breakdown))
                                                <!-- PROCESSING/APPROVED: Show Bonus Breakdown from Snapshot -->
                                                @php
                                                    // Get bonuses breakdown from the fetched snapshot
                                                    $bonusesBreakdown = [];
                                                    $snapshotBonusesTotal = 0;
                                                    if ($employeeSnapshot && $employeeSnapshot->bonuses_breakdown) {
                                                        $bonusesBreakdown = is_string($employeeSnapshot->bonuses_breakdown) 
                                                            ? json_decode($employeeSnapshot->bonuses_breakdown, true) 
                                                            : $employeeSnapshot->bonuses_breakdown;
                                                        
                                                        // Calculate total from breakdown (this is the CORRECT distributed amount)
                                                        foreach ($bonusesBreakdown as $bonus) {
                                                            if (isset($bonus['amount']) && $bonus['amount'] > 0) {
                                                                $snapshotBonusesTotal += $bonus['amount'];
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                
                                                @if(!empty($bonusesBreakdown))
                                                    @foreach($bonusesBreakdown as $bonus)
                                                        @if(isset($bonus['amount']) && $bonus['amount'] > 0)
                                                            <div class="text-xs text-gray-500">
                                                                <span>{{ $bonus['name'] ?? $bonus['code'] }}:</span>
                                                                <span>₱{{ number_format($bonus['amount'], 2) }}</span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                                
                                                <div class="font-bold text-blue-600">
                                                    ₱{{ number_format($snapshotBonusesTotal, 2) }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                        </svg>
                                                        Locked snapshot
                                                    </span>
                                                </div>
                                            @else
                                                <!-- No bonuses or zero amount -->
                                                <div class="text-gray-400">₱0.00</div>
                                                @if(isset($isDynamic) && $isDynamic)
                                                    <div class="text-xs text-blue-500">
                                                        <span class="inline-flex items-center">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                            </svg>
                                                            Current settings
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="text-xs text-gray-500">
                                                        <span class="inline-flex items-center">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                            </svg>
                                                            Locked snapshot
                                                        </span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <!-- Incentives Column -->
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        <div class="space-y-1">
                                            @if(isset($isDynamic) && $isDynamic && isset($incentiveSettings) && $incentiveSettings->isNotEmpty())
                                                <!-- DYNAMIC PAYROLL: Calculate and display from settings -->
                                                @php
                                                    $dynamicIncentiveTotal = 0;
                                                    $hasDynamicIncentives = false;
                                                @endphp
                                                @foreach($incentiveSettings as $setting)
                                                    @php
                                                        // Check if this incentive setting applies to this employee's benefit status
                                                        if (!$setting->appliesTo($detail->employee)) {
                                                            continue; // Skip this setting for this employee
                                                        }
                                                        $hasDynamicIncentives = true;
                                                        
                                                        // Check if this incentive requires perfect attendance
                                                        if ($setting->requires_perfect_attendance) {
                                                            // Check if employee has perfect attendance for this payroll period
                                                            if (!$setting->hasPerfectAttendance($detail->employee, $payroll->period_start, $payroll->period_end)) {
                                                                continue; // Skip this incentive if perfect attendance not met
                                                            }
                                                        }
                                                        
                                        // Calculate distributed amount
                                        $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                        $distributedAmount = $setting->calculateDistributedAmount(
                                            $setting->fixed_amount ?? 0,
                                            $payroll->period_start,
                                            $payroll->period_end,
                                            $employeePaySchedule,
                                            $payroll->pay_schedule ?? null
                                        );                                                        $dynamicIncentiveTotal += $distributedAmount;
                                                    @endphp
                                                    @if($distributedAmount > 0)
                                                        <div class="text-xs text-gray-500">
                                                            <span>{{ $setting->name }}:</span>
                                                            <span>₱{{ number_format($distributedAmount, 2) }}</span>
                                                        </div>
                                                    @endif
                                                @endforeach
                                                
                                                <div class="font-bold text-purple-600">
                                                    ₱{{ number_format($dynamicIncentiveTotal, 2) }}
                                                </div>
                                                <div class="text-xs text-purple-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        Current settings
                                                    </span>
                                                </div>
                                            @elseif($detail->incentives > 0 || ($employeeSnapshot && $employeeSnapshot->incentives_breakdown))
                                                <!-- PROCESSING/APPROVED: Show Incentive Breakdown from Snapshot -->
                                                @php
                                                    // Get incentives breakdown from the fetched snapshot
                                                    $incentivesBreakdown = [];
                                                    $snapshotIncentivesTotal = 0;
                                                    if ($employeeSnapshot && $employeeSnapshot->incentives_breakdown) {
                                                        $incentivesBreakdown = is_string($employeeSnapshot->incentives_breakdown) 
                                                            ? json_decode($employeeSnapshot->incentives_breakdown, true) 
                                                            : $employeeSnapshot->incentives_breakdown;
                                                        
                                                        // Calculate total from breakdown (this is the CORRECT distributed amount)
                                                        foreach ($incentivesBreakdown as $incentive) {
                                                            if (isset($incentive['amount']) && $incentive['amount'] > 0) {
                                                                $snapshotIncentivesTotal += $incentive['amount'];
                                                            }
                                                        }
                                                    }
                                                @endphp
                                                
                                                @if(!empty($incentivesBreakdown))
                                                    @foreach($incentivesBreakdown as $incentive)
                                                        @if(isset($incentive['amount']) && $incentive['amount'] > 0)
                                                            <div class="text-xs text-gray-500">
                                                                <span>{{ $incentive['name'] ?? $incentive['code'] }}:</span>
                                                                <span>₱{{ number_format($incentive['amount'], 2) }}</span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @endif
                                                
                                                <div class="font-bold text-purple-600">
                                                    ₱{{ number_format($snapshotIncentivesTotal, 2) }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                        </svg>
                                                        Locked snapshot
                                                    </span>
                                                </div>
                                            @else
                                                <!-- No incentives or zero amount -->
                                                <div class="text-gray-400">₱0.00</div>
                                                @if(isset($isDynamic) && $isDynamic)
                                                    <div class="text-xs text-purple-500">
                                                        <span class="inline-flex items-center">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                            </svg>
                                                            Current settings
                                                        </span>
                                                    </div>
                                                @else
                                                    <div class="text-xs text-gray-500">
                                                        <span class="inline-flex items-center">
                                                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                            </svg>
                                                            Locked snapshot
                                                        </span>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                        @php
                            // Calculate correct gross pay: Basic + Holiday + Rest + Overtime + Allowances + Bonuses + Incentives
                            // Use the SAME distributed calculation logic as individual columns
                            
                            // Calculate DYNAMIC allowances (same logic as Allowances column)
                            $allowances = 0;
                            if ($payroll->status === 'draft' && $allowanceSettings->isNotEmpty()) {
                                foreach($allowanceSettings as $allowanceSetting) {
                                    // Check if this allowance setting applies to this employee's benefit status
                                    if (!$allowanceSetting->appliesTo($detail->employee)) {
                                        continue; // Skip this setting for this employee
                                    }
                                    
                                    // Calculate actual amount for display based on setting configuration
                                    $displayAmount = 0;
                                    if($allowanceSetting->calculation_type === 'percentage') {
                                        $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                        $displayAmount = ($basicPay * $allowanceSetting->rate_percentage) / 100;
                                    } elseif($allowanceSetting->calculation_type === 'fixed_amount') {
                                        $displayAmount = $allowanceSetting->fixed_amount;
                                    } elseif($allowanceSetting->calculation_type === 'automatic') {
                                        // Use the model's calculateAmount method for automatic calculation
                                        $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                        $displayAmount = $allowanceSetting->calculateAmount($basicPay, $detail->employee->daily_rate, null, $detail->employee);
                                        
                                        // Apply frequency-based calculation for daily allowances
                                        if ($allowanceSetting->frequency === 'daily') {
                                            // Calculate actual working days for this employee in this period
                                            $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                            $workingDays = 0;
                                            
                                            // Count working days from DTR data
                                            if (isset($employeeBreakdown['regular_workday'])) {
                                                $regularBreakdown = $employeeBreakdown['regular_workday'];
                                                $workingDays += ($regularBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                            }
                                            if (isset($employeeBreakdown['special_holiday'])) {
                                                $specialBreakdown = $employeeBreakdown['special_holiday'];
                                                $workingDays += ($specialBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                            }
                                            if (isset($employeeBreakdown['regular_holiday'])) {
                                                $regularHolidayBreakdown = $employeeBreakdown['regular_holiday'];
                                                $workingDays += ($regularHolidayBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                            }
                                            if (isset($employeeBreakdown['rest_day'])) {
                                                $restBreakdown = $employeeBreakdown['rest_day'];
                                                $workingDays += ($restBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                            }
                                            
                                            // Apply max days limit if set
                                            $maxDays = $allowanceSetting->max_days_per_period ?? $workingDays;
                                            $applicableDays = min($workingDays, $maxDays);
                                            
                                            $displayAmount = $allowanceSetting->fixed_amount * $applicableDays;
                                        }
                                    } elseif($allowanceSetting->calculation_type === 'daily_rate_multiplier') {
                                        $dailyRate = $detail->employee->daily_rate ?? 0;
                                        $multiplier = $allowanceSetting->multiplier ?? 1;
                                        $displayAmount = $dailyRate * $multiplier;
                                    }
                                    
                                    // Apply minimum and maximum limits
                                    if ($allowanceSetting->minimum_amount && $displayAmount < $allowanceSetting->minimum_amount) {
                                        $displayAmount = $allowanceSetting->minimum_amount;
                                    }
                                    if ($allowanceSetting->maximum_amount && $displayAmount > $allowanceSetting->maximum_amount) {
                                        $displayAmount = $allowanceSetting->maximum_amount;
                                    }
                                    
                                    // Apply distribution method
                                    $distributedAmount = 0;
                                    if ($displayAmount > 0) {
                                        $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                        $distributedAmount = $allowanceSetting->calculateDistributedAmount(
                                            $displayAmount,
                                            $payroll->period_start,
                                            $payroll->period_end,
                                            $employeePaySchedule,
                                            $payroll->pay_schedule ?? null
                                        );
                                    }
                                    
                                    $allowances += $distributedAmount;
                                }
            } else {
                // For non-dynamic payrolls, use snapshot breakdown totals (CORRECT distributed amounts)
                if ($employeeSnapshot && $employeeSnapshot->allowances_breakdown !== null) {
                    $allowancesBreakdown = is_string($employeeSnapshot->allowances_breakdown) 
                        ? json_decode($employeeSnapshot->allowances_breakdown, true) 
                        : $employeeSnapshot->allowances_breakdown;
                    
                    $allowances = 0;
                    if (is_array($allowancesBreakdown)) {
                        foreach ($allowancesBreakdown as $allowance) {
                            if (isset($allowance['amount']) && $allowance['amount'] > 0) {
                                $allowances += $allowance['amount'];
                            }
                        }
                    }
                } else {
                    // Fallback to stored value if no snapshot breakdown exists
                    $allowances = $detail->allowances ?? 0;
                }
            }                            // Calculate DYNAMIC bonuses (same logic as Bonuses column)
                            $bonuses = 0;
                            if ($payroll->status === 'draft' && isset($bonusSettings) && $bonusSettings->isNotEmpty()) {
                                foreach($bonusSettings as $bonusSetting) {
                                    // Check if this bonus setting applies to this employee's benefit status
                                    if (!$bonusSetting->appliesTo($detail->employee)) {
                                        continue; // Skip this setting for this employee
                                    }
                                    
                                    // Calculate actual amount for display
                                    $displayAmount = 0;
                                    if($bonusSetting->calculation_type === 'percentage') {
                                        $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                        $displayAmount = ($basicPay * $bonusSetting->rate_percentage) / 100;
                                    } elseif($bonusSetting->calculation_type === 'fixed_amount') {
                                        $displayAmount = $bonusSetting->fixed_amount;
                                    } elseif($bonusSetting->calculation_type === 'automatic') {
                                        // Use the model's calculateAmount method for automatic calculation
                                        $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                        $breakdownData = ['basic' => [], 'holiday' => []];
                                        $displayAmount = $bonusSetting->calculateAmount($basicPay, $detail->employee->daily_rate, null, $detail->employee, $breakdownData);
                                    }
                                    
                    // Apply distribution method
                    $distributedAmount = 0;
                    if ($displayAmount > 0) {
                        $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                        $distributedAmount = $bonusSetting->calculateDistributedAmount(
                            $displayAmount,
                            $payroll->period_start,
                            $payroll->period_end,
                            $employeePaySchedule,
                            $payroll->pay_schedule ?? null
                        );
                    }                                    $bonuses += $distributedAmount;
                                }
            } else {
                // For non-dynamic payrolls, use snapshot breakdown totals (CORRECT distributed amounts)
                if ($employeeSnapshot && $employeeSnapshot->bonuses_breakdown !== null) {
                    $bonusesBreakdown = is_string($employeeSnapshot->bonuses_breakdown) 
                        ? json_decode($employeeSnapshot->bonuses_breakdown, true) 
                        : $employeeSnapshot->bonuses_breakdown;
                    
                    $bonuses = 0;
                    if (is_array($bonusesBreakdown)) {
                        foreach ($bonusesBreakdown as $bonus) {
                            if (isset($bonus['amount']) && $bonus['amount'] > 0) {
                                $bonuses += $bonus['amount'];
                            }
                        }
                    }
                } else {
                    // Fallback to stored value if no snapshot breakdown exists
                    $bonuses = $detail->bonuses ?? 0;
                }
            }                            // Calculate DYNAMIC incentives (same logic as Incentives column)
                            $incentives = 0;
                            if ($payroll->status === 'draft' && isset($incentiveSettings) && $incentiveSettings->isNotEmpty()) {
                                foreach($incentiveSettings as $incentiveSetting) {
                                    // Check if this incentive setting applies to this employee's benefit status
                                    if (!$incentiveSetting->appliesTo($detail->employee)) {
                                        continue; // Skip this setting for this employee
                                    }
                                    
                                    // Check if this incentive requires perfect attendance
                                    if ($incentiveSetting->requires_perfect_attendance) {
                                        // Check if employee has perfect attendance for this payroll period
                                        if (!$incentiveSetting->hasPerfectAttendance($detail->employee, $payroll->period_start, $payroll->period_end)) {
                                            continue; // Skip this incentive if perfect attendance not met
                                        }
                                    }
                                    
                                    $calculatedIncentiveAmount = $incentiveSetting->fixed_amount ?? 0;
                                    
                    // Apply distribution method
                    $distributedAmount = 0;
                    if ($calculatedIncentiveAmount > 0) {
                        $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                        $distributedAmount = $incentiveSetting->calculateDistributedAmount(
                            $calculatedIncentiveAmount,
                            $payroll->period_start,
                            $payroll->period_end,
                            $employeePaySchedule,
                            $payroll->pay_schedule ?? null
                        );
                    }                                    $incentives += $distributedAmount;
                                }
            } else {
                // For non-dynamic payrolls, use snapshot breakdown totals (CORRECT distributed amounts)
                if ($employeeSnapshot && $employeeSnapshot->incentives_breakdown !== null) {
                    $incentivesBreakdown = is_string($employeeSnapshot->incentives_breakdown) 
                        ? json_decode($employeeSnapshot->incentives_breakdown, true) 
                        : $employeeSnapshot->incentives_breakdown;
                    
                    $incentives = 0;
                    if (is_array($incentivesBreakdown)) {
                        foreach ($incentivesBreakdown as $incentive) {
                            if (isset($incentive['amount']) && $incentive['amount'] > 0) {
                                $incentives += $incentive['amount'];
                            }
                        }
                    }
                } else {
                    // Fallback to stored value if no snapshot breakdown exists
                    $incentives = $detail->incentives ?? 0;
                }
            }                            // Handle basic pay - for locked payrolls use snapshot, for draft use dynamic calculation
                            if ($payroll->status !== 'draft' && $employeeSnapshot) {
                                // For locked/processing payrolls, use static snapshot data
                                if ($employeeSnapshot->basic_breakdown) {
                                    $basicBreakdown = is_string($employeeSnapshot->basic_breakdown) 
                                        ? json_decode($employeeSnapshot->basic_breakdown, true) 
                                        : $employeeSnapshot->basic_breakdown;
                                    
                                    $basicPayForGross = 0;
                                    if (is_array($basicBreakdown)) {
                                        foreach ($basicBreakdown as $type => $data) {
                                            $basicPayForGross += $data['amount'] ?? 0;
                                        }
                                    }
                                } else {
                                    // Fallback to stored regular_pay if no breakdown
                                    $basicPayForGross = $employeeSnapshot->regular_pay ?? 0;
                                }
            } else {
                // For draft payrolls, calculate from basic breakdown to match REGULAR column total
                // This ensures the gross pay breakdown "Regular:" matches the REGULAR column total
                $basicPayForGross = 0;
                
                // Get basic breakdown data (includes suspensions that appear in REGULAR column)
                if (isset($basicBreakdownData) && is_array($basicBreakdownData)) {
                    foreach ($basicBreakdownData as $type => $data) {
                        $basicPayForGross += $data['amount'] ?? 0;
                    }
                } else {
                    // Fallback to basic pay if no breakdown available
                    $basicPayForGross = round($basicPay, 2);
                }
            }                            // Handle holiday pay - for locked payrolls use snapshot, for draft use dynamic calculation
                            if ($payroll->status !== 'draft' && $employeeSnapshot) {
                                // For locked/processing payrolls, use static snapshot data
                                if ($employeeSnapshot->holiday_breakdown) {
                                    $holidayBreakdown = is_string($employeeSnapshot->holiday_breakdown) 
                                        ? json_decode($employeeSnapshot->holiday_breakdown, true) 
                                        : $employeeSnapshot->holiday_breakdown;
                                    
                                    $holidayPayForGross = 0;
                                    if (is_array($holidayBreakdown)) {
                                        foreach ($holidayBreakdown as $type => $data) {
                                            $holidayPayForGross += $data['amount'] ?? 0;
                                        }
                                    }
                                } else {
                                    // Fallback to stored holiday_pay if no breakdown
                                    $holidayPayForGross = $employeeSnapshot->holiday_pay ?? 0;
                                }
                            } else {
                                // For draft payrolls, use dynamic calculation
                                $holidayPayForGross = round($holidayPay, 2); // Use rounded value to match display
                            }
                            
                            // Handle rest pay - for locked payrolls use snapshot, for draft use dynamic calculation
                            if ($payroll->status !== 'draft' && $employeeSnapshot) {
                                // For locked/processing payrolls, use static snapshot data
                                if ($employeeSnapshot->rest_breakdown) {
                                    $restBreakdown = is_string($employeeSnapshot->rest_breakdown) 
                                        ? json_decode($employeeSnapshot->rest_breakdown, true) 
                                        : $employeeSnapshot->rest_breakdown;
                                    
                                    $restPayForGross = 0;
                                    if (is_array($restBreakdown)) {
                                        foreach ($restBreakdown as $restData) {
                                            $restPayForGross += $restData['amount'] ?? 0;
                                        }
                                    }
                                } else {
                                    // Fallback to stored rest_day_pay if no breakdown
                                    $restPayForGross = $employeeSnapshot->rest_day_pay ?? 0;
                                }
                            } else {
                                // For draft payrolls, use dynamic calculation
                                $restPayForGross = round($restDayPay, 2); // Use rounded value to match display
                            }
                            
                            // Handle overtime pay - for locked payrolls use snapshot, for draft use dynamic calculation
                            if ($payroll->status !== 'draft' && $employeeSnapshot) {
                                // For locked/processing payrolls, use static snapshot data
                                if ($employeeSnapshot->overtime_breakdown) {
                                    $overtimeBreakdown = is_string($employeeSnapshot->overtime_breakdown) 
                                        ? json_decode($employeeSnapshot->overtime_breakdown, true) 
                                        : $employeeSnapshot->overtime_breakdown;
                                    
                                    $overtimePayForGross = 0;
                                    if (is_array($overtimeBreakdown)) {
                                        foreach ($overtimeBreakdown as $type => $data) {
                                            $overtimePayForGross += $data['amount'] ?? 0;
                                        }
                                    }
                                } else {
                                    // Fallback to stored overtime_pay if no breakdown
                                    $overtimePayForGross = $employeeSnapshot->overtime_pay ?? 0;
                                }
                            } else {
                                // For draft payrolls, use dynamic calculation and round overtime pay to match display precision
                                $overtimePayForGross = round($overtimePay, 2);
                            }
                            
                            // Recalculate bonuses using proper breakdown data (fixes 13th month calculation for GROSS PAY column)
                            if ($payroll->status !== 'draft' && $employeeSnapshot) {
                                // For locked/processing payrolls, use bonuses from snapshot breakdown
                                $bonusesForGross = 0;
                                if ($employeeSnapshot->bonuses_breakdown) {
                                    $bonusesBreakdown = is_string($employeeSnapshot->bonuses_breakdown) 
                                        ? json_decode($employeeSnapshot->bonuses_breakdown, true) 
                                        : $employeeSnapshot->bonuses_breakdown;
                                    
                                    if (is_array($bonusesBreakdown)) {
                                        foreach ($bonusesBreakdown as $bonus) {
                                            if (isset($bonus['amount']) && $bonus['amount'] > 0) {
                                                $bonusesForGross += $bonus['amount'];
                                            }
                                        }
                                    }
                                } else {
                                    // Fallback to stored bonuses if no breakdown
                                    $bonusesForGross = $employeeSnapshot->bonuses ?? 0;
                                }
                            } else {
                                // For draft payrolls, recalculate bonuses with proper breakdown data now that it's available
                                $bonusesForGross = 0;
                                if (isset($bonusSettings) && $bonusSettings->isNotEmpty()) {
                                    foreach($bonusSettings as $bonusSetting) {
                                        // Check if this bonus setting applies to this employee's benefit status
                                        if (!$bonusSetting->appliesTo($detail->employee)) {
                                            continue; // Skip this setting for this employee
                                        }
                                        
                                        $calculatedBonusAmount = 0;
                                        if($bonusSetting->calculation_type === 'percentage') {
                                            $calculatedBonusAmount = ($basicPayForGross * $bonusSetting->rate_percentage) / 100;
                                        } elseif($bonusSetting->calculation_type === 'fixed_amount') {
                                            $calculatedBonusAmount = $bonusSetting->fixed_amount;
                                        } elseif($bonusSetting->calculation_type === 'automatic') {
                                            // Use proper breakdown data for 13th month calculation
                                            $breakdownData = [
                                                'basic' => isset($basicBreakdownData) ? $basicBreakdownData : [],
                                                'holiday' => isset($employeeHolidayBreakdown) ? $employeeHolidayBreakdown : []
                                            ];
                                            $calculatedBonusAmount = $bonusSetting->calculateAmount($basicPayForGross, $detail->employee->daily_rate, null, $detail->employee, $breakdownData);
                                        }
                                        
                                        // Apply distribution method for summary calculation (to match individual columns)
                                        if ($calculatedBonusAmount > 0) {
                                            $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                            $distributedAmount = $bonusSetting->calculateDistributedAmount(
                                                $calculatedBonusAmount,
                                                $payroll->period_start,
                                                $payroll->period_end,
                                                $employeePaySchedule,
                                                $payroll->pay_schedule ?? null
                                            );
                                            $bonusesForGross += $distributedAmount;
                                        }
                                    }
                                } else {
                                    // Fallback to stored value if no active settings
                                    $bonusesForGross = $detail->bonuses ?? 0;
                                }
                            }
                            
                            // Calculate gross pay - for locked payrolls, calculate from breakdown totals to match display
                            // This ensures the gross pay total always matches the sum of breakdown components
                            $calculatedGrossPay = $basicPayForGross + $holidayPayForGross + $restPayForGross + $overtimePayForGross + $allowances + $bonusesForGross + $incentives;
                        @endphp                                        <!-- Show Gross Pay Breakdown -->
                                        <div class="space-y-1">
                                            @if($calculatedGrossPay > 0)
                                               
                                                    @if($basicPayForGross > 0)
                                                        <div class="text-xs text-gray-500">
                                                            <span>Regular:</span>
                                                            <span>₱{{ number_format($basicPayForGross, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    @if($holidayPayForGross > 0)
                                                        <div class="text-xs text-gray-500">
                                                            <span>Holiday:</span>
                                                            <span>₱{{ number_format($holidayPayForGross, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    @if($restPayForGross > 0)
                                                        <div class="text-xs text-gray-500">
                                                            <span>Rest:</span>
                                                            <span>₱{{ number_format($restPayForGross, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    @if($overtimePayForGross > 0)
                                                        <div class="text-xs text-gray-500">
                                                            <span>Overtime:</span>
                                                            <span>₱{{ number_format($overtimePayForGross, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    @if($allowances > 0)
                                                        <div class="text-xs text-gray-500">
                                                            <span>Allow.:</span>
                                                            <span>₱{{ number_format($allowances, 2) }}</span>
                                                        </div>
                                                    @endif
                                    @if($bonusesForGross > 0)
                                        <div class="text-xs text-gray-500">
                                            <span>Bonus:</span>
                                            <span>₱{{ number_format($bonusesForGross, 2) }}</span>
                                        </div>
                                    @endif
                                    @if($incentives > 0)
                                        <div class="text-xs text-gray-500">
                                            <span>Incent:</span>
                                            <span>₱{{ number_format($incentives, 2) }}</span>
                                        </div>
                                    @endif                                            @endif
                                            @php
                                                // Calculate taxable income from breakdown components to match display
                                                // This ensures taxable income always matches the sum of taxable breakdown components
                                                $taxableIncome = $basicPayForGross + $holidayPayForGross + $restPayForGross + $overtimePayForGross;
                                                
                                                // Add taxable allowances/bonuses/incentives from breakdown calculations
                                                // For locked payrolls, these are already calculated from snapshot breakdown above
                                                // For draft payrolls, these are calculated dynamically above
                                                if ($payroll->status !== 'draft' && $employeeSnapshot) {
                                                    // For locked payrolls, check taxable settings from snapshot breakdown
                                                    // Add taxable allowances
                                                    if ($employeeSnapshot->allowances_breakdown) {
                                                        $allowancesBreakdown = is_string($employeeSnapshot->allowances_breakdown) 
                                                            ? json_decode($employeeSnapshot->allowances_breakdown, true) 
                                                            : $employeeSnapshot->allowances_breakdown;
                                                        
                                                        foreach ($allowancesBreakdown as $allowance) {
                                                            if (isset($allowance['is_taxable']) && $allowance['is_taxable'] && isset($allowance['amount'])) {
                                                                $taxableIncome += $allowance['amount'];
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Add taxable bonuses
                                                    if ($employeeSnapshot->bonuses_breakdown) {
                                                        $bonusesBreakdown = is_string($employeeSnapshot->bonuses_breakdown) 
                                                            ? json_decode($employeeSnapshot->bonuses_breakdown, true) 
                                                            : $employeeSnapshot->bonuses_breakdown;
                                                        
                                                        foreach ($bonusesBreakdown as $bonus) {
                                                            if (isset($bonus['is_taxable']) && $bonus['is_taxable'] && isset($bonus['amount'])) {
                                                                $taxableIncome += $bonus['amount'];
                                                            }
                                                        }
                                                    }
                                                    
                                                    // Add taxable incentives
                                                    if ($employeeSnapshot->incentives_breakdown) {
                                                        $incentivesBreakdown = is_string($employeeSnapshot->incentives_breakdown) 
                                                            ? json_decode($employeeSnapshot->incentives_breakdown, true) 
                                                            : $employeeSnapshot->incentives_breakdown;
                                                        
                                                        foreach ($incentivesBreakdown as $incentive) {
                                                            if (isset($incentive['is_taxable']) && $incentive['is_taxable'] && isset($incentive['amount'])) {
                                                                $taxableIncome += $incentive['amount'];
                                                            }
                                                        }
                                                    }
                                                } else {
                                    // For draft payrolls, calculate taxable income dynamically
                                    // Same calculation as PayrollDetail.getTaxableIncomeAttribute()
                                    // NOTE: Night differential amounts are already embedded in basic, holiday, and rest pay
                                    // through the breakdown calculations (Regular Workday+ND, Holiday+ND, etc.)
                                    // Base taxable income already calculated above: $taxableIncome = $basicPayForGross + $holidayPayForGross + $restPayForGross + $overtimePayForGross;                                                    // Add taxable allowances/bonuses/incentives from settings
                                                    $allSettings = collect();
                                                    if (isset($allowanceSettings) && $allowanceSettings->isNotEmpty()) {
                                                        $allSettings = $allSettings->merge($allowanceSettings);
                                                    }
                                                    if (isset($bonusSettings) && $bonusSettings->isNotEmpty()) {
                                                        $allSettings = $allSettings->merge($bonusSettings);
                                                    }
                                                    if (isset($incentiveSettings) && $incentiveSettings->isNotEmpty()) {
                                                        $allSettings = $allSettings->merge($incentiveSettings);
                                                    }
                                                    
                                                    // Add only taxable allowances/bonuses/incentives
                                                    if ($allSettings->isNotEmpty()) {
                                                        foreach($allSettings as $setting) {
                                                            // Check if this setting applies to this employee's benefit status
                                                            if (!$setting->appliesTo($detail->employee)) {
                                                                continue; // Skip this setting for this employee
                                                            }
                                                            
                                                            // Only add if this setting is taxable
                                                            if (!$setting->is_taxable) {
                                                                continue;
                                                            }
                                                            
                                                            // Check perfect attendance for incentives
                                                            if ($setting->type === 'incentives' && $setting->requires_perfect_attendance) {
                                                                if (!$setting->hasPerfectAttendance($detail->employee, $payroll->period_start, $payroll->period_end)) {
                                                                    continue; // Skip this incentive if perfect attendance not met
                                                                }
                                                            }
                                                            
                                                            $calculatedAmount = 0;
                                                            
                                            // Calculate the amount based on the setting type
                                            if($setting->calculation_type === 'percentage') {
                                                $calculatedAmount = ($basicPayForGross * $setting->rate_percentage) / 100;
                                            } elseif($setting->calculation_type === 'fixed_amount') {
                                                $calculatedAmount = $setting->fixed_amount;
                                            } elseif($setting->calculation_type === 'automatic') {
                                                // Use the model's calculateAmount method for automatic calculation
                                                $calculatedAmount = $setting->calculateAmount($basicPayForGross, $detail->employee->daily_rate, null, $detail->employee);                                                                // Apply frequency-based calculation for daily allowances
                                                                if ($setting->frequency === 'daily') {
                                                                    // Use same working days calculation as in allowances column
                                                                    $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                                                    $workingDays = 0;
                                                                    
                                                                    if (isset($employeeBreakdown['regular_workday'])) {
                                                                        $regularBreakdown = $employeeBreakdown['regular_workday'];
                                                                        $workingDays += ($regularBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                                    }
                                                                    if (isset($employeeBreakdown['special_holiday'])) {
                                                                        $specialBreakdown = $employeeBreakdown['special_holiday'];
                                                                        $workingDays += ($specialBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                                    }
                                                                    if (isset($employeeBreakdown['regular_holiday'])) {
                                                                        $regularHolidayBreakdown = $employeeBreakdown['regular_holiday'];
                                                                        $workingDays += ($regularHolidayBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                                    }
                                                                    if (isset($employeeBreakdown['rest_day'])) {
                                                                        $restBreakdown = $employeeBreakdown['rest_day'];
                                                                        $workingDays += ($restBreakdown['regular_hours'] ?? 0) > 0 ? 1 : 0;
                                                                    }
                                                                    
                                                                    $maxDays = $setting->max_days_per_period ?? $workingDays;
                                                                    $applicableDays = min($workingDays, $maxDays);
                                                                    
                                                                    $calculatedAmount = $setting->fixed_amount * $applicableDays;
                                                                }
                                                            } elseif($setting->calculation_type === 'daily_rate_multiplier') {
                                                                $dailyRate = $detail->employee->daily_rate ?? 0;
                                                                $multiplier = $setting->multiplier ?? 1;
                                                                $calculatedAmount = $dailyRate * $multiplier;
                                                            }
                                                            
                                                            // Apply limits
                                                            if ($setting->minimum_amount && $calculatedAmount < $setting->minimum_amount) {
                                                                $calculatedAmount = $setting->minimum_amount;
                                                            }
                                                            if ($setting->maximum_amount && $calculatedAmount > $setting->maximum_amount) {
                                                                $calculatedAmount = $setting->maximum_amount;
                                                            }
                                                            
                                                            // Apply distribution method
                                                            if ($calculatedAmount > 0) {
                                                                $employeePaySchedule = $detail->employee->pay_schedule ?? 'semi_monthly';
                                                                $distributedAmount = $setting->calculateDistributedAmount(
                                                                    $calculatedAmount,
                                                                    $payroll->period_start,
                                                                    $payroll->period_end,
                                                                    $employeePaySchedule,
                                                                    $payroll->pay_schedule ?? null
                                                                );
                                                                
                                                                // Add taxable allowance/bonus/incentive to taxable income
                                                                $taxableIncome += $distributedAmount;
                                                            }
                                                        }
                                                    }
                                                    
                                                }
                                                
                                                $taxableIncome = max(0, $taxableIncome);
                                            @endphp
                                          
                                            <div class="font-medium text-green-600 gross-pay-amount" data-gross-amount="{{ $calculatedGrossPay }}">₱{{ number_format($calculatedGrossPay, 2) }}</div>
                                              @if($taxableIncome > 0)
                                                  <div class="text-xs text-gray-500 taxable-income-amount" data-taxable-amount="{{ $taxableIncome }}">Taxable: ₱{{ number_format($taxableIncome, 2) }}</div>
                                              @endif
                                          
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                        <div class="space-y-1">
                                            @php
                                                $calculatedDeductionTotal = 0;
                                                $hasBreakdown = false;
                                                
                                                // Check if we have deductions to show (either stored or can be calculated dynamically)
                                                $hasDeductionsToShow = $detail->total_deductions > 0 || 
                                                                     (isset($detail->deduction_breakdown) && is_array($detail->deduction_breakdown) && !empty($detail->deduction_breakdown)) ||
                                                                     ($employeeSnapshot && $employeeSnapshot->deductions_breakdown) ||
                                                                     (isset($isDynamic) && $isDynamic && $deductionSettings->isNotEmpty());
                                            @endphp
                                            
                                            @if($hasDeductionsToShow)
                                                
                                                <!-- Show Deduction Breakdown if available (from snapshot or dynamic calculation) -->
                                                @if(isset($detail->deduction_breakdown) && is_array($detail->deduction_breakdown) && !empty($detail->deduction_breakdown))
                                                    @php $hasBreakdown = true; @endphp
                                                    @foreach($detail->deduction_breakdown as $code => $deductionData)
                                                        @php
                                                            $amount = $deductionData['amount'] ?? $deductionData;
                                                            $calculatedDeductionTotal += $amount;
                                                        @endphp
                                                        <div class="text-xs text-gray-500">
                                                            <span>{{ $deductionData['name'] ?? $code }}:</span>
                                                            <span>₱{{ number_format($amount, 2) }}</span>
                                                        </div>
                                                    @endforeach
                                                @elseif($employeeSnapshot && $employeeSnapshot->deductions_breakdown)
                                                    @php 
                                                        $hasBreakdown = true; 
                                                        $deductionsBreakdown = is_string($employeeSnapshot->deductions_breakdown) 
                                                            ? json_decode($employeeSnapshot->deductions_breakdown, true) 
                                                            : $employeeSnapshot->deductions_breakdown;
                                                    @endphp
                                                    @foreach($deductionsBreakdown as $code => $deductionData)
                                                        @php
                                                            $amount = $deductionData['amount'] ?? $deductionData;
                                                            $calculatedDeductionTotal += $amount;
                                                        @endphp
                                                        @if($amount > 0)
                                                            <div class="text-xs text-gray-500">
                                                                <span>{{ $deductionData['name'] ?? $code }}:</span>
                                                                <span>₱{{ number_format($amount, 2) }}</span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                @elseif(isset($isDynamic) && $isDynamic && $deductionSettings->isNotEmpty())
                                                    @php $hasBreakdown = true; @endphp
                                                    <!-- Show Active Deduction Settings with Calculated Amounts -->
                                                    @foreach($deductionSettings as $setting)
                                                        @php
                                                            // Check if this deduction setting applies to this employee's benefit status
                                                            if (!$setting->appliesTo($detail->employee)) {
                                                                continue; // Skip this setting for this employee
                                                            }
                                                            
                                                            // Calculate actual deduction amount for this employee
                                                            $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->basic_pay ?? 0;
                                                            // Use the CALCULATED gross pay from the Gross Pay column instead of stored value
                                                            $grossPayForDeduction = $calculatedGrossPay;
                                                            // Use the CALCULATED breakdown components for consistency
                                                            $overtimePayForDeduction = $overtimePayForGross;
                                                            $allowancesForDeduction = $allowances;
                                                            $bonusesForDeduction = $bonuses;
                                                            
                                                            // Auto-detect pay frequency from payroll period using dynamic pay schedule settings
                                                            $payFrequency = \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                                                                $payroll->period_start,
                                                                $payroll->period_end
                                                            );
                                                            
                                            // Use the calculated taxable income from the previous column
                                            $calculatedAmount = $setting->calculateDeduction(
                                                $basicPay, 
                                                $overtimePayForDeduction, 
                                                $bonusesForDeduction, 
                                                $allowancesForDeduction, 
                                                $grossPayForDeduction,
                                                $taxableIncome,  // Pass calculated taxable income
                                                null, // netPay (not used for now)
                                                $detail->employee->calculateMonthlyBasicSalary($payroll->period_start, $payroll->period_end), // monthlyBasicSalary - DYNAMIC
                                                $payFrequency // Pass auto-detected pay frequency
                                            );
                                            
                                            // Apply deduction distribution logic to match backend calculations
                                            if ($calculatedAmount > 0) {
                                                $calculatedAmount = $setting->calculateDistributedAmount(
                                                    $calculatedAmount,
                                                    $payroll->period_start,
                                                    $payroll->period_end,
                                                    $detail->employee->pay_schedule ?? $payFrequency,
                                                    $payroll->pay_schedule ?? null
                                                );
                                            }
                                            
                                            $calculatedDeductionTotal += $calculatedAmount;                                                            // Debug info for PhilHealth only
                                                            $debugInfo = '';
                                                            if (strtolower($setting->name) === 'philhealth' || strtolower($setting->code) === 'philhealth') {
                                                                // Get the pay basis being used by this setting
                                                                $payBasisDebug = '';
                                                                if ($setting->apply_to_basic_pay) $payBasisDebug .= 'Basic Pay ';
                                                                if ($setting->apply_to_gross_pay) $payBasisDebug .= 'Gross Pay ';
                                                                if ($setting->apply_to_taxable_income) $payBasisDebug .= 'Taxable Income ';
                                                                if ($setting->apply_to_monthly_basic_salary) $payBasisDebug .= 'Monthly Basic ';
                                                                if ($setting->apply_to_net_pay) $payBasisDebug .= 'Net Pay ';
                                                                
                                                                $salaryUsed = 0;
                                                                if ($setting->apply_to_basic_pay) $salaryUsed = $basicPay;
                                                                elseif ($setting->apply_to_gross_pay) $salaryUsed = $grossPayForDeduction;
                                                                elseif ($setting->apply_to_taxable_income) $salaryUsed = $taxableIncome;
                                                                elseif ($setting->apply_to_monthly_basic_salary) $salaryUsed = $detail->employee->calculateMonthlyBasicSalary($payroll->period_start, $payroll->period_end);
                                                                elseif ($setting->apply_to_net_pay) $salaryUsed = 0; // calculated later
                                                                
                                                                // Find matching tax table using correct column names
                                                                $taxTable = null;
                                                                if ($setting->tax_table_type === 'philhealth') {
                                                                    $taxTable = \App\Models\PhilHealthTaxTable::where('range_start', '<=', $salaryUsed)
                                                                        ->where('range_end', '>=', $salaryUsed)
                                                                        ->first();
                                                                }
                                                            
                                                            }
                                                        @endphp
                                                        @if($calculatedAmount > 0)
                                                            <div class="text-xs text-gray-500">
                                                                <span>{{ $setting->name }}:</span>
                                                                <span>₱{{ number_format($calculatedAmount, 2) }}</span>
                                                                {!! $debugInfo !!}
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                    
                                                    <!-- Show Cash Advance Deductions for Dynamic Payroll -->
                                                    @if($detail->cash_advance_deductions > 0)
                                                        @php $calculatedDeductionTotal += $detail->cash_advance_deductions; @endphp
                                                        <div class="text-xs text-gray-500">
                                                            <span>CA:</span>
                                                            <span>₱{{ number_format($detail->cash_advance_deductions, 2) }}</span>
                                                        </div>
                                                    @endif
                                                @elseif(!isset($isDynamic) || !$isDynamic || $deductionSettings->isEmpty())
                                                    @php $hasBreakdown = true; @endphp
                                                    <!-- Show Traditional Breakdown for snapshot/non-dynamic payrolls or when no deduction settings -->
                                                    @if($detail->sss_contribution > 0)
                                                        @php $calculatedDeductionTotal += $detail->sss_contribution; @endphp
                                                        <div class="text-xs text-gray-500">
                                                            <span>SSS:</span>
                                                            <span>₱{{ number_format($detail->sss_contribution, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($detail->philhealth_contribution > 0)
                                                        @php $calculatedDeductionTotal += $detail->philhealth_contribution; @endphp
                                                        <div class="text-xs text-gray-500">
                                                            <span>PhilHealth:</span>
                                                            <span>₱{{ number_format($detail->philhealth_contribution, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($detail->pagibig_contribution > 0)
                                                        @php $calculatedDeductionTotal += $detail->pagibig_contribution; @endphp
                                                        <div class="text-xs text-gray-500">
                                                            <span>PagIBIG:</span>
                                                            <span>₱{{ number_format($detail->pagibig_contribution, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($detail->withholding_tax > 0)
                                                        @php $calculatedDeductionTotal += $detail->withholding_tax; @endphp
                                                        <div class="text-xs text-gray-500">
                                                            <span>BIR:</span>
                                                            <span>₱{{ number_format($detail->withholding_tax, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($detail->cash_advance_deductions > 0)
                                                        @php $calculatedDeductionTotal += $detail->cash_advance_deductions; @endphp
                                                        <div class="text-xs text-gray-500">
                                                            <span>CA:</span>
                                                            <span>₱{{ number_format($detail->cash_advance_deductions, 2) }}</span>
                                                        </div>
                                                    @endif
                                                    
                                                    @if($detail->other_deductions > 0)
                                                        @php $calculatedDeductionTotal += $detail->other_deductions; @endphp
                                                        <div class="text-xs text-gray-500">
                                                            <span>Other:</span>
                                                            <span>₱{{ number_format($detail->other_deductions, 2) }}</span>
                                                        </div>
                                                    @endif
                                                @endif
                                                
                                                <!-- Total deductions -->
                                                <div class="font-medium text-red-600 deduction-amount" data-deduction-amount="{{ $calculatedDeductionTotal > 0 ? $calculatedDeductionTotal : $detail->total_deductions }}">
                                                    ₱{{ number_format($calculatedDeductionTotal > 0 ? $calculatedDeductionTotal : $detail->total_deductions, 2) }}
                                                </div>
                                            @else
                                                @if(isset($isDynamic) && $isDynamic && $deductionSettings->isNotEmpty())
                                                    <!-- Show Available Deduction Settings when no deductions applied -->
                                                    @foreach($deductionSettings as $setting)
                                                        @php
                                                            // Check if this deduction setting applies to this employee's benefit status
                                                            if (!$setting->appliesTo($detail->employee)) {
                                                                continue; // Skip this setting for this employee
                                                            }
                                                        @endphp
                                                        <div class="text-xs text-gray-400">
                                                            <span>{{ $setting->name }}:</span>
                                                            <span>
                                                                @if($setting->calculation_type === 'fixed_amount')
                                                                    ₱{{ number_format($setting->fixed_amount, 2) }}
                                                                @elseif($setting->calculation_type === 'percentage')
                                                                    {{ $setting->rate_percentage }}%
                                                                @else
                                                                    {{ ucfirst(str_replace('_', ' ', $setting->calculation_type)) }}
                                                                @endif
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                @endif
                                                <div class="font-medium text-gray-400">₱0.00</div>
                                            @endif
                                            
                                            @if(isset($isDynamic) && $isDynamic)
                                                <div class="text-xs text-green-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                                        </svg>
                                                        Current settings
                                                    </span>
                                                </div>
                                            @else
                                                <div class="text-xs text-gray-500">
                                                    <span class="inline-flex items-center">
                                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                                        </svg>
                                                        Locked snapshot
                                                    </span>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap text-sm text-right">
                                        @php
                                            // Calculate net pay - use snapshot data for processing/approved payrolls
                                            $detailDeductionTotal = 0;
                                            
                                            // Use the SAME calculation logic as the Gross Pay column
                                            $allowances = $detail->allowances ?? 0;
                                            $bonuses = $detail->bonuses ?? 0;
                                            
                                            // Handle basic pay - use the same calculation as the Basic column for consistency
                                            $basicPay = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->regular_pay ?? 0;
                                            
                                            // Handle holiday pay - use the same calculation as the Holiday column for consistency  
                                            $holidayPay = $payBreakdownByEmployee[$detail->employee_id]['holiday_pay'] ?? $detail->holiday_pay ?? 0;
                                            
                                            // Handle rest pay - use the same calculation as the Rest column for consistency
                                            $restPayForNet = 0;
                                            $restDayBreakdown = [];
                                            $totalRestRegularHours = 0;
                                            
                                            if ($payroll->status === 'draft') {
                                                // DRAFT: Calculate rest pay dynamically using DTR data
                                                $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                                $hourlyRate = $detail->hourly_rate ?? 0; // Use calculated hourly rate from detail
                                                
                                                if (isset($employeeBreakdown['rest_day'])) {
                                                    $restBreakdown = $employeeBreakdown['rest_day'];
                                                    $rateConfig = $restBreakdown['rate_config'];
                                                    if ($rateConfig) {
                                                        $regularMultiplier = $rateConfig->regular_rate_multiplier ?? 1.3;
                                                        $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.69;
                                                        
                                                        // Apply per-minute calculation for rest day pay (same as Rest column)
                                                        $timeLogInstance = new \App\Models\TimeLog();
                                                        $regularRestPay = $timeLogInstance->calculatePerMinuteAmount($hourlyRate, $regularMultiplier, ($restBreakdown['regular_hours'] ?? 0));
                                                        $overtimeRestPay = $timeLogInstance->calculatePerMinuteAmount($hourlyRate, $overtimeMultiplier, ($restBreakdown['overtime_hours'] ?? 0));
                                                        
                                                        $restPayForNet = $regularRestPay + $overtimeRestPay;
                                                    }
                                                }
                                            } else {
                                                // PROCESSING/APPROVED: Use breakdown data from snapshot
                                                if ($detail->earnings_breakdown) {
                                                    $earningsBreakdown = json_decode($detail->earnings_breakdown, true);
                                                    $restDetails = $earningsBreakdown['rest'] ?? [];
                                                    $restPayForNet = 0;
                                                    foreach ($restDetails as $restData) {
                                                        $restPayForNet += is_array($restData) ? ($restData['amount'] ?? $restData) : $restData;
                                                    }
                                                }
                                            }
                                            
                                            // Handle overtime pay - use the SAME calculation as the Overtime column for consistency
                                            $overtimePayForNet = 0;
                                            if ($payroll->status === 'draft') {
                                                // DRAFT: Calculate overtime pay using per-minute logic (same as Overtime column)
                                                $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                                $hourlyRate = $detail->hourly_rate ?? 0; // Use calculated hourly rate from detail
                                                
                                                // Calculate overtime for regular workdays
                                                if (isset($employeeBreakdown['regular_workday'])) {
                                                    $regularBreakdown = $employeeBreakdown['regular_workday'];
                                                    $overtimeHours = $regularBreakdown['overtime_hours'] ?? 0;
                                                    $rateConfig = $regularBreakdown['rate_config'];
                                                    if ($rateConfig && $overtimeHours > 0) {
                                                        $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.25;
                                                        
                                                        // Apply per-minute calculation for overtime (same as Overtime column)
                                                        $timeLogInstance = new \App\Models\TimeLog();
                                                        $overtimePayForNet += $timeLogInstance->calculatePerMinuteAmount($hourlyRate, $overtimeMultiplier, $overtimeHours);
                                                    }
                                                }
                                                
                                                // Calculate overtime for special holidays
                                                if (isset($employeeBreakdown['special_holiday'])) {
                                                    $specialBreakdown = $employeeBreakdown['special_holiday'];
                                                    $overtimeHours = $specialBreakdown['overtime_hours'] ?? 0;
                                                    $rateConfig = $specialBreakdown['rate_config'];
                                                    if ($rateConfig && $overtimeHours > 0) {
                                                        $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.69;
                                                        
                                                        // Apply per-minute calculation for overtime (same as Overtime column)
                                                        $timeLogInstance = new \App\Models\TimeLog();
                                                        $overtimePayForNet += $timeLogInstance->calculatePerMinuteAmount($hourlyRate, $overtimeMultiplier, $overtimeHours);
                                                    }
                                                }
                                                
                                                // Calculate overtime for regular holidays  
                                                if (isset($employeeBreakdown['regular_holiday'])) {
                                                    $regularHolidayBreakdown = $employeeBreakdown['regular_holiday'];
                                                    $overtimeHours = $regularHolidayBreakdown['overtime_hours'] ?? 0;
                                                    $rateConfig = $regularHolidayBreakdown['rate_config'];
                                                    if ($rateConfig && $overtimeHours > 0) {
                                                        $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 2.6;
                                                        
                                                        // Apply per-minute calculation for overtime (same as Overtime column)
                                                        $timeLogInstance = new \App\Models\TimeLog();
                                                            $overtimePayForNet += $timeLogInstance->calculatePerMinuteAmount($hourlyRate, $overtimeMultiplier, $overtimeHours);
                                                    }
                                                }
                                                
                                                // Calculate overtime for rest days
                                                if (isset($employeeBreakdown['rest_day'])) {
                                                    $restDayBreakdown = $employeeBreakdown['rest_day'];
                                                    $overtimeHours = $restDayBreakdown['overtime_hours'] ?? 0;
                                                    $rateConfig = $restDayBreakdown['rate_config'];
                                                    if ($rateConfig && $overtimeHours > 0) {
                                                        $overtimeMultiplier = $rateConfig->overtime_rate_multiplier ?? 1.69;
                                                        
                                                        // Apply per-minute calculation for overtime (same as Overtime column)
                                                        $timeLogInstance = new \App\Models\TimeLog();
                                                        $overtimePayForNet += $timeLogInstance->calculatePerMinuteAmount($hourlyRate, $overtimeMultiplier, $overtimeHours);
                                                    }
                                                }
                                            } else {
                                                // PROCESSING/APPROVED: Use breakdown data from snapshot 
                                                if ($detail->earnings_breakdown) {
                                                    $earningsBreakdown = json_decode($detail->earnings_breakdown, true);
                                                    $overtimeDetails = $earningsBreakdown['overtime'] ?? [];
                                                    foreach ($overtimeDetails as $overtimeData) {
                                                        $overtimePayForNet += is_array($overtimeData) ? ($overtimeData['amount'] ?? $overtimeData) : $overtimeData;
                                                    }
                                                }
                                            }
                                            
                                            // Calculate gross pay using EXACT SAME logic and variables as Gross Pay column
                                            // This ensures Net Pay breakdown shows SAME gross amount as Gross Pay column
                                            $calculatedGrossPayForNet = $calculatedGrossPay; // Use the SAME gross calculation from Gross Pay column
                                            
                                            // For processing/approved payrolls with snapshots, use the EXACT SAME logic as deduction column
                                            if (!isset($isDynamic) || !$isDynamic) {
                                                // Use snapshot data - EXACT SAME logic as deduction column
                                                if (isset($detail->deduction_breakdown) && is_array($detail->deduction_breakdown) && !empty($detail->deduction_breakdown)) {
                                                    // Sum up snapshot breakdown amounts
                                                    foreach ($detail->deduction_breakdown as $deduction) {
                                                        $detailDeductionTotal += $deduction['amount'] ?? 0;
                                                    }
                                                } elseif($employeeSnapshot && $employeeSnapshot->deductions_breakdown) {
                                                    // Use employee snapshot breakdown
                                                    $deductionsBreakdown = is_string($employeeSnapshot->deductions_breakdown) 
                                                        ? json_decode($employeeSnapshot->deductions_breakdown, true) 
                                                        : $employeeSnapshot->deductions_breakdown;
                                                    if (is_array($deductionsBreakdown)) {
                                                        foreach($deductionsBreakdown as $code => $deductionData) {
                                                            $amount = $deductionData['amount'] ?? $deductionData;
                                                            $detailDeductionTotal += $amount;
                                                        }
                                                    }
                                                } else {
                                                    // Fallback to individual fields - SAME as deduction column
                                                    $detailDeductionTotal += $detail->sss_contribution ?? 0;
                                                    $detailDeductionTotal += $detail->philhealth_contribution ?? 0;
                                                    $detailDeductionTotal += $detail->pagibig_contribution ?? 0;
                                                    $detailDeductionTotal += $detail->withholding_tax ?? 0;
                                                    $detailDeductionTotal += $detail->cash_advance_deductions ?? 0;
                                                    $detailDeductionTotal += $detail->other_deductions ?? 0;
                                                }
                                            } elseif(isset($isDynamic) && $isDynamic && isset($deductionSettings) && $deductionSettings->isNotEmpty()) {
                                                // Use dynamic calculation with SAME variables as deduction column
                                                foreach($deductionSettings as $setting) {
                                                    // Check if this deduction setting applies to this employee's benefit status - SAME AS DEDUCTIONS COLUMN
                                                    if (!$setting->appliesTo($detail->employee)) {
                                                        continue; // Skip this setting for this employee
                                                    }
                                                    
                                                    // Use same variable mapping as deduction column calculation
                                                    $basicPayForDeduction = $payBreakdownByEmployee[$detail->employee_id]['basic_pay'] ?? $detail->basic_pay ?? 0;
                                                    // Use the CALCULATED gross pay from the Gross Pay column instead of stored value
                                                    $grossPayForDeduction = $calculatedGrossPay;
                                                    $overtimePayForDeduction = $detail->overtime_pay ?? 0;
                                                    $allowancesForDeduction = $detail->allowances ?? 0;
                                                    $bonuses = $detail->bonuses ?? 0;
                                                    
                                                    // Auto-detect pay frequency from payroll period using dynamic pay schedule settings
                                                    $payFrequency = \App\Models\PayScheduleSetting::detectPayFrequencyFromPeriod(
                                                        $payroll->period_start,
                                                        $payroll->period_end
                                                    );
                                                    
                                                    $calculatedAmount = $setting->calculateDeduction(
                                                        $basicPayForDeduction, 
                                                        $overtimePayForDeduction, 
                                                        $bonuses, 
                                                        $allowancesForDeduction, 
                                                        $grossPayForDeduction,
                                                        $taxableIncome,  // Pass calculated taxable income
                                                        null, // netPay (not used for now)
                                                        $detail->employee->calculateMonthlyBasicSalary($payroll->period_start, $payroll->period_end), // monthlyBasicSalary - DYNAMIC
                                                        $payFrequency // Pass auto-detected pay frequency
                                                    );
                                                    
                                                    // Apply deduction distribution logic to match backend calculations
                                                    if ($calculatedAmount > 0) {
                                                        $calculatedAmount = $setting->calculateDistributedAmount(
                                                            $calculatedAmount,
                                                            $payroll->period_start,
                                                            $payroll->period_end,
                                                            $detail->employee->pay_schedule ?? $payFrequency,
                                                            $payroll->pay_schedule ?? null
                                                        );
                                                    }
                                                    
                                                    $detailDeductionTotal += $calculatedAmount;
                                                }
                                                // Add cash advance deductions for dynamic payrolls
                                                $detailDeductionTotal += $detail->cash_advance_deductions ?? 0;
                                            } else {
                                                // Use stored values for non-dynamic payrolls - EXACT SAME logic as deduction column
                                                $detailDeductionTotal += $detail->sss_contribution ?? 0;
                                                $detailDeductionTotal += $detail->philhealth_contribution ?? 0;
                                                $detailDeductionTotal += $detail->pagibig_contribution ?? 0;
                                                $detailDeductionTotal += $detail->withholding_tax ?? 0;
                                                $detailDeductionTotal += $detail->cash_advance_deductions ?? 0;
                                                $detailDeductionTotal += $detail->other_deductions ?? 0;
                                            }
                                            
                                            $calculatedNetPay = $calculatedGrossPayForNet - $detailDeductionTotal;
                                        @endphp
                                        
                                        <!-- Show Net Pay Breakdown -->
                                        <div class="space-y-1">
                                            @if($calculatedNetPay > 0)
                                               
                                                    <div class="text-xs text-gray-500">
                                                        <span>Gross:</span>
                                                        <span>₱{{ number_format($calculatedGrossPayForNet, 2) }}</span>
                                                    </div>
                                                    <div class="text-xs text-gray-500">
                                                        <span>Deduct:</span>
                                                        <span>₱{{ number_format($detailDeductionTotal, 2) }}</span>
                                                    </div>
                                              
                                            @endif
                                            <div class="font-bold text-purple-600 net-pay-amount" data-net-amount="{{ $calculatedNetPay }}">₱{{ number_format($calculatedNetPay, 2) }}</div>
                                        </div>
                                    </td>
                                    {{-- @if($payroll->status == 'approved')
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex space-x-2">
                                            @can('view payslips')
                                            <a href="{{ route('payslips.show', $detail) }}" 
                                               class="text-indigo-600 hover:text-indigo-900 text-xs">View</a>
                                            @endcan
                                            @can('download payslips')
                                            <a href="{{ route('payslips.download', $detail) }}" 
                                               class="text-blue-600 hover:text-blue-900 text-xs">PDF</a>
                                            @endcan
                                            @canany(['email payslip'], [auth()->user()])
                                                @if(auth()->user()->hasAnyRole(['System Administrator', 'HR Head', 'HR Staff']))
                                                    <button type="button" 
                                                            class="text-green-600 hover:text-green-900 text-xs"
                                                            onclick="emailIndividualPayslip('{{ $detail->id }}', '{{ $detail->employee->first_name }} {{ $detail->employee->last_name }}', '{{ $detail->employee->user->email ?? 'No email' }}')">
                                                        Email
                                                    </button>
                                                @endif
                                            @endcanany
                                        </div>
                                    </td>
                                    @endif --}}
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- JavaScript to update all summary boxes to match their respective column totals -->
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    // Helper function to format currency
                    function formatCurrency(amount) {
                        return '₱' + amount.toLocaleString('en-PH', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                    
                    // Calculate and update Total Basic (matches Basic column)
                    const basicPayElements = document.querySelectorAll('.basic-pay-amount');
                    let totalBasic = 0;
                    basicPayElements.forEach(function(element) {
                        const basicAmount = parseFloat(element.getAttribute('data-basic-amount')) || 0;
                        totalBasic += basicAmount;
                    });
                    const totalBasicDisplay = document.getElementById('totalBasicDisplay');
                    if (totalBasicDisplay) {
                        totalBasicDisplay.textContent = formatCurrency(totalBasic);
                    }
                    
                    // Calculate and update Total Holiday (matches Holiday column)
                    const holidayPayElements = document.querySelectorAll('.holiday-pay-amount');
                    let totalHoliday = 0;
                    holidayPayElements.forEach(function(element) {
                        const holidayAmount = parseFloat(element.getAttribute('data-holiday-amount')) || 0;
                        totalHoliday += holidayAmount;
                    });
                    const totalHolidayDisplay = document.getElementById('totalHolidayDisplay');
                    if (totalHolidayDisplay) {
                        totalHolidayDisplay.textContent = formatCurrency(totalHoliday);
                    }
                    
                    // Calculate and update Total Rest (matches Rest column)
                    const restPayElements = document.querySelectorAll('.rest-pay-amount');
                    let totalRest = 0;
                    restPayElements.forEach(function(element) {
                        const restAmount = parseFloat(element.getAttribute('data-rest-amount')) || 0;
                        totalRest += restAmount;
                    });
                    const totalRestDisplay = document.getElementById('totalRestDisplay');
                    if (totalRestDisplay) {
                        totalRestDisplay.textContent = formatCurrency(totalRest);
                    }
                    
                    // Calculate and update Total Deductions (matches Deductions column)
                    const deductionElements = document.querySelectorAll('.deduction-amount');
                    let totalDeductions = 0;
                    deductionElements.forEach(function(element) {
                        const deductionAmount = parseFloat(element.getAttribute('data-deduction-amount')) || 0;
                        totalDeductions += deductionAmount;
                    });
                    const totalDeductionsDisplay = document.getElementById('totalDeductionsDisplay');
                    if (totalDeductionsDisplay) {
                        totalDeductionsDisplay.textContent = formatCurrency(totalDeductions);
                    }
                    
                    // Calculate and update Total Gross (matches Gross Pay column)
                    const grossPayElements = document.querySelectorAll('.gross-pay-amount');
                    let totalGross = 0;
                    grossPayElements.forEach(function(element) {
                        const grossAmount = parseFloat(element.getAttribute('data-gross-amount')) || 0;
                        totalGross += grossAmount;
                    });
                    const totalGrossDisplay = document.getElementById('totalGrossDisplay');
                    if (totalGrossDisplay) {
                        totalGrossDisplay.textContent = formatCurrency(totalGross);
                    }
                    
                    // Calculate and update Total Net (matches Net Pay column)
                    const netPayElements = document.querySelectorAll('.net-pay-amount');
                    let totalNet = 0;
                    netPayElements.forEach(function(element) {
                        const netAmount = parseFloat(element.getAttribute('data-net-amount')) || 0;
                        totalNet += netAmount;
                    });
                    const totalNetDisplay = document.getElementById('totalNetDisplay');
                    if (totalNetDisplay) {
                        totalNetDisplay.textContent = formatCurrency(totalNet);
                    }
                });
            </script>

            <!-- DTR Summary for Period -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            DTR Summary: {{ \Carbon\Carbon::parse($payroll->period_start)->format('M d') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('M d, Y') }}
                        </h3>
                        <div class="flex space-x-2">
                            @can('create time logs')
                                @php
                                    $firstEmployee = $payroll->payrollDetails->first();
                                    $hasSchedule = $firstEmployee && $firstEmployee->employee && $firstEmployee->employee->timeSchedule;
                                @endphp
                                
                                @if(!$hasSchedule)
                                    {{-- No schedule assigned - lock the button --}}
                                    <span class="bg-gray-400 text-white font-bold py-2 px-4 rounded text-sm flex items-center cursor-not-allowed opacity-50" 
                                          title="Employee has no time schedule assigned">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                        No Schedule Assigned
                                    </span>
                                @elseif($payroll->payrollDetails->isNotEmpty() && $payroll->status === 'draft')
                                    <a href="{{ route('time-logs.create-bulk-employee', array_merge([
                                        'employee_id' => $payroll->payrollDetails->first()->employee_id,
                                        'period_start' => $payroll->period_start->format('Y-m-d'),
                                        'period_end' => $payroll->period_end->format('Y-m-d'),
                                        'payroll_id' => $payroll->id
                                    ], isset($schedule) ? ['schedule' => $schedule] : [])) }}" 
                                       class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded text-sm flex items-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Manage DTR
                                    </a>
                                @elseif($payroll->payrollDetails->isNotEmpty() && $payroll->status !== 'draft')
                                    <span class="bg-gray-400 text-white font-bold py-2 px-4 rounded text-sm flex items-center cursor-not-allowed opacity-50">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                        </svg>
                                        DTR Locked
                                    </span>
                                @endif
                            @endcan
                        </div>
                    </div>
                    
                    <!-- DTR Summary Legends -->
                    <div class="mb-4 p-3 bg-gray-50 rounded-lg">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Time Period Legend:</h4>
                        <div class="flex flex-wrap gap-4 text-xs">
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-green-600 mr-2"></div>
                                <span class="text-green-600 font-medium">Regular Hours</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-orange-600 mr-2"></div>
                                <span class="text-orange-600 font-medium">Regular OT Hours</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-purple-600 mr-2"></div>
                                <span class="text-purple-600 font-medium">OT + ND Hours</span>
                            </div>
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-blue-600 mr-2"></div>
                                <span class="text-blue-600 font-medium">Regular + ND Hours</span>
                            </div>

                             @php
                            // Get break time description for the first employee (assuming all employees have similar break configuration)
                            $breakTimeDescription = 'Not Set';
                            $firstEmployee = $payroll->payrollDetails->first();
                            
                            if ($firstEmployee && $firstEmployee->employee && $firstEmployee->employee->timeSchedule) {
                                $timeSchedule = $firstEmployee->employee->timeSchedule;
                                
                                // Check if employee has flexible break (break_duration_minutes without fixed times)
                                if ($timeSchedule->break_duration_minutes && $timeSchedule->break_duration_minutes > 0 && !($timeSchedule->break_start && $timeSchedule->break_end)) {
                                    // Flexible break
                                    $breakMinutes = $timeSchedule->break_duration_minutes;
                                    $breakHours = floor($breakMinutes / 60);
                                    $breakMins = $breakMinutes % 60;
                                    
                                    if ($breakHours > 0 && $breakMins > 0) {
                                        $breakTimeDescription = "Flexible {$breakHours}h {$breakMins}m";
                                    } elseif ($breakHours > 0) {
                                        $breakTimeDescription = "Flexible {$breakHours}h";
                                    } else {
                                        $breakTimeDescription = "Flexible {$breakMins}m";
                                    }
                                } elseif ($timeSchedule->break_start && $timeSchedule->break_end) {
                                    // Fixed break - check if any employee has actual break logs
                                    $hasActualBreakLogs = false;
                                    $actualBreakStart = '';
                                    $actualBreakEnd = '';
                                    
                                    // Check the first few time logs to see if there are actual break logs
                                    foreach ($payroll->payrollDetails->take(5) as $detail) {
                                        foreach ($periodDates as $date) {
                                            $timeLogData = $dtrData[$detail->employee_id][$date] ?? null;
                                            if ($timeLogData) {
                                                $timeLog = is_array($timeLogData) ? (object) $timeLogData : $timeLogData;
                                                if (isset($timeLog->break_in) && isset($timeLog->break_out) && $timeLog->break_in && $timeLog->break_out) {
                                                    $hasActualBreakLogs = true;
                                                    $actualBreakStart = \Carbon\Carbon::parse($timeLog->break_in)->format('g:i A');
                                                    $actualBreakEnd = \Carbon\Carbon::parse($timeLog->break_out)->format('g:i A');
                                                    break 2; // Break out of both loops
                                                }
                                            }
                                        }
                                    }
                                    
                                    if ($hasActualBreakLogs) {
                                        // Show actual break logs
                                        $breakTimeDescription = "Fixed {$actualBreakStart} - {$actualBreakEnd}";
                                    } else {
                                        // Show default schedule break times
                                        $defaultBreakStart = $timeSchedule->break_start->format('g:i A');
                                        $defaultBreakEnd = $timeSchedule->break_end->format('g:i A');
                                        $breakTimeDescription = "Fixed {$defaultBreakStart} - {$defaultBreakEnd}";
                                    }
                                } else {
                                    $breakTimeDescription = 'No Break';
                                }
                            }
                        @endphp
                            <div class="flex items-center">
                                <div class="w-3 h-3 rounded-full bg-red-600 mr-2"></div>
                                <span class="text-red-600 font-medium">Break Time: {{$breakTimeDescription}} </span>
                            </div>
                        </div>
                        
      
                    <div class="overflow-x-auto">
                        <style>
                            .dtr-table {
                                font-size: 0.75rem;
                            }
                            .dtr-table td, .dtr-table th {
                                white-space: nowrap;
                            }
                            .employee-column {
                                min-width: 150px;
                                max-width: 200px;
                            }
                            .date-column {
                                min-width: 90px;
                                max-width: 120px;
                            }
                        </style>
                        <table class="min-w-full divide-y divide-gray-200 dtr-table">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider sticky left-0 bg-gray-50 z-10 employee-column">
                                        Employee
                                    </th>
                                    @foreach($periodDates as $date)
                                    <th class="px-2 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider date-column">
                                        {{ \Carbon\Carbon::parse($date)->format('M d') }}
                                        <br>
                                        <span class="text-gray-400 normal-case">{{ \Carbon\Carbon::parse($date)->format('D') }}</span>
                                    </th>
                                    @endforeach
                                    {{-- <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Total<br>Hours
                                    </th>
                                    <th class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Overtime<br>Hours
                                    </th> --}}
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($payroll->payrollDetails as $detail)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-4 text-sm font-medium text-gray-900 sticky left-0 bg-white z-10 border-r">
                                        <div>
                                            {{ $detail->employee->user->name }}
                                            @php
                                                $daySchedule = $detail->employee->daySchedule;
                                                $timeSchedule = $detail->employee->timeSchedule;
                                            @endphp
                                            @if($daySchedule && $timeSchedule)
                                                @php
                                                    // Calculate working days for the payroll period
                                                    $workingDays = $detail->employee->getWorkingDaysForPeriod($payroll->period_start, $payroll->period_end);
                                                    $totalHours = $timeSchedule->total_hours ?? 8; // Get from time schedule or default to 8
                                                @endphp
                                                <div class="text-xs text-gray-600">{{ $daySchedule->days_display }} ({{ $workingDays }}days)</div>
                                                <div class="text-xs text-gray-600">{{ $timeSchedule->time_range_display }} ({{ $totalHours }}h)</div>
                                            @else
                                                <div class="text-xs text-gray-600">No schedule assigned</div>
                                            @endif
                                            <div class="text-xs text-blue-600">
                                                @php
                                                    $employee = $detail->employee;
                                                    $rateDisplay = '';
                                                    
                                                    if($employee->fixed_rate && $employee->rate_type) {
                                                        switch($employee->rate_type) {
                                                            case 'monthly':
                                                                // Monthly rate - show per day based on full month working days (not payroll period)
                                                                $monthStart = $payroll->period_start->copy()->startOfMonth();
                                                                $monthEnd = $payroll->period_start->copy()->endOfMonth();
                                                                $monthlyWorkingDays = $employee->getWorkingDaysForPeriod($monthStart, $monthEnd);
                                                                $dailyRate = $monthlyWorkingDays > 0 ? ($employee->fixed_rate / $monthlyWorkingDays) : 0;
                                                                $rateDisplay = '₱' . number_format($dailyRate, 2) . '/day (' . $monthlyWorkingDays . ' days/month)';
                                                                break;
                                                            case 'semi_monthly':
                                                            case 'semi-monthly':
                                                                // Semi-monthly rate - count actual working days in the specific cutoff period
                                                                $payrollStartDay = $payroll->period_start->day;
                                                                $currentMonth = $payroll->period_start->copy();
                                                                
                                                                if ($payrollStartDay <= 15) {
                                                                    // First cutoff (1st - 15th)
                                                                    $cutoffStart = $currentMonth->copy()->setDay(1);
                                                                    $cutoffEnd = $currentMonth->copy()->setDay(15);
                                                                    $cutoffLabel = '1st-15th';
                                                                } else {
                                                                    // Second cutoff (16th - EOD)
                                                                    $cutoffStart = $currentMonth->copy()->setDay(16);
                                                                    $cutoffEnd = $currentMonth->copy()->endOfMonth();
                                                                    $cutoffLabel = '16th-EOD';
                                                                }
                                                                
                                                                $semiMonthlyWorkingDays = $employee->getWorkingDaysForPeriod($cutoffStart, $cutoffEnd);
                                                                $dailyRate = $semiMonthlyWorkingDays > 0 ? ($employee->fixed_rate / $semiMonthlyWorkingDays) : 0;
                                                                $rateDisplay = '₱' . number_format($dailyRate, 2) . '/day (' . $semiMonthlyWorkingDays . ' days/' . $cutoffLabel . ')';
                                                                break;
                                                            case 'weekly':
                                                                // Weekly rate - show per day based on actual working days per week
                                                                $weekStart = $payroll->period_start->copy()->startOfWeek();
                                                                $weekEnd = $payroll->period_start->copy()->endOfWeek();
                                                                $weeklyWorkingDays = $employee->getWorkingDaysForPeriod($weekStart, $weekEnd);
                                                                $dailyRate = $weeklyWorkingDays > 0 ? ($employee->fixed_rate / $weeklyWorkingDays) : 0;
                                                                $rateDisplay = '₱' . number_format($dailyRate, 2) . '/day (' . $weeklyWorkingDays . ' days/week)';
                                                                break;
                                                            case 'daily':
                                                                // Daily rate - show per hour based on assigned time schedule
                                                                $timeSchedule = $employee->timeSchedule;
                                                                $dailyHours = $timeSchedule ? $timeSchedule->total_hours : 8;
                                                                $hourlyRate = $dailyHours > 0 ? ($employee->fixed_rate / $dailyHours) : 0;
                                                                $rateDisplay = '₱' . number_format($hourlyRate, 2) . '/hr (' . $dailyHours . 'h)';
                                                                break;
                                                            case 'hourly':
                                                                // Hourly rate - show per minute
                                                                $minuteRate = $employee->fixed_rate / 60;
                                                                $rateDisplay = '₱' . number_format($minuteRate, 4) . '/min';
                                                                break;
                                                            default:
                                                                // Fallback to hourly rate from database if available
                                                                if($detail->hourly_rate) {
                                                                    $rateDisplay = '₱' . number_format($detail->hourly_rate, 2) . '/hr';
                                                                }
                                                                break;
                                                        }
                                                    } elseif($detail->hourly_rate) {
                                                        // Fallback to hourly rate from database
                                                        $rateDisplay = '₱' . number_format($detail->hourly_rate, 2) . '/hr';
                                                    }
                                                @endphp
                                                {{ $rateDisplay }}
                                            </div>
                                        </div>
                                    </td>
                                    @php 
                                        $totalEmployeeHours = 0; 
                                        $totalEmployeeOvertimeHours = 0;
                                    @endphp
                                    @foreach($periodDates as $date)
                                    @php 
                                        $timeLogData = $dtrData[$detail->employee_id][$date] ?? null;
                                        
                                        // Ensure we have a proper object
                                        if ($timeLogData) {
                                            if (is_array($timeLogData)) {
                                                // Convert array to object
                                                $timeLog = (object) $timeLogData;
                                            } elseif (is_object($timeLogData)) {
                                                $timeLog = $timeLogData;
                                            } else {
                                                $timeLog = null;
                                            }
                                        } else {
                                            $timeLog = null;
                                        }
                                        
                                        // Exclude incomplete records from hour calculation
                                        $isIncompleteRecord = $timeLog && (
                                            (isset($timeLog->remarks) && $timeLog->remarks === 'Incomplete Time Record') || 
                                            (!isset($timeLog->time_in) || !isset($timeLog->time_out) || !$timeLog->time_in || !$timeLog->time_out)
                                        );
                                        
                                        // For draft payrolls, FORCE use live calculation from timeBreakdowns (same as Employee Payroll Details)
                                        if (!$isIncompleteRecord && $timeLog && $payroll->status === 'draft') {
                                            // UNIFIED CALCULATION - Use SAME approach as Employee Payroll Details OVERTIME column
                                            $employeeBreakdown = $timeBreakdowns[$detail->employee_id] ?? [];
                                            
                                            // Find breakdown for this specific day using the EXACT same logic as Employee Payroll Details
                                            $dayBreakdown = null;
                                            
                                            // First try to find in daily logs (if they exist)
                                            foreach ($employeeBreakdown as $dayType => $breakdown) {
                                                if (isset($breakdown['logs']) && is_array($breakdown['logs'])) {
                                                    foreach ($breakdown['logs'] as $log) {
                                                        if ($log['date'] === $date) {
                                                            $dayBreakdown = $log;
                                                            break 2;
                                                        }
                                                    }
                                                }
                                            }
                                            
                                            // If no daily logs found, check if we can determine day type and use aggregate
                                            if (!$dayBreakdown && $timeLog) {
                                                // Determine day type for this date
                                                $isWeekend = \Carbon\Carbon::parse($date)->isWeekend();
                                                $logType = $timeLog->log_type ?? ($isWeekend ? 'rest_day' : 'regular_workday');
                                                
                                                // For DTR Summary, ALWAYS calculate dynamic values with grace periods
                                                // This ensures consistent display between draft and processing payrolls
                                                if ($timeLog->time_in && $timeLog->time_out && $timeLog->remarks !== 'Incomplete Time Record') {
                                                    // Calculate dynamic values on-the-fly for DTR display
                                                    $controller = app(App\Http\Controllers\PayrollController::class);
                                                    $reflection = new ReflectionClass($controller);
                                                    $method = $reflection->getMethod('calculateTimeLogHoursDynamically');
                                                    $method->setAccessible(true);
                                                    $dynamicCalc = $method->invoke($controller, $timeLog);
                                                    
                                                    $regularHours = $dynamicCalc['regular_hours'] ?? 0;
                                                    $overtimeHours = $dynamicCalc['overtime_hours'] ?? 0;
                                                } else {
                                                    // Fallback for incomplete records
                                                    $regularHours = $timeLog->regular_hours ?? 0;
                                                    $overtimeHours = $timeLog->overtime_hours ?? 0;
                                                }
                                            } else if ($dayBreakdown) {
                                                // Use the daily breakdown
                                                $regularHours = $dayBreakdown['regular_hours'] ?? 0;
                                                $overtimeHours = $dayBreakdown['overtime_hours'] ?? 0;
                                            } else {
                                                // If no breakdown found, use 0 (don't fall back to stored values)
                                                $regularHours = 0;
                                                $overtimeHours = 0;
                                            }
                                        } else {
                                            // Always use dynamic calculation if available, otherwise stored values
                                            $regularHours = (!$isIncompleteRecord && $timeLog) ? 
                                                (isset($timeLog->dynamic_regular_hours) ? $timeLog->dynamic_regular_hours : ($timeLog->regular_hours ?? 0)) : 0;
                                            $overtimeHours = (!$isIncompleteRecord && $timeLog) ? 
                                                (isset($timeLog->dynamic_overtime_hours) ? $timeLog->dynamic_overtime_hours : ($timeLog->overtime_hours ?? 0)) : 0;
                                        }
                                        $totalEmployeeHours += $regularHours;
                                        $totalEmployeeOvertimeHours += $overtimeHours;
                                        $isWeekend = \Carbon\Carbon::parse($date)->isWeekend();
                                        
                                        // Check if this date is a rest day for this employee based on their schedule
                                        $isEmployeeRestDay = false;
                                        if ($detail->employee->daySchedule) {
                                            $isEmployeeRestDay = !$detail->employee->daySchedule->isWorkingDay(\Carbon\Carbon::parse($date));
                                        } else {
                                            // Fallback to weekend if no schedule assigned
                                            $isEmployeeRestDay = $isWeekend;
                                        }
                                        
                                        // Get day type for indicator
                                        $dayType = 'Regular Day';
                                        $dayTypeColor = 'bg-green-100 text-green-800';
                                        
                                        if ($timeLog) {
                                            // Get the log_type to determine day type
                                            $logType = is_array($timeLog) ? ($timeLog['log_type'] ?? null) : ($timeLog->log_type ?? null);
                                            
                                            if ($logType) {
                                                // Map log_type to display names
                                                switch ($logType) {
                                                    case 'suspension':
                                                        $dayType = 'Suspension';
                                                        $dayTypeColor = 'bg-gray-200 text-gray-800';
                                                        break;
                                                    case 'full_day_suspension':
                                                        $dayType = 'Full Suspension';
                                                        $dayTypeColor = 'bg-gray-200 text-gray-800';
                                                        break;
                                                    case 'partial_suspension':
                                                        $dayType = 'Partial Suspension';
                                                        $dayTypeColor = 'bg-gray-200 text-gray-800';
                                                        break;
                                                    case 'special_holiday':
                                                        $dayType = 'Special Holiday';
                                                        $dayTypeColor = 'bg-orange-100 text-orange-800';
                                                        break;
                                                    case 'regular_holiday':
                                                        $dayType = 'Regular Holiday';
                                                        $dayTypeColor = 'bg-red-100 text-red-800';
                                                        break;
                                                    case 'rest_day_regular_holiday':
                                                        $dayType = 'Rest + REG Holiday';
                                                        $dayTypeColor = 'bg-red-100 text-red-800';
                                                        break;
                                                    case 'rest_day_special_holiday':
                                                        $dayType = 'Rest + SPE Holiday';
                                                        $dayTypeColor = 'bg-orange-100 text-orange-800';
                                                        break;
                                                    case 'rest_day':
                                                        $dayType = 'Rest Day';
                                                        $dayTypeColor = 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'regular_workday':
                                                    default:
                                                        $dayType = 'Regular Day';
                                                        $dayTypeColor = 'bg-green-100 text-green-800';
                                                        break;
                                                }
                                            } else {
                                                // Fallback: try to get rate configuration if log_type is null
                                                if (is_object($timeLog) && method_exists($timeLog, 'getRateConfiguration')) {
                                                    $rateConfig = $timeLog->getRateConfiguration();
                                                    if ($rateConfig) {
                                                        $dayType = $rateConfig->display_name;
                                                        // Set color based on type
                                                        if (str_contains($dayType, 'Holiday')) {
                                                            $dayTypeColor = 'bg-red-100 text-red-800';
                                                        } elseif (str_contains($dayType, 'Rest')) {
                                                            $dayTypeColor = 'bg-blue-100 text-blue-800';
                                                        }
                                                    }
                                                }
                                            }
                                        } elseif ($isEmployeeRestDay) {
                                            $dayType = 'Rest Day';
                                            $dayTypeColor = 'bg-blue-100 text-blue-800';
                                        }
                                    @endphp
                                    <td class="px-2 py-4 text-xs text-center {{ $isEmployeeRestDay ? 'bg-gray-100' : '' }}">
                                        <!-- Day Type Indicator -->
                                        <div class="mb-2">
                                            <span class="inline-flex items-center px-2 py-1 text-xs font-medium rounded-full {{ $dayTypeColor }}">
                                                {{ $dayType }}
                                            </span>
                                        </div>
                                        
                                        @if($timeLog)
                                            @php
                                                // Check different time log conditions
                                                $hasTimeIn = isset($timeLog->time_in) && $timeLog->time_in;
                                                $hasTimeOut = isset($timeLog->time_out) && $timeLog->time_out;
                                                $isMarkedIncomplete = isset($timeLog->remarks) && $timeLog->remarks === 'Incomplete Time Record';
                                                
                                                // Determine display logic:
                                                // N/A: Both time_in and time_out are missing/null
                                                // INC: Either time_in OR time_out is missing (but not both) OR explicitly marked as incomplete
                                                $showNA = !$hasTimeIn && !$hasTimeOut;
                                                $showINC = $isMarkedIncomplete || ($hasTimeIn && !$hasTimeOut) || (!$hasTimeIn && $hasTimeOut);
                                            @endphp
                                            
                                            @if($showNA)
                                                {{-- Check if this is a suspension day with paid suspension settings --}}
                                                @php
                                                    $suspensionPayDisplay = null;
                                                    if (in_array($logType, ['suspension', 'full_day_suspension', 'partial_suspension'])) {
                                                        // Get suspension settings for this date
                                                        $suspensionSetting = \App\Models\NoWorkSuspendedSetting::where('date_from', '<=', $date)
                                                            ->where('date_to', '>=', $date)
                                                            ->where('status', 'active')
                                                            ->first();
                                                            
                                                        if ($suspensionSetting && $suspensionSetting->is_paid) {
                                                            // Check if this employee is eligible for paid suspension
                                                            $employeeHasBenefits = $detail->employee->benefits_status === 'with_benefits';
                                                            $payApplicableTo = $suspensionSetting->pay_applicable_to ?? 'all';
                                                            $shouldReceivePay = false;
                                                            
                                                            if ($payApplicableTo === 'all') {
                                                                $shouldReceivePay = true;
                                                            } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                                                                $shouldReceivePay = true;
                                                            } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                                                                $shouldReceivePay = true;
                                                            }
                                                            
                                                            if ($shouldReceivePay) {
                                                                $payRule = $suspensionSetting->pay_rule ?? 'full';
                                                                if (in_array($suspensionSetting->type, ['partial_suspension'])) {
                                                                    $suspensionPayDisplay = ($payRule === 'full') ? 'FULL PAID' : 'HALF PAID';
                                                                } else {
                                                                    $suspensionPayDisplay = ($payRule === 'full') ? 'FULL PAID' : 'HALF PAID';
                                                                }
                                                            } else {
                                                                $suspensionPayDisplay = 'NOT PAID';
                                                            }
                                                        } else {
                                                            $suspensionPayDisplay = 'NOT PAID';
                                                        }
                                                    }
                                                @endphp
                                                
                                                @if($suspensionPayDisplay)
                                                    {{-- Display suspension pay status instead of N/A --}}
                                                    <div class="text-blue-600 font-bold">{{ $suspensionPayDisplay }}</div>
                                                @else
                                                    {{-- Check if it's a holiday to display holiday pay status --}}
                                                    @php
                                                        $holidayPayDisplay = null;
                                                        if (in_array($logType, ['regular_holiday', 'special_holiday', 'rest_day_regular_holiday', 'rest_day_special_holiday'])) {
                                                            // Get holiday settings for this date
                                                            $holidaySetting = \App\Models\Holiday::where('date', $date)
                                                                ->where('is_active', true)
                                                                ->first();
                                                                
                                                            if ($holidaySetting && $holidaySetting->is_paid) {
                                                                // Check if this employee is eligible for paid holiday
                                                                $employeeHasBenefits = $detail->employee->benefits_status === 'with_benefits';
                                                                $payApplicableTo = $holidaySetting->pay_applicable_to ?? 'all';
                                                                $shouldReceivePay = false;
                                                                
                                                                if ($payApplicableTo === 'all') {
                                                                    $shouldReceivePay = true;
                                                                } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                                                                    $shouldReceivePay = true;
                                                                } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                                                                    $shouldReceivePay = true;
                                                                }
                                                                
                                                                if ($shouldReceivePay) {
                                                                    $payRule = $holidaySetting->pay_rule ?? 'full';
                                                                    $holidayPayDisplay = ($payRule === 'full') ? 'FULL PAID' : 'HALF PAID';
                                                                } else {
                                                                    $holidayPayDisplay = 'NOT PAID';
                                                                }
                                                            } else {
                                                                $holidayPayDisplay = 'NOT PAID';
                                                            }
                                                        }
                                                    @endphp
                                                    
                                                    @if($holidayPayDisplay)
                                                        {{-- Display holiday pay status instead of N/A --}}
                                                        <div class="text-blue-600 font-bold">{{ $holidayPayDisplay }}</div>
                                                    @else
                                                        {{-- Display N/A when both time_in and time_out are missing for non-holiday/non-suspension days --}}
                                                        <div class="text-gray-600 font-bold">N/A</div>
                                                    @endif
                                                @endif
                                            @elseif($showINC)
                                                {{-- Display INC for incomplete records (only one time missing or explicitly marked incomplete) --}}
                                                <div class="text-red-600 font-bold">INC</div>
                                            @else
                                                <div class="space-y-1">
                                                    {{-- Always show hours, even if 0 --}}
                                                    @if((isset($timeLog->time_in) && $timeLog->time_in) || (isset($timeLog->time_out) && $timeLog->time_out))
                                                    
                                                    {{-- Main work schedule with regular hours --}}
                                                    @php
                                                        // Calculate regular hours period end time
                                                        $regularPeriodEnd = $timeLog->time_out;
                                                        $regularPeriodStart = $timeLog->time_in;
                                                        
                                                        // If there's overtime, regular period should end when overtime starts
                                                        if($overtimeHours > 0 && $timeLog->time_in && $timeLog->time_out) {
                                                            $employee = $detail->employee;
                                                            $timeSchedule = $employee->timeSchedule;
                                                            // Fix: Combine the log date with the time_in to get the correct datetime
                                                            $actualTimeIn = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', 
                                                                $timeLog->log_date->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($timeLog->time_in)->format('H:i:s'));
                                                            
                                                            // Calculate overtime threshold from employee's time schedule
                                                            $employee = $detail->employee;
                                                            $timeSchedule = $employee->timeSchedule;
                                                            
                                                            // NEW: Use schedule-specific overtime threshold instead of global setting
                                                            $overtimeThresholdMinutes = $timeSchedule ? $timeSchedule->getOvertimeThresholdMinutes() : 480; // Default 8 hours
                                                            $baseWorkingHours = $overtimeThresholdMinutes / 60; // Convert to hours (e.g., 540 minutes = 9 hours)
                                                            
                                                            $clockHoursForRegular = $baseWorkingHours; // Use schedule-specific overtime threshold
                                                            
                                                            if ($timeSchedule && $timeSchedule->break_start && $timeSchedule->break_end) {
                                                                // Add break duration to working hours to get total clock hours
                                                                $breakDuration = $timeSchedule->break_start->diffInHours($timeSchedule->break_end);
                                                                $clockHoursForRegular = $baseWorkingHours + $breakDuration; // schedule working hours + break time
                                                            }
                                                            
                                                            // Check if employee is within grace period by comparing actual time_in with scheduled time
                                                            $scheduledStart = $timeSchedule ? 
                                                                \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', 
                                                                    $timeLog->log_date->format('Y-m-d') . ' ' . $timeSchedule->time_in->format('H:i') . ':00') :
                                                                \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' 08:00');
                                                            
                                                            // Check if employee is within 15-minute grace period
                                                            // If actualTimeIn is after scheduledStart, calculate minutes late
                                                            $minutesLate = 0;
                                                            if ($actualTimeIn > $scheduledStart) {
                                                                $minutesLate = $scheduledStart->diffInMinutes($actualTimeIn);
                                                            }
                                                            
                                                            // Get grace period setting from database instead of hardcoding
                                                            $gracePeriodSettings = \App\Models\GracePeriodSetting::current();
                                                            $lateGracePeriodMinutes = $gracePeriodSettings ? $gracePeriodSettings->late_grace_minutes : 15;
                                                            
                                                            // Check if employee is within the configured grace period
                                                            $isFullWorkingDay = $timeLog->time_in && $timeLog->time_out;
                                                            $isWithinGracePeriod = ($minutesLate <= $lateGracePeriodMinutes) && $isFullWorkingDay;
                                                            
                                                            if ($isWithinGracePeriod) {
                                                                // Grace period applied - use scheduled end time (e.g., 5:00 PM)
                                                                $regularPeriodEnd = $scheduledStart->copy()->addHours($clockHoursForRegular);
                                                            } else {
                                                                // Employee was truly late or beyond grace - extend period to compensate for late minutes
                                                                // For 8:16 AM (16 min late), they work until 5:16 PM to complete 8 hours
                                                                $regularPeriodEnd = $actualTimeIn->copy()->addHours($clockHoursForRegular);
                                                            }
                                                        } else {
                                                            $regularPeriodEnd = $timeLog->time_out ? \Carbon\Carbon::parse($timeLog->time_out)->format('g:i A') : 'N/A';
                                                        }
                                                        
                                                        // Format the regularPeriodEnd if it's a Carbon instance
                                                        if ($regularPeriodEnd instanceof \Carbon\Carbon) {
                                                            $regularPeriodEnd = $regularPeriodEnd->format('g:i A');
                                                        }
                                                        
                                                        // FOR DTR SUMMARY: ALWAYS use the dynamic calculation results to ensure consistency
                                                        // Force dynamic calculation for DTR display regardless of payroll status
                                                        if ($timeLog->time_in && $timeLog->time_out && $timeLog->remarks !== 'Incomplete Time Record') {
                                                            // Calculate dynamic values on-the-fly for DTR display
                                                            $controller = app(App\Http\Controllers\PayrollController::class);
                                                            $reflection = new ReflectionClass($controller);
                                                            $method = $reflection->getMethod('calculateTimeLogHoursDynamically');
                                                            $method->setAccessible(true);
                                                            $dynamicCalc = $method->invoke($controller, $timeLog);
                                                            
                                                            // Always use the dynamic calculation for display hours
                                                            $displayRegularHours = $dynamicCalc['regular_hours'] ?? 0;
                                                            $displayOvertimeHours = $dynamicCalc['overtime_hours'] ?? 0;
                                                            
                                                            // FOR DTR SUMMARY: Always use dynamic calculation for night differential regular hours
                                                            $nightDiffRegularHours = $dynamicCalc['night_diff_regular_hours'] ?? 0;
                                                            
                                                            // Get time period breakdown early for consistent display
                                                            $forceDynamicValues = [
                                                                'regular_hours' => $dynamicCalc['regular_hours'] ?? 0,
                                                                'overtime_hours' => $dynamicCalc['overtime_hours'] ?? 0,
                                                                'regular_overtime_hours' => $dynamicCalc['regular_overtime_hours'] ?? 0,
                                                                'night_diff_overtime_hours' => $dynamicCalc['night_diff_overtime_hours'] ?? 0,
                                                                'night_diff_regular_hours' => $dynamicCalc['night_diff_regular_hours'] ?? 0,
                                                                'overtime_start_time' => $dynamicCalc['overtime_start_time'] ?? null,
                                                            ];
                                                            $timePeriodBreakdown = $timeLog->getTimePeriodBreakdown($forceDynamicValues);
                                                            
                                                            // FOR DTR SUMMARY: Use the accurate overtime start time from dynamic calculation
                                                            if ($displayOvertimeHours > 0 && isset($dynamicCalc['overtime_start_time']) && $dynamicCalc['overtime_start_time']) {
                                                                $regularPeriodEnd = $dynamicCalc['overtime_start_time']->format('g:i A');
                                                            } else {
                                                                // No overtime, regular period ends at employee time out
                                                                $regularPeriodEnd = $timeLog->time_out ? \Carbon\Carbon::parse($timeLog->time_out)->format('g:i A') : 'N/A';
                                                            }
                                                        } else {
                                                            // Fallback for incomplete records
                                                            $displayRegularHours = $regularHours;
                                                            $displayOvertimeHours = $overtimeHours;
                                                            $timePeriodBreakdown = [];
                                                            
                                                            // Fallback for night differential regular hours
                                                            if ($payroll->status === 'draft') {
                                                                $nightDiffRegularHours = $timeLog->dynamic_night_diff_regular_hours ?? 0;
                                                            } else {
                                                                $nightDiffRegularHours = $timeLog->night_diff_regular_hours ?? 0;
                                                            }
                                                        }
                                                    @endphp
                                                    
                                                    @php
                                                        // Check if this is a suspension day and display suspension pay rule
                                                        $suspensionPayDisplay = null;
                                                        if (in_array($logType, ['suspension', 'full_day_suspension', 'partial_suspension'])) {
                                                            // Get suspension settings for this date
                                                            $suspensionSetting = \App\Models\NoWorkSuspendedSetting::where('date_from', '<=', $date)
                                                                ->where('date_to', '>=', $date)
                                                                ->where('status', 'active')
                                                                ->first();
                                                                
                                                            if ($suspensionSetting && $suspensionSetting->is_paid) {
                                                                // Check if this employee is eligible for paid suspension
                                                                $employeeHasBenefits = $detail->employee->benefits_status === 'with_benefits';
                                                                $payApplicableTo = $suspensionSetting->pay_applicable_to ?? 'all';
                                                                $shouldReceivePay = false;
                                                                
                                                                if ($payApplicableTo === 'all') {
                                                                    $shouldReceivePay = true;
                                                                } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                                                                    $shouldReceivePay = true;
                                                                } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                                                                    $shouldReceivePay = true;
                                                                }
                                                                
                                                if ($shouldReceivePay) {
                                                    $payRule = $suspensionSetting->pay_rule ?? 'full';
                                                    if (in_array($suspensionSetting->type, ['partial_suspension'])) {
                                                        $suspensionPayDisplay = ($payRule === 'full') ? 'FULL PAID' : 'HALF PAID';
                                                    } else {
                                                        $suspensionPayDisplay = ($payRule === 'full') ? 'FULL PAID' : 'HALF PAID';
                                                    }
                                                } else {
                                                    $suspensionPayDisplay = 'NOT PAID';
                                                }
                                            } else {
                                                $suspensionPayDisplay = 'NOT PAID';
                                            }
                                        }
                                    @endphp
                                    
                                    @php
                                        // Check if this is a holiday day and display holiday pay rule
                                        $holidayPayDisplay = null;
                                        if (in_array($logType, ['regular_holiday', 'special_holiday', 'rest_day_regular_holiday', 'rest_day_special_holiday'])) {
                                            // Get holiday settings for this date
                                            $holidaySetting = \App\Models\Holiday::where('date', $date)
                                                ->where('is_active', true)
                                                ->first();
                                                
                                            if ($holidaySetting && $holidaySetting->is_paid) {
                                                // Check if this employee is eligible for paid holiday
                                                $employeeHasBenefits = $detail->employee->benefits_status === 'with_benefits';
                                                $payApplicableTo = $holidaySetting->pay_applicable_to ?? 'all';
                                                $shouldReceivePay = false;
                                                
                                                if ($payApplicableTo === 'all') {
                                                    $shouldReceivePay = true;
                                                } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                                                    $shouldReceivePay = true;
                                                } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                                                    $shouldReceivePay = true;
                                                }
                                                
                                                if ($shouldReceivePay) {
                                                    $payRule = $holidaySetting->pay_rule ?? 'full';
                                                    $holidayPayDisplay = ($payRule === 'full') ? 'FULL PAID' : 'HALF PAID';
                                                } else {
                                                    $holidayPayDisplay = 'NOT PAID';
                                                }
                                            } else {
                                                $holidayPayDisplay = 'NOT PAID';
                                            }
                                        }
                                    @endphp

                                    @if($suspensionPayDisplay)
                                        <div class="text-blue-600 font-bold text-xs mb-1">{{ $suspensionPayDisplay }}</div>
                                    @endif
                                    
                                    @if($holidayPayDisplay)
                                        <div class="text-blue-600 font-bold text-xs mb-1">{{ $holidayPayDisplay }}</div>
                                    @endif

                                    <div class="text-green-600 font-medium">
                                                        @php
                                                            // FOR DTR SUMMARY: Display actual time periods based on employee's time in/out and ND boundaries
                                                            $regularStart = $timeLog->time_in ? \Carbon\Carbon::parse($timeLog->time_in)->format('g:i A') : 'N/A';
                                                            $regularEnd = '';
                                                            
                                                            if ($timeLog->time_in && $timeLog->time_out) {
                                                                // Get night differential settings to determine where regular hours end
                                                                $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                                
                                                                if ($nightDiffSetting && $nightDiffSetting->is_active && $nightDiffRegularHours > 0) {
                                                                    // If employee works into ND period, regular hours end at ND start
                                                                    $nightStart = \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $nightDiffSetting->start_time);
                                                                    $regularEnd = $nightStart->format('g:i A');
                                                                } else {
                                                                    // If no ND hours, regular period ends at calculated regular period end (not employee's actual time out)
                                                                    // Use the regularPeriodEnd calculated earlier which considers grace periods and scheduled hours
                                                                    if (is_string($regularPeriodEnd)) {
                                                                        $regularEnd = $regularPeriodEnd;
                                                                    } else {
                                                                        $regularEnd = $regularPeriodEnd->format('g:i A');
                                                                    }
                                                                }
                                                            } else {
                                                                $regularEnd = $regularPeriodEnd;
                                                            }
                                                        @endphp
                                                        {{ $regularStart }} - {{ $regularEnd }}
                                                        @if($displayRegularHours > 0)
                                                            {{-- (regular hours period) --}}
                                                        @endif
                                                        ({{ number_format($displayRegularHours * 60, 0) }}m) {{ floor($displayRegularHours) }}h {{ round(($displayRegularHours - floor($displayRegularHours)) * 60) }}m
                                                    </div>
                                                    
                                                    {{-- Display Night Differential Regular Hours --}}
                                                    @if($nightDiffRegularHours > 0)
                                                    @php
                                                        // Calculate night differential regular period times
                                                        $nightRegularStart = '';
                                                        $nightRegularEnd = '';
                                                        
                                                        if ($timeLog->time_out && $timeLog->time_in) {
                                                            $workStart = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', 
                                                                $timeLog->log_date->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($timeLog->time_in)->format('H:i:s'));
                                                            $workEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', 
                                                                $timeLog->log_date->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($timeLog->time_out)->format('H:i:s'));
                                                            
                                                            // Get night differential settings
                                                            $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                            if ($nightDiffSetting && $nightDiffSetting->is_active) {
                                                                $nightStart = \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $nightDiffSetting->start_time);
                                                                $nightEnd = \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $nightDiffSetting->end_time);
                                                                
                                                                // Handle next day end time (e.g., 10 PM to 5 AM next day)
                                                                if ($nightEnd->lte($nightStart)) {
                                                                    $nightEnd->addDay();
                                                                }
                                                                
                                                                // Handle next day night start (if before current day start)
                                                                if ($nightStart->lt($workStart)) {
                                                                    $nightStart->addDay();
                                                                }
                                                                
                                                                // ND Regular starts at max(work start, ND start)
                                                                $ndRegularStartTime = $workStart->greaterThan($nightStart) ? $workStart : $nightStart;
                                                                
                                                                // ND Regular ends at min(work end, scheduled end, overtime start, ND end)
                                                                $ndRegularEndTime = $workEnd;
                                                                
                                                                // If there's overtime, regular ND ends when OT starts
                                                                if (isset($dynamicCalc['overtime_start_time']) && $dynamicCalc['overtime_start_time']) {
                                                                    $overtimeStartTime = $dynamicCalc['overtime_start_time'];
                                                                    if ($overtimeStartTime->lessThan($ndRegularEndTime)) {
                                                                        $ndRegularEndTime = $overtimeStartTime;
                                                                    }
                                                                }
                                                                
                                                                // Also limit to ND period end
                                                                if ($nightEnd->lessThan($ndRegularEndTime)) {
                                                                    $ndRegularEndTime = $nightEnd;
                                                                }
                                                                
                                                                $nightRegularStart = $ndRegularStartTime->format('g:i A');
                                                                $nightRegularEnd = $ndRegularEndTime->format('g:i A');
                                                            }
                                                        }
                                                    @endphp
                                                    @if($nightRegularStart && $nightRegularEnd)
                                                    <div class="text-blue-600 text-xs">
                                                        {{ $nightRegularStart }} - {{ $nightRegularEnd }} ({{ number_format($nightDiffRegularHours * 60, 0) }}m) {{ floor($nightDiffRegularHours) }}h {{ round(($nightDiffRegularHours - floor($nightDiffRegularHours)) * 60) }}m
                                                    </div>
                                                    @endif
                                                    @endif
                                               
                                                    @endif
                                                    
                                                    {{-- Break schedule with break hours --}}
                                                    @php
                                                        // Calculate break duration - use logged break times OR employee's time schedule default
                                                        $breakHours = 0;
                                                        $showBreakTime = false;
                                                        $breakDisplayStart = '';
                                                        $breakDisplayEnd = '';
                                                        
                                                        if ($timeLog->break_in && $timeLog->break_out && $timeLog->time_in && $timeLog->time_out) {
                                                            // Use logged break times
                                                            $breakStart = \Carbon\Carbon::parse($timeLog->break_in);
                                                            $breakEnd = \Carbon\Carbon::parse($timeLog->break_out);
                                                            $workStart = \Carbon\Carbon::parse($timeLog->time_in);
                                                            $workEnd = \Carbon\Carbon::parse($timeLog->time_out);
                                                            
                                                            // Only show break time if employee was present during the break period
                                                            if ($breakStart >= $workStart && $breakEnd <= $workEnd) {
                                                                $breakHours = $breakEnd->diffInMinutes($breakStart) / 60;
                                                                $showBreakTime = true;
                                                                $breakDisplayStart = $breakStart->format('g:i A');
                                                                $breakDisplayEnd = $breakEnd->format('g:i A');
                                                            }
                                                            // Special case: if employee came in during break time (e.g., 1pm when break is 12pm-1pm)
                                                            elseif ($workStart >= $breakStart && $workStart < $breakEnd) {
                                                                // Employee came in during break time, so no break deduction applies
                                                                $showBreakTime = false;
                                                            }
                                                            // Special case: if employee left during break time
                                                            elseif ($workEnd > $breakStart && $workEnd <= $breakEnd) {
                                                                // Employee left during break time, only count partial break
                                                                $actualBreakStart = max($breakStart, $workStart);
                                                                $actualBreakEnd = min($breakEnd, $workEnd);
                                                                if ($actualBreakEnd > $actualBreakStart) {
                                                                    $breakHours = $actualBreakEnd->diffInMinutes($actualBreakStart) / 60;
                                                                    $showBreakTime = true;
                                                                    $breakDisplayStart = $actualBreakStart->format('g:i A');
                                                                    $breakDisplayEnd = $actualBreakEnd->format('g:i A');
                                                                }
                                                            }
                                                        } elseif ($timeLog->time_in && $timeLog->time_out) {
                                                            // Use employee's time schedule default break times when break in/out is missing
                                                            $employee = $detail->employee;
                                                            $timeSchedule = $employee->timeSchedule ?? null;
                                                            
                                                            if ($timeSchedule && $timeSchedule->break_start && $timeSchedule->break_end) {
                                                                $defaultBreakStart = \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $timeSchedule->break_start->format('H:i:s'));
                                                                $defaultBreakEnd = \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $timeSchedule->break_end->format('H:i:s'));
                                                                $workStart = \Carbon\Carbon::parse($timeLog->time_in);
                                                                $workEnd = \Carbon\Carbon::parse($timeLog->time_out);
                                                                
                                                                // Only show default break time if employee was present during the scheduled break period
                                                                if ($defaultBreakStart >= $workStart && $defaultBreakEnd <= $workEnd) {
                                                                    $breakHours = $defaultBreakEnd->diffInMinutes($defaultBreakStart) / 60;
                                                                    $showBreakTime = true;
                                                                    $breakDisplayStart = $defaultBreakStart->format('g:i A');
                                                                    $breakDisplayEnd = $defaultBreakEnd->format('g:i A');
                                                                }
                                                            }
                                                        }
                                                    @endphp
                                                    
                                                    {{-- Display break time if applicable --}}
                                                    @if($showBreakTime && $breakHours > 0)
                                                        <div class="text-red-600 text-xs">
                                                            {{ $breakDisplayStart }} - {{ $breakDisplayEnd }} ({{ number_format($breakHours * 60, 0) }}m) {{ floor($breakHours) }}h {{ round(($breakHours - floor($breakHours)) * 60) }}m
                                                        </div>
                                                    @endif
                                                
                                                    @if($displayOvertimeHours > 0)
                                                    @php
                                                        // For DTR Summary, we need to calculate overtime breakdown consistently
                                                        // Use the already calculated values from above
                                                        if ($timeLog->time_in && $timeLog->time_out && $timeLog->remarks !== 'Incomplete Time Record') {
                                                            // Use dynamic calculation results already calculated above
                                                            $regularOvertimeHours = $dynamicCalc['regular_overtime_hours'] ?? 0;
                                                            $nightDiffOvertimeHours = $dynamicCalc['night_diff_overtime_hours'] ?? 0;
                                                        } else {
                                                            // Fallback for incomplete records
                                                            if ($payroll->status === 'draft') {
                                                                $regularOvertimeHours = $timeLog->dynamic_regular_overtime_hours ?? 0;
                                                                $nightDiffOvertimeHours = $timeLog->dynamic_night_diff_overtime_hours ?? 0;
                                                            } else {
                                                                $regularOvertimeHours = $timeLog->regular_overtime_hours ?? 0;
                                                                $nightDiffOvertimeHours = $timeLog->night_diff_overtime_hours ?? 0;
                                                            }
                                                        }
                                                        
                                                        // If breakdown not available, show total
                                                        if ($regularOvertimeHours == 0 && $nightDiffOvertimeHours == 0) {
                                                            $regularOvertimeHours = $displayOvertimeHours;
                                                        }
                                                    @endphp
                                                    
                                                    {{-- Display detailed time periods --}}
                                                    @if($regularOvertimeHours > 0)
                                                    @php
                                                        // Calculate Regular OT period times
                                                        $regularOTStart = '';
                                                        $regularOTEnd = '';
                                                        
                                                        if ($timeLog->time_out && $timeLog->time_in && isset($dynamicCalc['overtime_start_time']) && $dynamicCalc['overtime_start_time']) {
                                                            $overtimeStartTime = $dynamicCalc['overtime_start_time'];
                                                            $regularOTStart = $overtimeStartTime->format('g:i A');
                                                            
                                                            $workEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', 
                                                                $timeLog->log_date->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($timeLog->time_out)->format('H:i:s'));
                                                            
                                                            // Get night differential settings to determine where regular OT ends
                                                            $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                            if ($nightDiffSetting && $nightDiffSetting->is_active && $nightDiffOvertimeHours > 0) {
                                                                $nightStart = \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $nightDiffSetting->start_time);
                                                                
                                                                // Handle next day night start
                                                                if ($nightStart->lt($overtimeStartTime)) {
                                                                    $nightStart->addDay();
                                                                }
                                                                
                                                                // Regular OT ends at night differential start or work end, whichever is earlier
                                                                $regularOTEndTime = $nightStart->lessThan($workEnd) ? $nightStart : $workEnd;
                                                                $regularOTEnd = $regularOTEndTime->format('g:i A');
                                                            } else {
                                                                // No night differential or all OT is regular - goes to work end
                                                                $regularOTEnd = $workEnd->format('g:i A');
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="text-orange-600 text-xs">
                                                        @if($regularOTStart && $regularOTEnd)
                                                            {{ $regularOTStart }} - {{ $regularOTEnd }} ({{ number_format($regularOvertimeHours * 60, 0) }}m) {{ floor($regularOvertimeHours) }}h {{ round(($regularOvertimeHours - floor($regularOvertimeHours)) * 60) }}m
                                                        @else
                                                            Regular OT: {{ number_format($regularOvertimeHours * 60, 0) }}m ({{ floor($regularOvertimeHours) }}h {{ round(($regularOvertimeHours - floor($regularOvertimeHours)) * 60) }}m)
                                                        @endif
                                                    </div>
                                                    @endif
                                                    
                                                    @if($nightDiffOvertimeHours > 0)
                                                    @php
                                                        // Calculate Night Diff OT period times
                                                        $nightOTStart = '';
                                                        $nightOTEnd = '';
                                                        
                                                        if ($timeLog->time_out && $timeLog->time_in && isset($dynamicCalc['overtime_start_time']) && $dynamicCalc['overtime_start_time']) {
                                                            $overtimeStartTime = $dynamicCalc['overtime_start_time'];
                                                            $workEnd = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', 
                                                                $timeLog->log_date->format('Y-m-d') . ' ' . \Carbon\Carbon::parse($timeLog->time_out)->format('H:i:s'));
                                                            
                                                            $nightDiffSetting = \App\Models\NightDifferentialSetting::current();
                                                            if ($nightDiffSetting && $nightDiffSetting->is_active) {
                                                                $nightStart = \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $nightDiffSetting->start_time);
                                                                $nightEnd = \Carbon\Carbon::parse($timeLog->log_date->format('Y-m-d') . ' ' . $nightDiffSetting->end_time);
                                                                
                                                                // Handle next day end time
                                                                if ($nightEnd->lte($nightStart)) {
                                                                    $nightEnd->addDay();
                                                                }
                                                                
                                                                // Handle next day night start
                                                                if ($nightStart->lt($overtimeStartTime)) {
                                                                    $nightStart->addDay();
                                                                }
                                                                
                                                                // ND OT starts at the later of: overtime start OR night differential start
                                                                $ndOTStartTime = $overtimeStartTime->greaterThan($nightStart) ? $overtimeStartTime : $nightStart;
                                                                $nightOTStart = $ndOTStartTime->format('g:i A');
                                                                
                                                                // ND OT ends at work end or night end, whichever is earlier
                                                                $ndOTEndTime = $workEnd->lessThan($nightEnd) ? $workEnd : $nightEnd;
                                                                $nightOTEnd = $ndOTEndTime->format('g:i A');
                                                            }
                                                        }
                                                    @endphp
                                                    <div class="text-purple-600 text-xs">
                                                        @if($nightOTStart && $nightOTEnd)
                                                            {{ $nightOTStart }} - {{ $nightOTEnd }} ({{ number_format($nightDiffOvertimeHours * 60, 0) }}m) {{ floor($nightDiffOvertimeHours) }}h {{ round(($nightDiffOvertimeHours - floor($nightDiffOvertimeHours)) * 60) }}m
                                                        @else
                                                            OT+ND: {{ number_format($nightDiffOvertimeHours * 60, 0) }}m ({{ floor($nightDiffOvertimeHours) }}h {{ round(($nightDiffOvertimeHours - floor($nightDiffOvertimeHours)) * 60) }}m)
                                                        @endif
                                                    </div>
                                                    @endif
                                                    @endif
                                                </div>
                                            @endif
                                        @else
                                            {{-- Display N/A when there's no record from database --}}
                                            <div class="text-gray-600 font-bold">N/A</div>
                                        @endif
                                    </td>
                                    @endforeach
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Mark as Paid Modal --}}
    <div id="markAsPaidModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" style="z-index: 50;">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-2/3 lg:w-1/2 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Mark Payroll as Paid</h3>
                    <button type="button" onclick="closeMarkAsPaidModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <form id="markAsPaidForm" method="POST" action="{{ route('payrolls.mark-as-paid', $payroll) }}" enctype="multipart/form-data">
                    @csrf
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Payment Proof (Optional)</label>
                        <input type="file" 
                               name="payment_proof[]" 
                               multiple 
                               accept=".jpg,.jpeg,.png,.pdf"
                               class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                        <p class="text-xs text-gray-500 mt-1">You can upload multiple files (JPG, PNG, PDF). Max 10MB per file.</p>
                    </div>

                    <div class="mb-6">
                        <label for="payment_notes" class="block text-sm font-medium text-gray-700 mb-2">Payment Notes (Optional)</label>
                        <textarea name="payment_notes" 
                                  id="payment_notes" 
                                  rows="3" 
                                  class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"
                                  placeholder="Add any notes about the payment..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" 
                                onclick="closeMarkAsPaidModal()"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Mark as Paid
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openMarkAsPaidModal() {
            document.getElementById('markAsPaidModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeMarkAsPaidModal() {
            document.getElementById('markAsPaidModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            // Reset form
            document.getElementById('markAsPaidForm').reset();
        }

        // Close modal when clicking outside
        document.getElementById('markAsPaidModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeMarkAsPaidModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMarkAsPaidModal();
            }
        });
        
        // Email payslip functions
        function emailIndividualPayslip(employeeId, employeeName, employeeEmail) {
            if (confirm(`Send payslip to ${employeeName} via email?\nEmail address: ${employeeEmail}`)) {
                showLoading('Sending Payslip...', `Sending payslip to ${employeeName}. Please wait while we generate and send the PDF.`);
                
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('employee_id', employeeId);
                
                fetch('{{ route("payslips.email-individual", $payroll) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        hideLoading();
                        if (response.status === 403) {
                            throw new Error('This action is unauthorized. Please check your permissions.');
                        }
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoading();
                    
                    setTimeout(() => {
                        if (data.success) {
                            alert('Individual payslip sent successfully!');
                        } else {
                            alert('Error sending payslip: ' + data.message);
                        }
                    }, 100);
                })
                .catch(error => {
                    hideLoading();
                    
                    setTimeout(() => {
                        alert('Error sending payslip: ' + error.message);
                    }, 100);
                    console.error('Error:', error);
                });
            }
        }
        
        function emailAllPayslips(employeeCount) {
            if (confirm(`Send payslips to ALL ${employeeCount} employees in this payroll via email?`)) {
                showLoading('Sending All Payslips...', `Sending payslips to all ${employeeCount} employees. This may take several minutes.`);
                
                fetch('{{ route("payslips.email-all", $payroll) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    
                    setTimeout(() => {
                        if (data.success) {
                            alert('All payslips sent successfully!');
                        } else {
                            alert('Error sending payslips: ' + data.message);
                        }
                    }, 100);
                })
                .catch(error => {
                    hideLoading();
                    
                    setTimeout(() => {
                        alert('Error sending payslips');
                    }, 100);
                    console.error('Error:', error);
                });
            }
        }
        
        // Loading helper functions
        function showLoading(title = 'Processing...', message = 'Please wait while we process your request.') {
            const loadingText = document.getElementById('loadingText');
            const loadingSubtext = document.getElementById('loadingSubtext');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            if (loadingText) loadingText.textContent = title;
            if (loadingSubtext) loadingSubtext.textContent = message;
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
                loadingOverlay.style.opacity = '1';
            }
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
                overlay.style.opacity = '0';
            }
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
    </script>
</x-app-layout>
