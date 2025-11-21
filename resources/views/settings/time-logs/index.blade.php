<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Time Logs Settings
        </h2>
    </x-slot>

    <div class="py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-8">
            <div class="px-6 py-4 border-b bord// Context Menu Functions
function showContextMenu(event, element) {
    event.preventDefault();
    
    console.log('showContextMenu called', event, element); // Debug log
    
    const contextMenu = document.getElementById('contextMenu');
    const contextMenuItems = document.getElementById('contextMenuItems');00">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Time Log Settings</h1>
                        <p class="text-gray-600 mt-1">Manage schedules, break periods, and grace periods</p>
                    </div>
                    <div class="flex items-center space-x-3">
                        <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            
            <!-- Day Schedules -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Day Schedules</h2>
                        <button onclick="openDayScheduleModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-md text-sm font-medium">
                            Add Schedule
                        </button>
                    </div>
                    <p class="text-gray-600 text-sm mt-1">Define which days employees work</p>
                </div>
                <div class="p-6">
                    <div id="daySchedulesContainer" class="space-y-3">
                        @foreach($daySchedules as $schedule)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md hover:bg-gray-100 cursor-pointer schedule-item" 
                             data-schedule-id="{{ $schedule->id }}" 
                             data-schedule-type="day"
                             oncontextmenu="showContextMenu(event, this); return false;">
                            <div>
                                <div class="font-medium text-gray-900">{{ $schedule->name }}</div>
                                <div class="text-sm text-gray-600">{{ $schedule->days_display }}</div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Time Schedules -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold text-gray-900">Time Schedules</h2>
                        <button onclick="openTimeScheduleModal()" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-md text-sm font-medium">
                            Add Schedule
                        </button>
                    </div>
                    <p class="text-gray-600 text-sm mt-1">Define work hours and break periods</p>
                </div>
                <div class="p-6">
                    <div id="timeSchedulesContainer" class="space-y-3">
                        @foreach($timeSchedules as $schedule)
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-md hover:bg-gray-100 cursor-pointer schedule-item" 
                             data-schedule-id="{{ $schedule->id }}" 
                             data-schedule-type="time"
                             oncontextmenu="showContextMenu(event, this); return false;">
                            <div>
                                <div class="font-medium text-gray-900">{{ $schedule->name }}</div>
                                <div class="text-sm text-gray-600">{{ $schedule->time_range_display }}</div>
                                <div class="text-xs text-green-600 font-semibold">Total Hours: {{ $schedule->total_hours ?? 'N/A' }}</div>
                                <div class="text-xs text-purple-600 font-semibold">OT Threshold: {{ number_format($schedule->getOvertimeThresholdMinutes() / 60, 1) }} hrs ({{ $schedule->getOvertimeThresholdMinutes() }} min)</div>
                                @if($schedule->break_duration_minutes > 0)
                                    <div class="text-xs text-blue-600">Break: {{ $schedule->break_duration_minutes }} min</div>
                                @elseif($schedule->break_start && $schedule->break_end)
                                    <div class="text-xs text-blue-600">Break: {{ \Carbon\Carbon::parse($schedule->break_start)->format('g:i A') }} - {{ \Carbon\Carbon::parse($schedule->break_end)->format('g:i A') }}</div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Grace Period Settings -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Grace Period Settings</h2>
                    <p class="text-gray-600 text-sm mt-1">Configure time calculation tolerances</p>
                </div>
                <div class="p-6">
                    <form id="gracePeriodForm" class="space-y-4">
                        @csrf
                        <div>
                            <label for="late_grace_minutes" class="block text-sm font-medium text-gray-700">Late Grace Period (minutes)</label>
                            <input type="number" id="late_grace_minutes" name="late_grace_minutes" min="0" max="120" 
                                   value="{{ $gracePeriodData['late_grace_minutes'] }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <p class="text-xs text-gray-500 mt-1">Minutes before deducting from working hours</p>
                        </div>
                        
                        <!-- REMOVED: Overtime Threshold (now schedule-specific) -->
                        {{-- <div class="p-3 bg-blue-50 border border-blue-200 rounded-md">
                            <p class="text-sm text-blue-800">
                                <strong>Note:</strong> Overtime threshold is now automatically calculated from each employee's time schedule working hours. 
                                For example: 8am-5pm with 1hr break = 8 hours (480 minutes) overtime threshold.
                            </p>
                        </div> --}}

                        <div>
                            <label for="undertime_grace_minutes" class="block text-sm font-medium text-gray-700">Undertime Grace Period (minutes)</label>
                            <input type="number" id="undertime_grace_minutes" name="undertime_grace_minutes" min="0" max="120" 
                                   value="{{ $gracePeriodData['undertime_grace_minutes'] }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <p class="text-xs text-gray-500 mt-1">Minutes before deducting for early time out</p>
                        </div>
                        
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-md font-medium">
                            Update Grace Periods
                        </button>
                    </form>
                    
                    <!-- Information Section -->
                   
                </div>
            </div>

            <!-- Night Differential Settings -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Night Differential Settings</h2>
                    <p class="text-gray-600 text-sm mt-1">Configure night shift premium rates</p>
                </div>
                <div class="p-6">
                    <form id="nightDifferentialForm" class="space-y-4">
                        @csrf
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="nd_start_time" class="block text-sm font-medium text-gray-700">Start Time</label>
                                <input type="time" id="nd_start_time" name="start_time" 
                                       value="{{ substr($nightDifferentialData['start_time'], 0, 5) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                            <div>
                                <label for="nd_end_time" class="block text-sm font-medium text-gray-700">End Time</label>
                                <input type="time" id="nd_end_time" name="end_time" 
                                       value="{{ substr($nightDifferentialData['end_time'], 0, 5) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                            </div>
                        </div>
                        
                        <div>
                            <label for="nd_rate_percentage" class="block text-sm font-medium text-gray-700">Night Differential Rate (%)</label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <input type="number" id="nd_rate_percentage" name="rate_percentage" 
                                       value="{{ round(($nightDifferentialData['rate_multiplier'] - 1) * 100, 2) }}" 
                                       min="0" max="100" step="0.01"
                                       class="block w-full pr-8 rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                                       placeholder="10"
                                       oninput="updateMultiplierPreview()">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">%</span>
                                </div>
                            </div>
                            <input type="hidden" id="nd_rate_multiplier" name="rate_multiplier" 
                                   value="{{ $nightDifferentialData['rate_multiplier'] }}">
                            <p class="text-xs text-gray-500 mt-1">
                                Enter percentage • Multiplier: <span id="multiplier_preview">{{ $nightDifferentialData['rate_multiplier'] }}</span>
                            </p>
                        </div>
                        
                        <div>
                            <label for="nd_description" class="block text-sm font-medium text-gray-700">Description</label>
                            <input type="text" id="nd_description" name="description" 
                                   value="{{ $nightDifferentialData['description'] }}"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm"
                                   placeholder="e.g., Standard night differential">
                        </div>
                        
                        <div class="flex items-center">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" id="nd_is_active" name="is_active" value="1" 
                                   {{ $nightDifferentialData['is_active'] ? 'checked' : '' }}
                                   class="h-4 w-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <label for="nd_is_active" class="ml-2 block text-sm text-gray-700">
                                Enable Night Differential
                            </label>
                        </div>
                        
                        <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white py-2 px-4 rounded-md font-medium">
                            Update Night Differential
                        </button>
                    </form>
                    
                    {{-- <div class="mt-4 p-3 bg-purple-50 rounded-md">
                        <p class="text-sm text-purple-800">
                            <strong>Example:</strong> 10 PM - 5 AM with 10% differential applies premium to all hours worked during night shift.
                        </p>
                        <p class="text-xs text-purple-600 mt-1">
                            OT + ND: Regular OT (25%) + ND (10%) = Combined premium for overtime during night shift
                        </p>
                    </div> --}}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Day Schedule Modal -->
<div id="dayScheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="dayScheduleModalTitle">Add Day Schedule</h3>
                <button onclick="closeDayScheduleModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <form id="dayScheduleForm">
                @csrf
                <div class="mb-4">
                    <label for="schedule_name" class="block text-sm font-medium text-gray-700">Schedule Name</label>
                    <input type="text" id="schedule_name" name="name" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                           placeholder="e.g., Monday to Friday">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Working Days</label>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="days[]" value="monday" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm">Monday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="days[]" value="tuesday" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm">Tuesday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="days[]" value="wednesday" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm">Wednesday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="days[]" value="thursday" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm">Thursday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="days[]" value="friday" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm">Friday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="days[]" value="saturday" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm">Saturday</span>
                        </label>
                        <label class="flex items-center">
                            <input type="checkbox" name="days[]" value="sunday" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="ml-2 text-sm">Sunday</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeDayScheduleModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Time Schedule Modal -->
<div id="timeScheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900" id="timeScheduleModalTitle">Add Time Schedule</h3>
                <button onclick="closeTimeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <form id="timeScheduleForm">
                @csrf
                <div class="mb-4">
                    <label for="time_schedule_name" class="block text-sm font-medium text-gray-700">Schedule Name</label>
                    <input type="text" id="time_schedule_name" name="name" required
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                           placeholder="e.g., 8AM to 5PM">
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="time_in" class="block text-sm font-medium text-gray-700">Time In</label>
                        <input type="time" id="time_in" name="time_in" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                    <div>
                        <label for="time_out" class="block text-sm font-medium text-gray-700">Time Out</label>
                        <input type="time" id="time_out" name="time_out" required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>
                </div>
                
                <!-- Break Period Options -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Break Period Type</label>
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="radio" name="break_type" value="none" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" checked onchange="toggleBreakInputs()">
                            <span class="ml-2 text-sm text-gray-700">No Break Period</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="break_type" value="duration" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" onchange="toggleBreakInputs()">
                            <span class="ml-2 text-sm text-gray-700">Break Duration (flexible timing)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="break_type" value="fixed" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" onchange="toggleBreakInputs()">
                            <span class="ml-2 text-sm text-gray-700">Fixed Break Times</span>
                        </label>
                    </div>
                </div>

                <!-- Break Duration Input (shown when "duration" is selected) -->
                <div id="breakDurationSection" class="mb-4 hidden">
                    <label for="break_duration_minutes" class="block text-sm font-medium text-gray-700">Break Duration (minutes)</label>
                    <input type="number" id="break_duration_minutes" name="break_duration_minutes" min="0" max="480"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                           placeholder="e.g., 60">
                    <p class="text-xs text-gray-500 mt-1">Employees can take their break at any time during their shift</p>
                </div>
                
                <!-- Fixed Break Times (shown when "fixed" is selected) -->
                <div id="fixedBreakSection" class="mb-4 hidden">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="break_start" class="block text-sm font-medium text-gray-700">Break Start</label>
                            <input type="time" id="break_start" name="break_start"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="break_end" class="block text-sm font-medium text-gray-700">Break End</label>
                            <input type="time" id="break_end" name="break_end"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Break times are fixed for this schedule</p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeTimeScheduleModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Break Period Modal -->
<div id="breakPeriodModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Break Period</h3>
                <button onclick="closeBreakPeriodModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            
            <form id="breakPeriodForm">
                @csrf
                <!-- Break Period Options -->
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Break Period Type</label>
                    <div class="space-y-3">
                        <label class="flex items-center">
                            <input type="radio" name="break_type" value="none" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" onchange="toggleBreakInputsEdit()">
                            <span class="ml-2 text-sm text-gray-700">No Break Period</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="break_type" value="duration" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" onchange="toggleBreakInputsEdit()">
                            <span class="ml-2 text-sm text-gray-700">Break Duration (flexible timing)</span>
                        </label>
                        <label class="flex items-center">
                            <input type="radio" name="break_type" value="fixed" class="h-4 w-4 text-blue-600 border-gray-300 focus:ring-blue-500" onchange="toggleBreakInputsEdit()">
                            <span class="ml-2 text-sm text-gray-700">Fixed Break Times</span>
                        </label>
                    </div>
                </div>

                <!-- Break Duration Input (shown when "duration" is selected) -->
                <div id="breakDurationSectionEdit" class="mb-4 hidden">
                    <label for="break_duration_edit" class="block text-sm font-medium text-gray-700">Break Duration (minutes)</label>
                    <input type="number" id="break_duration_edit" name="break_duration_minutes" min="0" max="480"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <p class="text-xs text-gray-500 mt-1">Employees can take their break at any time during their shift</p>
                </div>
                
                <!-- Fixed Break Times (shown when "fixed" is selected) -->
                <div id="fixedBreakSectionEdit" class="mb-4 hidden">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="break_start_edit" class="block text-sm font-medium text-gray-700">Break Start</label>
                            <input type="time" id="break_start_edit" name="break_start"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="break_end_edit" class="block text-sm font-medium text-gray-700">Break End</label>
                            <input type="time" id="break_end_edit" name="break_end"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Break times are fixed for this schedule</p>
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeBreakPeriodModal()" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-200 border border-gray-300 rounded-md hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md hover:bg-green-700">
                        Update Break Period
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<!-- Context Menu -->
<div id="contextMenu" class="fixed bg-white rounded-md shadow-lg border border-gray-200 py-1 z-50 hidden min-w-[160px]">
    <div id="contextMenuItems"></div>
</div>

<script>
let currentEditingId = null;
let currentEditingType = null;

// Context Menu Functions
function showContextMenu(event, element) {
    event.preventDefault();
    
    console.log('showContextMenu called', event, element); // Debug log
    // alert('Context menu function called!'); // Temporary alert for testing
    
    const contextMenu = document.getElementById('contextMenu');
    const contextMenuItems = document.getElementById('contextMenuItems');
    
    console.log('contextMenu element:', contextMenu); // Debug log
    console.log('contextMenuItems element:', contextMenuItems); // Debug log
    
    const scheduleId = element.getAttribute('data-schedule-id');
    const scheduleType = element.getAttribute('data-schedule-type');
    
    console.log('scheduleId:', scheduleId, 'scheduleType:', scheduleType); // Debug log
    
    // Clear existing items
    contextMenuItems.innerHTML = '';
    
    if (scheduleType === 'day') {
        // Day Schedule context menu items
        contextMenuItems.innerHTML = `
            <button data-action="edit" data-schedule-id="${scheduleId}" data-schedule-type="day" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Schesdudsale
            </button>
            <button data-action="delete" data-schedule-id="${scheduleId}" data-schedule-type="day" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete Schedule
            </button>
        `;
    } else if (scheduleType === 'time') {
        // Time Schedule context menu items
        contextMenuItems.innerHTML = `
            <button data-action="edit" data-schedule-id="${scheduleId}" data-schedule-type="time" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 flex items-center">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Scdsahedule
            </button>
          
            <button data-action="delete" data-schedule-id="${scheduleId}" data-schedule-type="time" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 flex items-center">
                <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete Schedule
            </button>
        `;
    }

    //   <button data-action="break" data-schedule-id="${scheduleId}" data-schedule-type="time" class="w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-green-50 flex items-center">
    //             <svg class="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    //                 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
    //             </svg>
    //             Edit Break Period
    //         </button>
    
    // Add event listeners to the context menu buttons
    contextMenuItems.querySelectorAll('button').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const action = this.getAttribute('data-action');
            const schedId = parseInt(this.getAttribute('data-schedule-id'));
            const schedType = this.getAttribute('data-schedule-type');
            
            console.log('Context menu action:', action, 'ID:', schedId, 'Type:', schedType);
            
            hideContextMenu();
            
            // Execute the appropriate function
            if (schedType === 'day') {
                if (action === 'edit') {
                    editDaySchedule(schedId);
                } else if (action === 'delete') {
                    deleteDaySchedule(schedId);
                }
            } else if (schedType === 'time') {
                if (action === 'edit') {
                    editTimeSchedule(schedId);
                } else if (action === 'break') {
                    editBreakPeriod(schedId);
                } else if (action === 'delete') {
                    deleteTimeSchedule(schedId);
                }
            }
        });
    });
    
    // Position the context menu
    const rect = contextMenu.getBoundingClientRect();
    const windowWidth = window.innerWidth;
    const windowHeight = window.innerHeight;
    
    let x = event.clientX;
    let y = event.clientY;
    
    // Adjust position to keep menu within viewport
    if (x + 160 > windowWidth) {
        x = windowWidth - 160;
    }
    if (y + contextMenu.offsetHeight > windowHeight) {
        y = windowHeight - contextMenu.offsetHeight;
    }
    
    contextMenu.style.left = x + 'px';
    contextMenu.style.top = y + 'px';
    contextMenu.classList.remove('hidden');
    
    console.log('Context menu should now be visible at:', x, y); // Debug log
}

function hideContextMenu() {
    document.getElementById('contextMenu').classList.add('hidden');
}

// Hide context menu when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('#contextMenu')) {
        hideContextMenu();
    }
});

// Hide context menu when pressing Escape
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideContextMenu();
    }
});

// Day Schedule Functions
function openDayScheduleModal() {
    console.log('openDayScheduleModal called');
    document.getElementById('dayScheduleModal').classList.remove('hidden');
    document.getElementById('dayScheduleModalTitle').textContent = 'Add Day Schedule';
    document.getElementById('dayScheduleForm').reset();
    currentEditingId = null;
    currentEditingType = 'day';
}

function closeDayScheduleModal() {
    document.getElementById('dayScheduleModal').classList.add('hidden');
}

function editDaySchedule(id) {
    console.log('editDaySchedule called with ID:', id);
    
    // Fetch the schedule data and populate the form
    fetch(`{{ url('settings/time-logs/day-schedules') }}/${id}/edit`)
        .then(response => {
            console.log('Fetch response:', response);
            return response.json();
        })
        .then(data => {
            console.log('Fetched data:', data);
            const schedule = data.data;
            document.getElementById('dayScheduleModalTitle').textContent = 'Edit Day Schedule';
            document.getElementById('schedule_name').value = schedule.name;
            
            // Clear all checkboxes first
            document.querySelectorAll('input[name="days[]"]').forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // Check the appropriate days
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            days.forEach(day => {
                if (schedule[day]) {
                    document.querySelector(`input[name="days[]"][value="${day}"]`).checked = true;
                }
            });
            
            currentEditingId = id;
            currentEditingType = 'day';
            console.log('Opening day schedule modal...');
            document.getElementById('dayScheduleModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error fetching day schedule:', error);
        });
}

function deleteDaySchedule(id) {
    if (confirm('Are you sure you want to delete this day schedule?')) {
        fetch(`{{ url('settings/time-logs/day-schedules') }}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.message) {
                alert(data.message);
                if (!data.message.includes('Cannot delete')) {
                    location.reload();
                }
            }
        });
    }
}

// Time Schedule Functions
function openTimeScheduleModal() {
    console.log('openTimeScheduleModal called');
    document.getElementById('timeScheduleModal').classList.remove('hidden');
    document.getElementById('timeScheduleModalTitle').textContent = 'Add Time Schedule';
    document.getElementById('timeScheduleForm').reset();
    
    // Reset radio buttons to "none" and hide all break sections
    document.querySelector('input[name="break_type"][value="none"]').checked = true;
    document.getElementById('breakDurationSection').classList.add('hidden');
    document.getElementById('fixedBreakSection').classList.add('hidden');
    
    currentEditingId = null;
    currentEditingType = 'time';
}

function closeTimeScheduleModal() {
    document.getElementById('timeScheduleModal').classList.add('hidden');
}

function editTimeSchedule(id) {
    console.log('editTimeSchedule called with ID:', id);
    
    fetch(`{{ url('settings/time-logs/time-schedules') }}/${id}`)
        .then(response => {
            console.log('Fetch response:', response);
            return response.json();
        })
        .then(schedule => {
            console.log('Fetched schedule:', schedule);
            document.getElementById('timeScheduleModalTitle').textContent = 'Edit Time Schedule';
            document.getElementById('time_schedule_name').value = schedule.name;
            document.getElementById('time_in').value = schedule.time_in;
            document.getElementById('time_out').value = schedule.time_out;
            
            // Determine break type and set radio buttons accordingly
            let breakType = 'none';
            if (schedule.break_duration_minutes && schedule.break_duration_minutes > 0) {
                breakType = 'duration';
                document.getElementById('break_duration_minutes').value = schedule.break_duration_minutes;
            } else if (schedule.break_start && schedule.break_end) {
                breakType = 'fixed';
                document.getElementById('break_start').value = schedule.break_start;
                document.getElementById('break_end').value = schedule.break_end;
            }
            
            // Set the appropriate radio button
            document.querySelector(`input[name="break_type"][value="${breakType}"]`).checked = true;
            
            // Trigger the toggle function to show/hide appropriate sections
            toggleBreakInputs();
            
            currentEditingId = id;
            currentEditingType = 'time';
            console.log('Opening time schedule modal...');
            document.getElementById('timeScheduleModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error fetching time schedule:', error);
        });
}

function deleteTimeSchedule(id) {
    if (confirm('Are you sure you want to delete this time schedule?')) {
        fetch(`{{ url('settings/time-logs/time-schedules') }}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.message) {
                alert(data.message);
                if (!data.message.includes('Cannot delete')) {
                    location.reload();
                }
            }
        });
    }
}

// Break Period Functions
function editBreakPeriod(id) {
    fetch(`{{ url('settings/time-logs/time-schedules') }}/${id}`)
        .then(response => response.json())
        .then(schedule => {
            // Determine break type and set radio buttons accordingly
            let breakType = 'none';
            if (schedule.break_duration_minutes && schedule.break_duration_minutes > 0) {
                breakType = 'duration';
                document.getElementById('break_duration_edit').value = schedule.break_duration_minutes;
            } else if (schedule.break_start && schedule.break_end) {
                breakType = 'fixed';
                document.getElementById('break_start_edit').value = schedule.break_start;
                document.getElementById('break_end_edit').value = schedule.break_end;
            }
            
            // Set the appropriate radio button in the break period modal
            document.querySelector(`#breakPeriodModal input[name="break_type"][value="${breakType}"]`).checked = true;
            
            // Trigger the toggle function to show/hide appropriate sections
            toggleBreakInputsEdit();
            
            currentEditingId = id;
            document.getElementById('breakPeriodModal').classList.remove('hidden');
        });
}

function closeBreakPeriodModal() {
    document.getElementById('breakPeriodModal').classList.add('hidden');
}

// Form Submissions
document.getElementById('dayScheduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const url = currentEditingId ? `{{ url('settings/time-logs/day-schedules') }}/${currentEditingId}` : '{{ url('settings/time-logs/day-schedules') }}';
    const method = currentEditingId ? 'PUT' : 'POST';
    
    if (currentEditingId) {
        formData.append('_method', 'PUT');
    }
    
    fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.message) {
            alert(data.message);
            closeDayScheduleModal();
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
});

document.getElementById('timeScheduleForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const breakType = document.querySelector('input[name="break_type"]:checked').value;
    
    // Validate break type specific requirements
    if (breakType === 'duration') {
        const durationValue = document.getElementById('break_duration_minutes').value;
        if (!durationValue || durationValue === '' || parseInt(durationValue) <= 0) {
            alert('Please enter a valid break duration in minutes.');
            return;
        }
    } else if (breakType === 'fixed') {
        const startValue = document.getElementById('break_start').value;
        const endValue = document.getElementById('break_end').value;
        if (!startValue || startValue === '' || !endValue || endValue === '') {
            alert('Please enter both break start and end times for fixed break periods.');
            return;
        }
    }
    
    // Handle field values based on break type
    if (breakType === 'none') {
        // For no break, remove all break-related fields completely
        formData.delete('break_duration_minutes');
        formData.delete('break_start');
        formData.delete('break_end');
    } else if (breakType === 'duration') {
        // For duration break, remove fixed time fields and ensure duration has a value
        formData.delete('break_start');
        formData.delete('break_end');
        const durationValue = document.getElementById('break_duration_minutes').value;
        if (!durationValue || durationValue === '') {
            formData.delete('break_duration_minutes');
        }
    } else if (breakType === 'fixed') {
        // For fixed break, remove duration field and ensure start/end times have values
        formData.delete('break_duration_minutes');
        const startValue = document.getElementById('break_start').value;
        const endValue = document.getElementById('break_end').value;
        if (!startValue || startValue === '') {
            formData.delete('break_start');
        }
        if (!endValue || endValue === '') {
            formData.delete('break_end');
        }
    }
    
    const url = currentEditingId ? `{{ url('settings/time-logs/time-schedules') }}/${currentEditingId}` : '{{ url('settings/time-logs/time-schedules') }}';
    
    if (currentEditingId) {
        formData.append('_method', 'PUT');
    }
    
    fetch(url, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.message) {
            alert(data.message);
            closeTimeScheduleModal();
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
});

document.getElementById('breakPeriodForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const breakType = document.querySelector('#breakPeriodModal input[name="break_type"]:checked').value;
    
    // Validate break type specific requirements
    if (breakType === 'duration') {
        const durationValue = document.getElementById('break_duration_edit').value;
        if (!durationValue || durationValue === '' || parseInt(durationValue) <= 0) {
            alert('Please enter a valid break duration in minutes.');
            return;
        }
    } else if (breakType === 'fixed') {
        const startValue = document.getElementById('break_start_edit').value;
        const endValue = document.getElementById('break_end_edit').value;
        if (!startValue || startValue === '' || !endValue || endValue === '') {
            alert('Please enter both break start and end times for fixed break periods.');
            return;
        }
    }
    
    // Handle field values based on break type
    if (breakType === 'none') {
        // For no break, remove all break-related fields completely
        formData.delete('break_duration_minutes');
        formData.delete('break_start');
        formData.delete('break_end');
    } else if (breakType === 'duration') {
        // For duration break, remove fixed time fields and ensure duration has a value
        formData.delete('break_start');
        formData.delete('break_end');
        const durationValue = document.getElementById('break_duration_edit').value;
        if (!durationValue || durationValue === '') {
            formData.delete('break_duration_minutes');
        }
    } else if (breakType === 'fixed') {
        // For fixed break, remove duration field and ensure start/end times have values
        formData.delete('break_duration_minutes');
        const startValue = document.getElementById('break_start_edit').value;
        const endValue = document.getElementById('break_end_edit').value;
        if (!startValue || startValue === '') {
            formData.delete('break_start');
        }
        if (!endValue || endValue === '') {
            formData.delete('break_end');
        }
    }
    
    fetch(`{{ url('settings/time-logs/time-schedules') }}/${currentEditingId}/break-periods`, {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.message) {
            alert(data.message);
            closeBreakPeriodModal();
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
});

document.getElementById('gracePeriodForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('{{ route('settings.time-logs.grace-period.update') }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.message) {
            alert(data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
});

// Function to update multiplier preview in real-time
function updateMultiplierPreview() {
    const percentageInput = document.getElementById('nd_rate_percentage');
    const multiplierPreview = document.getElementById('multiplier_preview');
    const percentage = parseFloat(percentageInput.value) || 0;
    const multiplier = 1 + (percentage / 100);
    multiplierPreview.textContent = multiplier.toFixed(4);
}

// Break Type Toggle Functions
function toggleBreakInputs() {
    const breakType = document.querySelector('input[name="break_type"]:checked').value;
    const durationSection = document.getElementById('breakDurationSection');
    const fixedSection = document.getElementById('fixedBreakSection');
    
    // Hide all sections first
    durationSection.classList.add('hidden');
    fixedSection.classList.add('hidden');
    
    // Show relevant section based on selection
    if (breakType === 'duration') {
        durationSection.classList.remove('hidden');
        // Clear fixed break fields when switching to duration
        document.getElementById('break_start').value = '';
        document.getElementById('break_end').value = '';
    } else if (breakType === 'fixed') {
        fixedSection.classList.remove('hidden');
        // Clear duration field when switching to fixed
        document.getElementById('break_duration_minutes').value = '';
    } else {
        // Clear all fields for "none"
        document.getElementById('break_duration_minutes').value = '';
        document.getElementById('break_start').value = '';
        document.getElementById('break_end').value = '';
    }
}

function toggleBreakInputsEdit() {
    const breakType = document.querySelector('#breakPeriodModal input[name="break_type"]:checked').value;
    const durationSection = document.getElementById('breakDurationSectionEdit');
    const fixedSection = document.getElementById('fixedBreakSectionEdit');
    
    // Hide all sections first
    durationSection.classList.add('hidden');
    fixedSection.classList.add('hidden');
    
    // Show relevant section based on selection
    if (breakType === 'duration') {
        durationSection.classList.remove('hidden');
        // Clear fixed break fields when switching to duration
        document.getElementById('break_start_edit').value = '';
        document.getElementById('break_end_edit').value = '';
    } else if (breakType === 'fixed') {
        fixedSection.classList.remove('hidden');
        // Clear duration field when switching to fixed
        document.getElementById('break_duration_edit').value = '';
    } else {
        // Clear all fields for "none"
        document.getElementById('break_duration_edit').value = '';
        document.getElementById('break_start_edit').value = '';
        document.getElementById('break_end_edit').value = '';
    }
}

// Night Differential Form
document.getElementById('nightDifferentialForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Convert percentage to multiplier before submitting
    const percentageInput = document.getElementById('nd_rate_percentage');
    const multiplierInput = document.getElementById('nd_rate_multiplier');
    const percentage = parseFloat(percentageInput.value) || 0;
    const multiplier = 1 + (percentage / 100);
    multiplierInput.value = multiplier.toFixed(4);
    
    const formData = new FormData(this);
    
    fetch('{{ route("settings.time-logs.night-differential.update") }}', {
        method: 'POST',
        body: formData,
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
        } else {
            alert('Error updating night differential settings');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating night differential settings');
    });
});
</script>
</x-app-layout>
