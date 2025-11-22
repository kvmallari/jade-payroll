<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Create DTR for') }} {{ $selectedEmployee->first_name }} {{ $selectedEmployee->last_name }}
            </h2>
            <div class="text-sm text-gray-600">
                <span class="font-medium">Period:</span> {{ $currentPeriod['period_label'] }}
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- Employee Information -->
                    <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Employee Details</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-700">Employee:</span>
                                <span class="text-gray-900">{{ $selectedEmployee->first_name }} {{ $selectedEmployee->last_name }}</span>
                                @if($selectedEmployee->benefits_status)
                                    <span class=" inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $selectedEmployee->benefits_status === 'with_benefits' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $selectedEmployee->benefits_status === 'with_benefits' ? 'Premium' : 'Basic' }}
                                    </span>
                                @endif
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Schedule:</span>
                                <span class="text-gray-900">{{ $selectedEmployee->schedule_display }}</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Department:</span>
                                <span class="text-gray-900">{{ $selectedEmployee->department->name ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm mt-2">
                            <div>
                                <span class="font-medium text-gray-700">Hourly Rate:</span>
                                <span class="text-blue-600">₱{{ number_format($selectedEmployee->hourly_rate ?? 0, 2) }}/hr</span>
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Break Type:</span>
                                @if($selectedEmployee->timeSchedule)
                                    @if($selectedEmployee->timeSchedule->break_start && $selectedEmployee->timeSchedule->break_end)
                                        <span class="text-gray-900">Fixed ({{ \Carbon\Carbon::parse($selectedEmployee->timeSchedule->break_start)->format('g:i A') }} - {{ \Carbon\Carbon::parse($selectedEmployee->timeSchedule->break_end)->format('g:i A') }})</span>
                                    @elseif($selectedEmployee->timeSchedule->break_duration_minutes)
                                        <span class="text-gray-900">Flexible ({{ $selectedEmployee->timeSchedule->break_duration_minutes }}min)</span>
                                    @else
                                        <span class="text-gray-900">No Break</span>
                                    @endif
                                @else
                                    <span class="text-gray-500">N/A</span>
                                @endif
                            </div>
                            <div>
                                <span class="font-medium text-gray-700">Period:</span>
                                <span class="text-gray-900">{{ $currentPeriod['period_label'] }}</span>
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('time-logs.store-bulk') }}" method="POST" id="dtr-form">
                        @csrf
                        <input type="hidden" name="employee_id" value="{{ $selectedEmployee->id }}">
                        @if($payrollId)
                            <input type="hidden" name="payroll_id" value="{{ $payrollId }}">
                        @endif
                        @if(isset($schedule))
                            <input type="hidden" name="schedule" value="{{ $schedule }}">
                        @endif
                        @if(request()->get('from_last_payroll'))
                            <input type="hidden" name="from_last_payroll" value="true">
                        @endif
                        <input type="hidden" name="redirect_to_payroll" value="1">

                        <!-- Bulk Actions -->
                        <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                            <h4 class="text-md font-medium text-blue-900 mb-3">Quick Fill Actions</h4>
                            <div class="flex flex-wrap gap-2">
                                @php
                                    $scheduleStart = '08:00'; // Default fallback
                                    $scheduleEnd = '17:00';   // Default fallback
                                    $scheduleBreakStart = '12:00'; // Default break start
                                    $scheduleBreakEnd = '13:00';   // Default break end
                                    
                                    // Determine break type
                                    $isFlexibleBreak = false;
                                    $isFixedBreak = false;
                                    
                                    if ($selectedEmployee->timeSchedule) {
                                        $scheduleStart = \Carbon\Carbon::parse($selectedEmployee->timeSchedule->time_in)->format('H:i');
                                        $scheduleEnd = \Carbon\Carbon::parse($selectedEmployee->timeSchedule->time_out)->format('H:i');
                                        
                                        // Check if employee has flexible break (break_duration_minutes without fixed times)
                                        if ($selectedEmployee->timeSchedule->break_duration_minutes && $selectedEmployee->timeSchedule->break_duration_minutes > 0 && !($selectedEmployee->timeSchedule->break_start && $selectedEmployee->timeSchedule->break_end)) {
                                            $isFlexibleBreak = true;
                                        } elseif ($selectedEmployee->timeSchedule->break_start && $selectedEmployee->timeSchedule->break_end) {
                                            $isFixedBreak = true;
                                            $scheduleBreakStart = \Carbon\Carbon::parse($selectedEmployee->timeSchedule->break_start)->format('H:i');
                                            $scheduleBreakEnd = \Carbon\Carbon::parse($selectedEmployee->timeSchedule->break_end)->format('H:i');
                                        }
                                    }
                                    
                                    $displayStart = \Carbon\Carbon::parse($scheduleStart)->format('g:i A');
                                    $displayEnd = \Carbon\Carbon::parse($scheduleEnd)->format('g:i A');
                                @endphp
                                
                                @if($isFlexibleBreak)
                                    <button type="button" class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600" onclick="fillTimeOnly('{{ $scheduleStart }}', '{{ $scheduleEnd }}')">
                                        Fill Time Fields ({{ $displayStart }} - {{ $displayEnd }})
                                    </button>
                                @elseif($isFixedBreak)
                                    <button type="button" class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600" onclick="fillRegularHours('{{ $scheduleStart }}', '{{ $scheduleEnd }}', '{{ $scheduleBreakStart }}', '{{ $scheduleBreakEnd }}')">
                                        Fill Time & Break Fields ({{ $displayStart }} - {{ $displayEnd }})
                                    </button>
                                @else
                                    <button type="button" class="px-3 py-1 bg-blue-500 text-white text-sm rounded hover:bg-blue-600" onclick="fillTimeOnly('{{ $scheduleStart }}', '{{ $scheduleEnd }}')">
                                        Fill Time Fields ({{ $displayStart }} - {{ $displayEnd }})
                                    </button>
                                @endif
                                
                                @if($isFlexibleBreak)
                                    {{-- For flexible employees, show Reset Time Only as default (first) --}}
                                    <button type="button" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600" onclick="resetTimeOnly()">
                                        Reset Time Only
                                    </button>
                                    <button type="button" class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600" onclick="resetAll()">
                                        Reset All
                                    </button>
                                @else
                                    {{-- For regular employees, show Reset All as default (first) --}}
                                 
                                    <button type="button" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600" onclick="resetTimeOnly()">
                                        Reset Time Only
                                    </button>
                                       <button type="button" class="px-3 py-1 bg-red-500 text-white text-sm rounded hover:bg-red-600" onclick="resetAll()">
                                        Reset All
                                    </button>
                                @endif
                            </div>
                            {{-- <p class="text-xs text-blue-700 mt-2">
                                <strong>Fill Time & Break:</strong> Fills empty fields with employee's scheduled work hours and break times.<br>
                                <strong>Reset Time Only:</strong> Clears only time fields, preserves current day types.<br>
                                <strong>Reset All:</strong> Clears all time fields AND resets day types to original defaults (Regular, Holiday, Rest Day, etc.).
                            </p> --}}
                        </div>

                        <!-- Time Log Entries -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border border-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">DATE</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">DAY</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">TIME IN</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">TIME OUT</th>
                                        @php
                                            $hasFlexibleBreak = $selectedEmployee->timeSchedule && 
                                                               $selectedEmployee->timeSchedule->break_duration_minutes && 
                                                               !$selectedEmployee->timeSchedule->break_start && 
                                                               !$selectedEmployee->timeSchedule->break_end;
                                        @endphp
                                        @if($hasFlexibleBreak)
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">BREAK</th>
                                        @else
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">BREAK IN</th>
                                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">BREAK OUT</th>
                                        @endif
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-r">TYPE</th>
                                        <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($dtrData as $index => $day)
                                        @php
                                            $suspensionInfo = $day['suspension_info'] ?? null;
                                            $isPaidSuspension = $suspensionInfo && ($suspensionInfo['is_paid'] ?? false);
                                            $isSuspension = $day['is_suspension'] ?? false;
                                            $isPartialSuspension = $suspensionInfo && ($suspensionInfo['is_partial'] ?? false);
                                            $employeeHasBenefits = $selectedEmployee->benefits_status === 'with_benefits';
                                            
                                            // Handle suspension auto-fill logic based on new requirements
                                            if ($isSuspension) {
                                                if ($isPaidSuspension) {
                                                    $payApplicableTo = $suspensionInfo['pay_applicable_to'] ?? 'all';
                                                    $shouldAutoFill = false;
                                                    
                                                    if ($payApplicableTo === 'all') {
                                                        $shouldAutoFill = true;
                                                    } elseif ($payApplicableTo === 'with_benefits' && $employeeHasBenefits) {
                                                        $shouldAutoFill = true;
                                                    } elseif ($payApplicableTo === 'without_benefits' && !$employeeHasBenefits) {
                                                        $shouldAutoFill = true;
                                                    }
                                                    
                                                    if ($shouldAutoFill) {
                                                        if ($isPartialSuspension) {
                                                            // PAID PARTIAL SUSPENSION: Preserve existing time logs if available, otherwise allow manual input
                                                            $defaultTimeIn = $day['time_in'] ? $day['time_in']->format('H:i') : null;
                                                            $defaultTimeOut = $day['time_out'] ? $day['time_out']->format('H:i') : null;
                                                            $defaultBreakIn = $day['break_in'] ? $day['break_in']->format('H:i') : null;
                                                            $defaultBreakOut = $day['break_out'] ? $day['break_out']->format('H:i') : null;
                                                        } else {
                                                            // PAID FULL DAY SUSPENSION: NO AUTO-FILL - all inputs disabled 
                                                            $defaultTimeIn = null;
                                                            $defaultTimeOut = null;
                                                            $defaultBreakIn = null;
                                                            $defaultBreakOut = null;
                                                        }
                                                    } else {
                                                        // PAID SUSPENSION BUT NOT APPLICABLE: Clear all time fields
                                                        $defaultTimeIn = null;
                                                        $defaultTimeOut = null;
                                                        $defaultBreakIn = null;
                                                        $defaultBreakOut = null;
                                                    }
                                                } else {
                                                    // UNPAID SUSPENSION: Preserve existing time logs for partial, clear for full
                                                    if ($isPartialSuspension) {
                                                        // UNPAID PARTIAL SUSPENSION: Preserve existing time logs if available
                                                        $defaultTimeIn = $day['time_in'] ? $day['time_in']->format('H:i') : null;
                                                        $defaultTimeOut = $day['time_out'] ? $day['time_out']->format('H:i') : null;
                                                        $defaultBreakIn = $day['break_in'] ? $day['break_in']->format('H:i') : null;
                                                        $defaultBreakOut = $day['break_out'] ? $day['break_out']->format('H:i') : null;
                                                    } else {
                                                        // UNPAID FULL DAY SUSPENSION: NO AUTO-FILL, disable accordingly
                                                        $defaultTimeIn = null;
                                                        $defaultTimeOut = null;
                                                        $defaultBreakIn = null;
                                                        $defaultBreakOut = null;
                                                    }
                                                }
                                            } elseif ($day['is_holiday_active'] ?? false) {
                                                // HOLIDAY LOGIC: Check if holiday is paid or unpaid
                                                $holidayInfo = $day['holiday_info'] ?? null;
                                                $isPaidHoliday = $holidayInfo && ($holidayInfo['is_paid'] ?? false);
                                                $employeeHasBenefits = $selectedEmployee->benefits_status === 'with_benefits';
                                                
                                                // HOLIDAY LOGIC: Don't auto-fill, only preserve existing time logs (like partial suspension behavior)
                                                // Users should manually enter time if they worked on holiday
                                                $defaultTimeIn = $day['time_in'] ? $day['time_in']->format('H:i') : null;
                                                $defaultTimeOut = $day['time_out'] ? $day['time_out']->format('H:i') : null;
                                                $defaultBreakIn = $day['break_in'] ? $day['break_in']->format('H:i') : null;
                                                $defaultBreakOut = $day['break_out'] ? $day['break_out']->format('H:i') : null;
                                            } else {
                                                // NOT SUSPENSION OR HOLIDAY: Use existing data as is
                                                $defaultTimeIn = $day['time_in'] ? $day['time_in']->format('H:i') : null;
                                                $defaultTimeOut = $day['time_out'] ? $day['time_out']->format('H:i') : null;
                                                $defaultBreakIn = $day['break_in'] ? $day['break_in']->format('H:i') : null;
                                                $defaultBreakOut = $day['break_out'] ? $day['break_out']->format('H:i') : null;
                                            }
                                            
                                            // For flexible break employees, determine if break was used
                                            // Default to true (used) for new entries, or check existing data
                                            $usedBreak = true; // Default to checked (break is used)
                                            if ($hasFlexibleBreak) {
                                                if (isset($day['time_log']) && $day['time_log'] && isset($day['time_log']->used_break)) {
                                                    // Check the time_log model if it has used_break field
                                                    $usedBreak = (bool) $day['time_log']->used_break;
                                                } elseif (isset($day['used_break'])) {
                                                    // Check day array if it has used_break
                                                    $usedBreak = (bool) $day['used_break'];
                                                } elseif ($defaultBreakIn || $defaultBreakOut) {
                                                    // If there are break times in existing data for flexible break, break was used
                                                    $usedBreak = true;
                                                } else {
                                                    // Default to true for new flexible break entries
                                                    $usedBreak = true;
                                                }
                                            }
                                            // Calculate original day type (Priority 2 logic - what it should be by default)
                                            $originalDayType = 'regular_workday'; // Default fallback
                                            $isHoliday = $day['is_holiday'] ? true : false;
                                            $holidayType = $day['holiday_type'] ?? null;
                                            
                                            if (($day['is_suspension'] ?? false)) {
                                                // Suspension days - find the appropriate suspension type
                                                foreach($logTypes as $value => $label) {
                                                    if (str_contains($label, 'Suspension')) {
                                                        $originalDayType = $value;
                                                        break;
                                                    }
                                                }
                                            } elseif (!$day['is_weekend'] && !$isHoliday) {
                                                // Regular Workday (default for work days)
                                                $originalDayType = 'regular_workday';
                                            } elseif ($day['is_weekend'] && !$isHoliday) {
                                                // Rest Day (default for rest days) 
                                                $originalDayType = 'rest_day';
                                            } elseif ($isHoliday && $holidayType === 'regular' && !$day['is_weekend']) {
                                                // Regular Holiday (default for active regular holidays on work days)
                                                $originalDayType = 'regular_holiday';
                                            } elseif ($isHoliday && $holidayType === 'special_non_working' && !$day['is_weekend']) {
                                                // Special Holiday (default for active special non-working holidays on work days)
                                                $originalDayType = 'special_holiday';
                                            } elseif ($day['is_weekend'] && $isHoliday && $holidayType === 'regular') {
                                                // Rest Day + Regular Holiday (rest day + regular holiday)
                                                $originalDayType = 'rest_day_regular_holiday';
                                            } elseif ($day['is_weekend'] && $isHoliday && $holidayType === 'special_non_working') {
                                                // Rest Day + Special Holiday (rest day + special non-working holiday)
                                                $originalDayType = 'rest_day_special_holiday';
                                            }
                                        @endphp
                                        <tr class="{{ $day['is_weekend'] ? 'bg-gray-100' : '' }}"
                                            data-is-paid-suspension="{{ $isPaidSuspension ? 'true' : 'false' }}"
                                            data-is-partial-suspension="{{ $isPartialSuspension ? 'true' : 'false' }}"
                                            data-suspension-start="{{ $isPartialSuspension && $suspensionInfo['time_from'] ? \Carbon\Carbon::parse($suspensionInfo['time_from'])->format('H:i') : '' }}"
                                            data-suspension-end="{{ $isPartialSuspension && $suspensionInfo['time_to'] ? \Carbon\Carbon::parse($suspensionInfo['time_to'])->format('H:i') : '' }}"
                                            data-employee-has-benefits="{{ $employeeHasBenefits ? 'true' : 'false' }}"
                                            data-time-in-default="{{ $defaultTimeIn }}"
                                            data-time-out-default="{{ $defaultTimeOut }}"
                                            data-break-in-default="{{ $defaultBreakIn }}"
                                            data-break-out-default="{{ $defaultBreakOut }}"
                                            data-original-day-type="{{ $originalDayType }}"
                                            data-original-used-break="{{ $hasFlexibleBreak ? 'true' : '' }}"
                                            data-is-weekend="{{ $day['is_weekend'] ? 'true' : 'false' }}"
                                            data-is-holiday="{{ $isHoliday ? 'true' : 'false' }}"
                                            data-holiday-type="{{ $holidayType ?? '' }}"
                                            data-is-suspension="{{ ($day['is_suspension'] ?? false) ? 'true' : 'false' }}">
                                            <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-gray-900 border-r">
                                                {{ $day['date']->format('M d') }}
                                                <input type="hidden" name="time_logs[{{ $index }}][log_date]" value="{{ $day['date']->format('Y-m-d') }}">
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 border-r">
                                                {{ $day['day_name'] }}
                                                @if($day['date']->isWeekend())
                                                    <span class="text-xs text-blue-600">(Weekend)</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap border-r">
                                                @php
                                                    $isSuspension = ($day['is_suspension'] ?? false);
                                                    $isActiveHoliday = ($day['is_holiday_active'] ?? false);
                                                    
                                                    // Holiday pay settings logic - ALWAYS allow time inputs for holidays (paid/unpaid)
                                                    // Users should be able to enter time logs regardless of holiday pay status
                                                    $holidayTimeInputDisabled = false;
                                                    
                                                    $isDropdownDisabled = $isSuspension || $isActiveHoliday; // Disable dropdown for both suspensions and active holidays
                                                    
                                                    // NEW SUSPENSION LOGIC:
                                                    if ($isSuspension) {
                                                        if ($isPartialSuspension) {
                                                            // PARTIAL SUSPENSION: Only disable day type dropdown, enable time/break inputs (emp can work before suspension starts)
                                                            $isTimeInputDisabled = false;
                                                            $isBreakInputDisabled = false;
                                                        } else {
                                                            // FULL DAY SUSPENSION: Disable all inputs (time in/out, break in/out/checkbox, day type)
                                                            $isTimeInputDisabled = true;
                                                            $isBreakInputDisabled = true;
                                                        }
                                                    } else {
                                                        // NON-SUSPENSION LOGIC: Consider holiday settings
                                                        $isTimeInputDisabled = $holidayTimeInputDisabled;
                                                        $isBreakInputDisabled = $holidayTimeInputDisabled;
                                                    }
                                                    
                                                    $timeInputClass = $isTimeInputDisabled ? 'bg-gray-100 cursor-not-allowed' : '';
                                                    $breakInputClass = $isBreakInputDisabled ? 'bg-gray-100 cursor-not-allowed' : '';
                                                    $timeDisabledAttr = $isTimeInputDisabled ? 'disabled' : '';
                                                    $breakDisabledAttr = $isBreakInputDisabled ? 'disabled' : '';
                                                @endphp
                                                <input type="time" 
                                                       name="time_logs[{{ $index }}][time_in]" 
                                                       value="{{ $defaultTimeIn ?? '' }}"
                                                       class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500 {{ $timeInputClass }} time-input-{{ $index }}"
                                                       data-row="{{ $index }}"
                                                       onchange="calculateHours({{ $index }})"
                                                       {!! $timeDisabledAttr !!}>
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap border-r">
                                                <input type="time" 
                                                       name="time_logs[{{ $index }}][time_out]" 
                                                       value="{{ $defaultTimeOut ?? '' }}"
                                                       class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500 {{ $timeInputClass }} time-input-{{ $index }}"
                                                       data-row="{{ $index }}"
                                                       onchange="calculateHours({{ $index }})"
                                                       {!! $timeDisabledAttr !!}>
                                            </td>
                                            @if($hasFlexibleBreak)
                                                <td class="px-8 py-2 whitespace-nowrap border-r">
                                                    <label class="flex items-center">
                                                        <!-- Hidden input to ensure unchecked state is submitted -->
                                                        <input type="hidden" name="time_logs[{{ $index }}][used_break]" value="0">
                                                        <input type="checkbox" 
                                                               name="time_logs[{{ $index }}][used_break]" 
                                                               value="1"
                                                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 {{ $breakInputClass }}"
                                                               {{ $usedBreak ? 'checked' : '' }}
                                                               onchange="handleFlexibleBreakChange({{ $index }})"
                                                               {!! $breakDisabledAttr !!}>
                                                        <span class="ml-2 text-sm text-gray-700">{{ $selectedEmployee->timeSchedule->break_duration_minutes ?? 60 }} min</span>
                                                    </label>
                                                    <!-- Hidden fields for break time calculation -->
                                                    <input type="hidden" name="time_logs[{{ $index }}][break_in]" value="">
                                                    <input type="hidden" name="time_logs[{{ $index }}][break_out]" value="">
                                                </td>
                                            @else
                                                <td class="px-3 py-2 whitespace-nowrap border-r">
                                                    <input type="time" 
                                                           name="time_logs[{{ $index }}][break_in]" 
                                                           value="{{ $defaultBreakIn ?? '' }}"
                                                           class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500 {{ $breakInputClass }} time-input-{{ $index }}"
                                                           {!! $breakDisabledAttr !!}>
                                                </td>
                                                <td class="px-3 py-2 whitespace-nowrap border-r">
                                                    <input type="time" 
                                                           name="time_logs[{{ $index }}][break_out]" 
                                                           value="{{ $defaultBreakOut ?? '' }}"
                                                           class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500 {{ $breakInputClass }} time-input-{{ $index }}"
                                                           {!! $breakDisabledAttr !!}>
                                                </td>
                                            @endif
                                            <td class="px-3 py-2 whitespace-nowrap border-r">
                                                <select name="time_logs[{{ $index }}][log_type]" 
                                                        class="w-full px-2 py-1 text-sm border border-gray-300 rounded focus:ring-indigo-500 focus:border-indigo-500 {{ $isDropdownDisabled ? 'bg-gray-100 cursor-not-allowed' : '' }} log-type-{{ $index }}"
                                                        data-row="{{ $index }}"
                                                        onchange="handleLogTypeChange({{ $index }})"
                                                        {!! $isDropdownDisabled ? 'disabled' : '' !!}>
                                                    @foreach($logTypes as $value => $label)
                                                        @php
                                                            $selected = '';
                                                            $isHoliday = $day['is_holiday'] ? true : false;
                                                            $holidayType = $day['holiday_type'] ?? null;
                                                            
                                                            // Priority 1: Use actual log_type from database if exists
                                                            if ($day['log_type'] && $value === $day['log_type']) {
                                                                $selected = 'selected';
                                                            }
                                                            // Priority 2: Smart default selection logic (only if no existing log_type)
                                                            elseif (!$day['log_type']) {
                                                                if (($day['is_suspension'] ?? false) && str_contains($label, 'Suspension')) {
                                                                    $selected = 'selected';
                                                                } elseif (!$day['is_weekend'] && !$isHoliday && !($day['is_suspension'] ?? false) && $value === 'regular_workday') {
                                                                    // 1. Regular Workday (default for work days)
                                                                    $selected = 'selected';
                                                                } elseif ($day['is_weekend'] && !$isHoliday && $value === 'rest_day') {
                                                                    // 2. Rest Day (default for rest days) 
                                                                    $selected = 'selected';
                                                                } elseif ($isHoliday && $holidayType === 'regular' && !$day['is_weekend'] && $value === 'regular_holiday') {
                                                                    // 3. Regular Holiday (default for active regular holidays on work days)
                                                                    $selected = 'selected';
                                                                } elseif ($isHoliday && $holidayType === 'special_non_working' && !$day['is_weekend'] && $value === 'special_holiday') {
                                                                    // 4. Special Holiday (default for active special non-working holidays on work days)
                                                                    $selected = 'selected';
                                                                } elseif ($day['is_weekend'] && $isHoliday && $holidayType === 'regular' && $value === 'rest_day_regular_holiday') {
                                                                    // Rest Day + Regular Holiday (rest day + regular holiday)
                                                                    $selected = 'selected';
                                                                } elseif ($day['is_weekend'] && $isHoliday && $holidayType === 'special_non_working' && $value === 'rest_day_special_holiday') {
                                                                    // Rest Day + Special Holiday (rest day + special non-working holiday)
                                                                    $selected = 'selected';
                                                                }
                                                            }
                                                        @endphp
                                                        <option value="{{ $value }}" {{ $selected }}>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                @if($isSuspension)
                                                    {{-- For active suspension days, add hidden input for log_type since disabled selects won't submit --}}
                                                    @php
                                                        // Always use the log_type determined by the controller (full_day_suspension or partial_suspension)
                                                        $selectedLogType = $day['log_type'] ?? 'full_day_suspension';
                                                    @endphp
                                                    <input type="hidden" name="time_logs[{{ $index }}][log_type]" value="{{ $selectedLogType }}">
                                                    {{-- Add hidden inputs for suspension days since disabled time inputs won't submit their values --}}
                                                    <input type="hidden" name="time_logs[{{ $index }}][time_in_hidden]" value="{{ $defaultTimeIn ?? '' }}">
                                                    <input type="hidden" name="time_logs[{{ $index }}][time_out_hidden]" value="{{ $defaultTimeOut ?? '' }}">
                                                    <input type="hidden" name="time_logs[{{ $index }}][break_in_hidden]" value="{{ $defaultBreakIn ?? '' }}">
                                                    <input type="hidden" name="time_logs[{{ $index }}][break_out_hidden]" value="{{ $defaultBreakOut ?? '' }}">
                                                    {{-- Add suspension pay settings for JavaScript access --}}
                                                    <input type="hidden" name="time_logs[{{ $index }}][suspension_pay_applicable_to]" value="{{ $suspensionInfo['pay_applicable_to'] ?? 'all' }}">
                                                @elseif($isActiveHoliday)
                                                    {{-- For active holidays, add hidden input for log_type since disabled selects won't submit --}}
                                                    @php
                                                        $selectedLogType = $day['log_type'] ?? '';
                                                    @endphp
                                                    <input type="hidden" name="time_logs[{{ $index }}][log_type]" value="{{ $selectedLogType }}">
                                                    @if($holidayTimeInputDisabled)
                                                        {{-- Add hidden inputs for holiday days since disabled time inputs won't submit their values --}}
                                                        <input type="hidden" name="time_logs[{{ $index }}][time_in_hidden]" value="{{ $day['time_in'] ? (is_string($day['time_in']) ? (strlen($day['time_in']) >= 5 ? substr($day['time_in'], 0, 5) : $day['time_in']) : $day['time_in']->format('H:i')) : '' }}">
                                                        <input type="hidden" name="time_logs[{{ $index }}][time_out_hidden]" value="{{ $day['time_out'] ? (is_string($day['time_out']) ? (strlen($day['time_out']) >= 5 ? substr($day['time_out'], 0, 5) : $day['time_out']) : $day['time_out']->format('H:i')) : '' }}">
                                                        <input type="hidden" name="time_logs[{{ $index }}][break_in_hidden]" value="{{ $day['break_in'] ? (is_string($day['break_in']) ? (strlen($day['break_in']) >= 5 ? substr($day['break_in'], 0, 5) : $day['break_in']) : $day['break_in']->format('H:i')) : '' }}">
                                                        <input type="hidden" name="time_logs[{{ $index }}][break_out_hidden]" value="{{ $day['break_out'] ? (is_string($day['break_out']) ? (strlen($day['break_out']) >= 5 ? substr($day['break_out'], 0, 5) : $day['break_out']) : $day['break_out']->format('H:i')) : '' }}">
                                                    @endif
                                                    {{-- Add holiday pay settings as hidden inputs for JavaScript access --}}
                                                    <input type="hidden" name="time_logs[{{ $index }}][holiday_is_paid]" value="{{ $day['holiday_is_paid'] ? '1' : '0' }}">
                                                    <input type="hidden" name="time_logs[{{ $index }}][holiday_pay_applicable_to]" value="{{ $day['holiday_pay_applicable_to'] ?? '' }}">
                                                @endif
                                                @if($day['is_weekend'])
                                                    <input type="hidden" name="time_logs[{{ $index }}][is_rest_day]" value="1">
                                                @endif
                                                @if($day['is_holiday'])
                                                    <input type="hidden" name="time_logs[{{ $index }}][is_holiday]" value="1">
                                                @endif
                                                @if($isActiveHoliday)
                                                    <input type="hidden" name="time_logs[{{ $index }}][is_holiday_active]" value="1">
                                                @endif
                                                @if($day['is_suspension'] ?? false)
                                                    <input type="hidden" name="time_logs[{{ $index }}][is_suspension]" value="1">
                                                @endif
                                            </td>
                                            <td class="px-3 py-2">
                                                <div class="flex gap-2">
                                                    <button type="button" 
                                                            onclick="setRegularHours({{ $index }})"
                                                            class="px-2 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"
                                                            title="Set 8:00 AM - 5:00 PM">
                                                        Set Regular
                                                    </button>
                                                    <button type="button" 
                                                            onclick="clearRowTimes({{ $index }})"
                                                            class="px-2 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-opacity-50"
                                                            title="Clear all times for this row">
                                                        Clear
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Legend -->
                        {{-- <div class="mt-4 text-sm text-gray-600">
                            <p><strong>Legend:</strong></p>
                            <div class="flex flex-wrap gap-4 mt-1">
                                <span>● <span class="bg-gray-100 px-2 py-1 rounded">Weekend days</span></span>
                                <span>● <span class="bg-yellow-50 px-2 py-1 rounded">Holiday</span></span>
                                <span>● <strong>Overtime Hours:</strong> Hours worked beyond regular shift</span>
                            </div>
                        </div> --}}

                        <!-- Action Buttons -->
                        <div class="mt-6 flex justify-between">
                            <div>
                                @if($payrollId && isset($schedule))
                                    <a href="{{ route('payrolls.automation.show', ['schedule' => $schedule, 'id' => $selectedEmployee->id]) }}{{ request()->get('from_last_payroll') ? '?from_last_payroll=true' : '' }}" 
                                       class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        ← Back to Payroll
                                    </a>
                                @elseif($payrollId)
                                    <a href="{{ route('payrolls.show', $payrollId) }}" 
                                       class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        ← Back to Payroll
                                    </a>
                                @else
                                    <a href="{{ route('time-logs.create-bulk') }}" 
                                       class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        ← Back
                                    </a>
                                @endif
                            </div>
                            <button type="submit" 
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring ring-blue-300 disabled:opacity-25 transition ease-in-out duration-150">
                                @if($payrollId)
                                    Save DTR & Return to Payroll
                                @else
                                    Save DTR
                                @endif
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function fillRegularHours(timeIn, timeOut, breakIn, breakOut) {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                const isRestDay = row.classList.contains('bg-gray-100'); // This is employee's rest day
                const isHoliday = row.classList.contains('bg-yellow-50');
                
                // Skip rest days and holidays (but handle suspensions properly)
                if (isRestDay || isHoliday) {
                    return;
                }
                
                // Check if the row has suspension type selected
                const logTypeSelect = row.querySelector(`select[name="time_logs[${index}][log_type]"]`);
                const selectedOption = logTypeSelect ? logTypeSelect.options[logTypeSelect.selectedIndex] : null;
                const selectedText = selectedOption ? selectedOption.text : '';
                const isSuspension = selectedText.toLowerCase().includes('suspension');
                
                // For suspension days, apply the same logic as setRegularHours
                if (isSuspension) {
                    // Check if time inputs are disabled (this catches paid full suspensions)
                    const timeInInput = row.querySelector(`input[name="time_logs[${index}][time_in]"]`);
                    if (timeInInput && timeInInput.disabled) {
                        console.log('Skipping fillRegularHours for disabled inputs (paid full suspension) on row:', index);
                        return;
                    }
                    
                    // Get suspension details to determine if filling should be allowed
                    const isPartialSuspension = row && row.dataset.isPartialSuspension === 'true';
                    const isPaidSuspension = row && row.dataset.isPaidSuspension === 'true';
                    const employeeHasBenefits = row && row.dataset.employeeHasBenefits === 'true';
                    
                    // Get suspension pay settings
                    const suspensionPayApplicableToInput = document.querySelector(`input[name="time_logs[${index}][suspension_pay_applicable_to]"][type="hidden"]`);
                    const suspensionPayApplicableTo = suspensionPayApplicableToInput ? suspensionPayApplicableToInput.value : 'all';
                    
                    // Check if this employee should receive paid suspension
                    let shouldReceivePaidSuspension = false;
                    if (isPaidSuspension) {
                        if (suspensionPayApplicableTo === 'all') {
                            shouldReceivePaidSuspension = true;
                        } else if (suspensionPayApplicableTo === 'with_benefits' && employeeHasBenefits) {
                            shouldReceivePaidSuspension = true;
                        } else if (suspensionPayApplicableTo === 'without_benefits' && !employeeHasBenefits) {
                            shouldReceivePaidSuspension = true;
                        }
                    }
                    
                    // Block auto-fill ONLY for paid full-day suspensions
                    // Allow for: 1. Partial suspensions 2. Unpaid suspensions
                    if (!isPartialSuspension && shouldReceivePaidSuspension) {
                        console.log('Skipping fillRegularHours for paid full-day suspension on row:', index);
                        return;
                    } else {
                        console.log('Allowing fillRegularHours for suspension (partial or unpaid) on row:', index);
                    }
                }
                
                // Fill the time inputs
                const timeInInput = row.querySelector(`input[name="time_logs[${index}][time_in]"]`);
                const timeOutInput = row.querySelector(`input[name="time_logs[${index}][time_out]"]`);
                const breakInInput = row.querySelector(`input[name="time_logs[${index}][break_in]"]`);
                const breakOutInput = row.querySelector(`input[name="time_logs[${index}][break_out]"]`);
                
                // Only fill empty fields to avoid overwriting existing data
                if (timeInInput && !timeInInput.value && !timeInInput.disabled) timeInInput.value = timeIn;
                if (timeOutInput && !timeOutInput.value && !timeOutInput.disabled) timeOutInput.value = timeOut;
                if (breakInInput && !breakInInput.value && !breakInInput.disabled) breakInInput.value = breakIn;
                if (breakOutInput && !breakOutInput.value && !breakOutInput.disabled) breakOutInput.value = breakOut;
            });
        }

        function fillTimeOnly(timeIn, timeOut) {
            // Fill only time in/out fields for flexible break employees
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                const isRestDay = row.classList.contains('bg-gray-100'); // This is employee's rest day
                const isHoliday = row.classList.contains('bg-yellow-50');
                
                // Skip rest days and holidays (but handle suspensions properly)
                if (isRestDay || isHoliday) {
                    return;
                }
                
                // Check if the row has suspension type selected
                const logTypeSelect = row.querySelector(`select[name="time_logs[${index}][log_type]"]`);
                const selectedOption = logTypeSelect ? logTypeSelect.options[logTypeSelect.selectedIndex] : null;
                const selectedText = selectedOption ? selectedOption.text : '';
                const isSuspension = selectedText.toLowerCase().includes('suspension');
                
                // For suspension days, apply the same logic as setRegularHours
                if (isSuspension) {
                    // Check if time inputs are disabled (this catches paid full suspensions)
                    const timeInInput = row.querySelector(`input[name="time_logs[${index}][time_in]"]`);
                    if (timeInInput && timeInInput.disabled) {
                        console.log('Skipping fillTimeOnly for disabled inputs (paid full suspension) on row:', index);
                        return;
                    }
                    
                    // Get suspension details to determine if filling should be allowed
                    const isPartialSuspension = row && row.dataset.isPartialSuspension === 'true';
                    const isPaidSuspension = row && row.dataset.isPaidSuspension === 'true';
                    const employeeHasBenefits = row && row.dataset.employeeHasBenefits === 'true';
                    
                    // Get suspension pay settings
                    const suspensionPayApplicableToInput = document.querySelector(`input[name="time_logs[${index}][suspension_pay_applicable_to]"][type="hidden"]`);
                    const suspensionPayApplicableTo = suspensionPayApplicableToInput ? suspensionPayApplicableToInput.value : 'all';
                    
                    // Check if this employee should receive paid suspension
                    let shouldReceivePaidSuspension = false;
                    if (isPaidSuspension) {
                        if (suspensionPayApplicableTo === 'all') {
                            shouldReceivePaidSuspension = true;
                        } else if (suspensionPayApplicableTo === 'with_benefits' && employeeHasBenefits) {
                            shouldReceivePaidSuspension = true;
                        } else if (suspensionPayApplicableTo === 'without_benefits' && !employeeHasBenefits) {
                            shouldReceivePaidSuspension = true;
                        }
                    }
                    
                    // Block auto-fill ONLY for paid full-day suspensions
                    // Allow for: 1. Partial suspensions 2. Unpaid suspensions
                    if (!isPartialSuspension && shouldReceivePaidSuspension) {
                        console.log('Skipping fillTimeOnly for paid full-day suspension on row:', index);
                        return;
                    } else {
                        console.log('Allowing fillTimeOnly for suspension (partial or unpaid) on row:', index);
                    }
                }
                
                // Fill the time inputs
                const timeInInput = row.querySelector(`input[name="time_logs[${index}][time_in]"]`);
                const timeOutInput = row.querySelector(`input[name="time_logs[${index}][time_out]"]`);
                
                // Only fill empty fields to avoid overwriting existing data
                if (timeInInput && !timeInInput.value && !timeInInput.disabled) timeInInput.value = timeIn;
                if (timeOutInput && !timeOutInput.value && !timeOutInput.disabled) timeOutInput.value = timeOut;
                // Do not fill break fields for flexible break employees
            });
        }

        function fillRegularHoursAll(timeIn, timeOut) {
            // Alternative function to fill all fields (overwrite existing)
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                const isRestDay = row.classList.contains('bg-gray-100'); // This is employee's rest day
                const isHoliday = row.classList.contains('bg-yellow-50');
                
                if (!isRestDay && !isHoliday) {
                    const timeInInput = row.querySelector(`input[name="time_logs[${index}][time_in]"]`);
                    const timeOutInput = row.querySelector(`input[name="time_logs[${index}][time_out]"]`);
                    const breakInInput = row.querySelector(`input[name="time_logs[${index}][break_in]"]`);
                    const breakOutInput = row.querySelector(`input[name="time_logs[${index}][break_out]"]`);
                    
                    if (timeInInput) timeInInput.value = timeIn;       // 8:00 (8:00 AM)
                    if (timeOutInput) timeOutInput.value = timeOut;    // 17:00 (5:00 PM)
                    if (breakInInput) breakInInput.value = '12:00';    // 12:00 PM
                    if (breakOutInput) breakOutInput.value = '13:00';  // 1:00 PM
                }
            });
        }

        function clearAndSetRegular() {
            // Clear only time logs (time in/out, break in/out), preserve types
            const timeInputs = document.querySelectorAll('input[name*="[time_in]"], input[name*="[time_out]"], input[name*="[break_in]"], input[name*="[break_out]"]');
            timeInputs.forEach(input => {
                input.value = '';
            });
        }

        function resetTimeOnly() {
            // Clear only time fields, preserve ALL day types (including suspension days)
            const timeInputs = document.querySelectorAll('input[name*="[time_in]"], input[name*="[time_out]"], input[name*="[break_in]"], input[name*="[break_out]"]');
            timeInputs.forEach(input => {
                // Only clear if not disabled (suspension days should keep their values)
                if (!input.disabled) {
                    input.value = '';
                }
            });

            // For flexible break employees, reset break checkbox to checked (default state)
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                // Reset flexible break checkbox to checked (default state) for flexible employees
                const breakCheckbox = row.querySelector(`input[type="checkbox"][name="time_logs[${index}][used_break]"]`);
                if (breakCheckbox && !breakCheckbox.disabled) {
                    breakCheckbox.checked = true;
                }
                
                // Trigger handleLogTypeChange for each row to ensure proper input states
                handleLogTypeChange(index);
            });
        }

        function resetAll() {
            // Clear all time fields AND reset day types to original defaults
            const timeInputs = document.querySelectorAll('input[name*="[time_in]"], input[name*="[time_out]"], input[name*="[break_in]"], input[name*="[break_out]"]');
            timeInputs.forEach(input => {
                // Only clear if not disabled (suspension days should keep their values)
                if (!input.disabled) {
                    input.value = '';
                }
            });

            // Reset all log types to their original day types
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                const logTypeSelect = row.querySelector(`select[name="time_logs[${index}][log_type]"]`);
                if (logTypeSelect && !logTypeSelect.disabled) {
                    // Get the original day type from data attribute
                    const originalDayType = row.getAttribute('data-original-day-type');
                    
                    if (originalDayType) {
                        // Find and select the original day type option
                        for (let i = 0; i < logTypeSelect.options.length; i++) {
                            if (logTypeSelect.options[i].value === originalDayType) {
                                logTypeSelect.selectedIndex = i;
                                break;
                            }
                        }
                    }
                }

                // Reset flexible break checkbox to original state (default: checked for flexible employees)
                const breakCheckbox = row.querySelector(`input[type="checkbox"][name="time_logs[${index}][used_break]"]`);
                if (breakCheckbox && !breakCheckbox.disabled) {
                    const originalUsedBreak = row.getAttribute('data-original-used-break');
                    if (originalUsedBreak === 'true') {
                        breakCheckbox.checked = true;
                    } else if (originalUsedBreak === 'false') {
                        breakCheckbox.checked = false;
                    }
                }
                
                // Trigger handleLogTypeChange for each row to ensure proper input states
                handleLogTypeChange(index);
            });
        }

        function setRegularHours(rowIndex) {
            // Check if time inputs are disabled (this will catch paid full suspensions, etc.)
            const timeInInput = document.querySelector(`input[name="time_logs[${rowIndex}][time_in]"]`);
            if (timeInInput && timeInInput.disabled) {
                console.log('Skipping time fill for disabled inputs on row:', rowIndex);
                return;
            }
            
            // Additional check: Allow auto-fill for partial suspensions and unpaid suspensions
            // Only block if it's a PAID full-day suspension (which would have disabled inputs above)
            const logTypeSelect = document.querySelector(`select[name="time_logs[${rowIndex}][log_type]"]`);
            const selectedOption = logTypeSelect ? logTypeSelect.options[logTypeSelect.selectedIndex] : null;
            const selectedText = selectedOption ? selectedOption.text : '';
            const isSuspension = selectedText.toLowerCase().includes('suspension');
            
            if (isSuspension) {
                // Get suspension details to determine if auto-fill should be allowed
                const row = document.querySelector(`[data-row="${rowIndex}"]`).closest('tr');
                const isPartialSuspension = row && row.dataset.isPartialSuspension === 'true';
                const isPaidSuspension = row && row.dataset.isPaidSuspension === 'true';
                const employeeHasBenefits = row && row.dataset.employeeHasBenefits === 'true';
                
                // Get suspension pay settings
                const suspensionPayApplicableToInput = document.querySelector(`input[name="time_logs[${rowIndex}][suspension_pay_applicable_to]"][type="hidden"]`);
                const suspensionPayApplicableTo = suspensionPayApplicableToInput ? suspensionPayApplicableToInput.value : 'all';
                
                // Check if this employee should receive paid suspension
                let shouldReceivePaidSuspension = false;
                if (isPaidSuspension) {
                    if (suspensionPayApplicableTo === 'all') {
                        shouldReceivePaidSuspension = true;
                    } else if (suspensionPayApplicableTo === 'with_benefits' && employeeHasBenefits) {
                        shouldReceivePaidSuspension = true;
                    } else if (suspensionPayApplicableTo === 'without_benefits' && !employeeHasBenefits) {
                        shouldReceivePaidSuspension = true;
                    }
                }
                
                // Allow auto-fill for:
                // 1. Partial suspensions (always need time inputs)
                // 2. Unpaid suspensions (where user can manually enter time)
                // Block auto-fill ONLY for paid full-day suspensions
                if (!isPartialSuspension && shouldReceivePaidSuspension) {
                    console.log('Skipping time fill for paid full-day suspension on row:', rowIndex);
                    return;
                } else {
                    console.log('Allowing time fill for suspension (partial or unpaid) on row:', rowIndex);
                }
            }
            
            @php
                // Pass the employee schedule to JavaScript
                $jsScheduleStart = $selectedEmployee->timeSchedule ? $selectedEmployee->timeSchedule->time_in : '08:00';
                $jsScheduleEnd = $selectedEmployee->timeSchedule ? $selectedEmployee->timeSchedule->time_out : '17:00';
                $jsBreakStart = ($selectedEmployee->timeSchedule && $selectedEmployee->timeSchedule->break_start) ? $selectedEmployee->timeSchedule->break_start : '12:00';
                $jsBreakEnd = ($selectedEmployee->timeSchedule && $selectedEmployee->timeSchedule->break_end) ? $selectedEmployee->timeSchedule->break_end : '13:00';
                
                $jsScheduleStart = \Carbon\Carbon::parse($jsScheduleStart)->format('H:i');
                $jsScheduleEnd = \Carbon\Carbon::parse($jsScheduleEnd)->format('H:i');
                $jsBreakStart = \Carbon\Carbon::parse($jsBreakStart)->format('H:i');
                $jsBreakEnd = \Carbon\Carbon::parse($jsBreakEnd)->format('H:i');
                
                // Determine break type for intelligent behavior
                $jsIsFlexibleBreak = false;
                $jsIsFixedBreak = false;
                
                if ($selectedEmployee->timeSchedule) {
                    // Check if employee has flexible break (break_duration_minutes without fixed times)
                    if ($selectedEmployee->timeSchedule->break_duration_minutes && $selectedEmployee->timeSchedule->break_duration_minutes > 0 && !($selectedEmployee->timeSchedule->break_start && $selectedEmployee->timeSchedule->break_end)) {
                        $jsIsFlexibleBreak = true;
                    } elseif ($selectedEmployee->timeSchedule->break_start && $selectedEmployee->timeSchedule->break_end) {
                        $jsIsFixedBreak = true;
                    }
                }
            @endphp
            
            // Set employee's scheduled working hours for a specific row
            const timeOutInput = document.querySelector(`input[name="time_logs[${rowIndex}][time_out]"]`);
            const breakInInput = document.querySelector(`input[name="time_logs[${rowIndex}][break_in]"]`);
            const breakOutInput = document.querySelector(`input[name="time_logs[${rowIndex}][break_out]"]`);
            
            // Use employee's actual schedule times
            if (timeInInput) timeInInput.value = '{{ $jsScheduleStart }}';
            if (timeOutInput) timeOutInput.value = '{{ $jsScheduleEnd }}';
            
            // Intelligent break field handling based on employee break type
            @if($isFlexibleBreak ?? false)
                // Flexible break employee - do not fill break fields
                // Break fields remain empty for flexible break employees
            @elseif($isFixedBreak ?? false)
                // Fixed break employee - fill break fields
                if (breakInInput) breakInInput.value = '{{ $jsBreakStart }}';
                if (breakOutInput) breakOutInput.value = '{{ $jsBreakEnd }}';
            @else
                // No break configuration - do not fill break fields (default to flexible behavior)
                // Break fields remain empty
            @endif
            
            // Do NOT change the log type - preserve the current selection
            // This allows users to set regular times on Rest Days, Holidays, etc. without changing the type
            
            // Trigger calculation if function exists
            if (typeof calculateHours === 'function') {
                calculateHours(rowIndex);
            }
        }

        function clearRowTimes(rowIndex) {
            // Clear only time entries for a specific row, preserve the log type
            const timeInInput = document.querySelector(`input[name="time_logs[${rowIndex}][time_in]"]`);
            const timeOutInput = document.querySelector(`input[name="time_logs[${rowIndex}][time_out]"]`);
            const breakInInput = document.querySelector(`input[name="time_logs[${rowIndex}][break_in]"]`);
            const breakOutInput = document.querySelector(`input[name="time_logs[${rowIndex}][break_out]"]`);
            
            // Clear only time fields, do NOT change the log type
            if (timeInInput && !timeInInput.disabled) timeInInput.value = '';
            if (timeOutInput && !timeOutInput.disabled) timeOutInput.value = '';
            if (breakInInput && !breakInInput.disabled) breakInInput.value = '';
            if (breakOutInput && !breakOutInput.disabled) breakOutInput.value = '';
            
            // Trigger calculation if function exists
            if (typeof calculateHours === 'function') {
                calculateHours(rowIndex);
            }
        }

        function calculateHours(rowIndex) {
            // You can add automatic hour calculation logic here if needed
            console.log('Calculating hours for row:', rowIndex);
        }

        function handleLogTypeChange(rowIndex) {
            const logTypeSelect = document.querySelector(`select[name="time_logs[${rowIndex}][log_type]"]`);
            const timeInputs = document.querySelectorAll(`.time-input-${rowIndex}`);
            
            if (logTypeSelect && timeInputs.length > 0) {
                const selectedOption = logTypeSelect.options[logTypeSelect.selectedIndex];
                const selectedText = selectedOption ? selectedOption.text : '';
                
                // Check if the selected option contains "Suspension" in its text
                const isSuspension = selectedText.toLowerCase().includes('suspension');
                
                // Get row element to check for suspension data attributes
                const row = document.querySelector(`[data-row="${rowIndex}"]`).closest('tr');
                const isPaidSuspension = row && row.dataset.isPaidSuspension === 'true';
                const employeeHasBenefits = row && row.dataset.employeeHasBenefits === 'true';
                
                // Check if this is an inherent suspension day (from suspension settings)
                const hasHiddenSuspensionInput = document.querySelector(`input[name="time_logs[${rowIndex}][is_suspension]"][type="hidden"]`);
                
                // Check if this is an active holiday day
                const hasHiddenHolidayInput = document.querySelector(`input[name="time_logs[${rowIndex}][is_holiday_active]"][type="hidden"]`);
                
                // Get holiday pay settings
                const holidayIsPaidInput = document.querySelector(`input[name="time_logs[${rowIndex}][holiday_is_paid]"][type="hidden"]`);
                const holidayApplicableToInput = document.querySelector(`input[name="time_logs[${rowIndex}][holiday_pay_applicable_to]"][type="hidden"]`);
                
                timeInputs.forEach(input => {
                    // PRIORITY 1: Active holiday - check pay settings logic
                    if (hasHiddenHolidayInput) {
                        const holidayIsPaid = holidayIsPaidInput && holidayIsPaidInput.value === '1';
                        const holidayApplicableTo = holidayApplicableToInput ? holidayApplicableToInput.value : null;
                        const employeeHasBenefitsValue = employeeHasBenefits; // Use suspension data for benefit status
                        
                        // ALWAYS allow time inputs for holidays (paid/unpaid) - users should be able to enter time logs
                        input.disabled = false;
                        input.classList.remove('bg-gray-100', 'cursor-not-allowed');
                        input.classList.add('focus:ring-indigo-500', 'focus:border-indigo-500');
                    }
                    // PRIORITY 2: Suspension days
                    else if (hasHiddenSuspensionInput) {
                        // Check if this is a partial suspension
                        const isPartialSuspension = row && row.dataset.isPartialSuspension === 'true';
                        
                        // For suspension setting days - check pay applicability
                        const suspensionPayApplicableToInput = document.querySelector(`input[name="time_logs[${rowIndex}][suspension_pay_applicable_to]"][type="hidden"]`);
                        const suspensionPayApplicableTo = suspensionPayApplicableToInput ? suspensionPayApplicableToInput.value : 'all';
                        
                        let shouldAutoFillSuspension = false;
                        
                        if (isPaidSuspension) {
                            if (suspensionPayApplicableTo === 'all') {
                                shouldAutoFillSuspension = true;
                            } else if (suspensionPayApplicableTo === 'with_benefits' && employeeHasBenefits) {
                                shouldAutoFillSuspension = true;
                            } else if (suspensionPayApplicableTo === 'without_benefits' && !employeeHasBenefits) {
                                shouldAutoFillSuspension = true;
                            }
                        }
                        
                        // Get the field type for partial suspension logic
                        const fieldName = input.name.match(/\[(\w+)\]$/)[1]; // Extract field name (time_in, time_out, break_in, break_out)
                        const isBreakField = fieldName === 'break_in' || fieldName === 'break_out';
                        const isTimeField = fieldName === 'time_in' || fieldName === 'time_out';
                        
                        if (isPartialSuspension) {
                            // PARTIAL SUSPENSION LOGIC:
                            if (isBreakField || isTimeField) {
                                // Enable both time and break fields for partial suspension (employee can work before suspension starts)
                                input.disabled = false;
                                input.classList.remove('bg-gray-100', 'cursor-not-allowed');
                                input.classList.add('focus:ring-indigo-500', 'focus:border-indigo-500');
                                // Keep existing values from PHP auto-fill
                            }
                        } else {
                            // FULL DAY SUSPENSION LOGIC:
                            if (shouldAutoFillSuspension) {
                                // Paid suspension applicable to this employee: disable inputs but preserve auto-filled values
                                input.disabled = true;
                                input.classList.add('bg-gray-100', 'cursor-not-allowed');
                                input.classList.remove('focus:ring-indigo-500', 'focus:border-indigo-500');
                                // Keep existing values (already set by PHP)
                            } else {
                                // Unpaid suspension OR not applicable to this employee: disable and clear values
                                input.disabled = true;
                                input.value = ''; // Clear the input
                                input.classList.add('bg-gray-100', 'cursor-not-allowed');
                                input.classList.remove('focus:ring-indigo-500', 'focus:border-indigo-500');
                            }
                        }
                    } else if (isSuspension) {
                        if (isPaidSuspension) {
                            // For paid suspensions (manual selection), enable inputs but fill with default schedule
                            input.disabled = false;
                            input.classList.remove('bg-gray-100', 'cursor-not-allowed');
                            input.classList.add('focus:ring-indigo-500', 'focus:border-indigo-500');
                            
                            // Fill with default values if empty (these come from server-side processing)
                            if (!input.value) {
                                const fieldName = input.name.match(/\[(\w+)\]$/)[1]; // Extract field name (time_in, time_out, etc.)
                                const defaultValue = row.dataset[fieldName + 'Default'];
                                if (defaultValue && defaultValue !== 'null') {
                                    input.value = defaultValue;
                                }
                            }
                        } else {
                            // For unpaid suspensions (manual selection), disable inputs and clear values
                            input.disabled = true;
                            input.value = ''; // Clear the input for unpaid suspension
                            input.classList.add('bg-gray-100', 'cursor-not-allowed');
                            input.classList.remove('focus:ring-indigo-500', 'focus:border-indigo-500');
                        }
                    } else {
                        // Enable inputs for non-suspension types (only for non-suspension setting days)
                        input.disabled = false;
                        input.classList.remove('bg-gray-100', 'cursor-not-allowed');
                        input.classList.add('focus:ring-indigo-500', 'focus:border-indigo-500');
                    }
                });
                
                // Sync hidden field values for suspension days
                syncHiddenFields(rowIndex);
            }
        }

        // Function to sync hidden field values with actual input values
        function syncHiddenFields(rowIndex) {
            const timeFields = ['time_in', 'time_out', 'break_in', 'break_out'];
            
            timeFields.forEach(field => {
                const actualInput = document.querySelector(`input[name="time_logs[${rowIndex}][${field}]"]`);
                const hiddenInput = document.querySelector(`input[name="time_logs[${rowIndex}][${field}_hidden]"]`);
                
                if (actualInput && hiddenInput) {
                    // If the actual input is disabled, sync its value to the hidden field
                    if (actualInput.disabled && actualInput.value) {
                        hiddenInput.value = actualInput.value;
                    }
                    // If the actual input is enabled, clear the hidden field to avoid conflicts
                    else if (!actualInput.disabled) {
                        hiddenInput.value = '';
                    }
                }
            });
        }

        // Function to handle flexible break checkbox changes
        function handleFlexibleBreakChange(rowIndex) {
            const checkbox = document.querySelector(`input[name="time_logs[${rowIndex}][used_break]"]`);
            if (checkbox) {
                // Trigger hour calculation when break usage changes
                if (typeof calculateHours === 'function') {
                    calculateHours(rowIndex);
                }
                
                // Log the change for debugging
                console.log(`Row ${rowIndex}: Break used = ${checkbox.checked}`);
            }
        }

        // Auto-save functionality (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('dtr-form');
            if (form) {
                // Add any initialization logic here
                console.log('DTR form initialized');
                
                // Initialize disabled state for all rows on page load
                const rows = document.querySelectorAll('tbody tr');
                rows.forEach((row, index) => {
                    const logTypeSelect = row.querySelector(`select[name="time_logs[${index}][log_type]"]`);
                    if (logTypeSelect) {
                        // Check current selected value and apply appropriate state
                        handleLogTypeChange(index);
                    }
                });
                
                // Add event listeners to time inputs for syncing hidden fields
                const timeInputs = form.querySelectorAll('input[type="time"]');
                timeInputs.forEach(input => {
                    input.addEventListener('input', function() {
                        const match = this.name.match(/time_logs\[(\d+)\]/);
                        if (match) {
                            const rowIndex = parseInt(match[1]);
                            syncHiddenFields(rowIndex);
                        }
                    });
                });
            }
        });
    </script>
</x-app-layout>
