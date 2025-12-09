<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Employee Configuration') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('settings.employee.update') }}" class="space-y-6">
                @csrf

                <!-- Employee Number Configuration -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Employee Number Configuration</h3>
                        
                        {{-- <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        <strong>Note:</strong> Employee settings are scoped by company. The default prefix is automatically set to your company code ({{ Auth::user()->company->code ?? 'N/A' }}), but you can customize it below.
                                    </p>
                                </div>
                            </div>
                        </div> --}}
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="employee_number_prefix" class="block text-sm font-medium text-gray-700 mb-2">
                                    Employee Number Prefix
                                </label>
                                <input type="text" name="employee_number_prefix" id="employee_number_prefix" 
                                       value="{{ old('employee_number_prefix', $settings['employee_number_prefix']) }}"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       placeholder="EMP" maxlength="10">
                                <p class="mt-1 text-xs text-gray-500">Default is your company code, but you can customize it here</p>
                                <p class="mt-1 text-xs text-gray-400">Format will be: PREFIX-YEAR-NUMBER (e.g., {{ old('employee_number_prefix', $settings['employee_number_prefix'] ?? 'EMP') }}-{{ date('Y') }}-0001)</p>
                                @error('employee_number_prefix')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="employee_number_start" class="block text-sm font-medium text-gray-700 mb-2">
                                    Starting Number
                                </label>
                                <input type="number" name="employee_number_start" id="employee_number_start" min="1"
                                       value="{{ old('employee_number_start', $settings['employee_number_start']) }}"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                @error('employee_number_start')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-4 space-y-3">
                            <div class="flex items-center">
                                <input type="checkbox" name="auto_generate_employee_number" id="auto_generate_employee_number" 
                                       value="1" {{ old('auto_generate_employee_number', $settings['auto_generate_employee_number']) ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                       onchange="toggleAutoGenerate()">
                                <label for="auto_generate_employee_number" class="ml-2 text-sm text-gray-700">
                                    Auto-generate employee numbers (if unchecked, users can enter manually)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Management -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Department Management</h3>
                            <button type="button" onclick="openCreateDepartment()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Add New Department
                            </button>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-600 mb-3">Manage available departments for employee selection:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @forelse($departments as $department)
                                    <div class="department-card bg-white rounded-lg p-4 border shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                                         data-id="{{ $department->id }}" data-name="{{ $department->name }}"
                                         oncontextmenu="showContextMenu(event, 'department', {{ $department->id }}, '{{ $department->name }}'); return false;">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <h4 class="text-sm font-semibold text-gray-900">{{ $department->name }}</h4>
                                                @if($department->code)
                                                    <p class="text-xs text-gray-500 mt-1">Code: {{ $department->code }}</p>
                                                @endif
                                            </div>
                                            <span class="text-xs px-2 py-1 rounded-full {{ $department->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $department->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 col-span-full">No departments found. Add the first department to get started.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Position Management -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Position Management</h3>
                            <button type="button" onclick="openCreatePosition()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Add New Position
                            </button>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-600 mb-3">Manage available positions for employee selection:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @forelse($positions as $position)
                                    <div class="position-card bg-white rounded-lg p-4 border shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                                         data-id="{{ $position->id }}" data-title="{{ $position->title }}"
                                         oncontextmenu="showContextMenu(event, 'position', {{ $position->id }}, '{{ $position->title }}'); return false;">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <h4 class="text-sm font-semibold text-gray-900">{{ $position->title }}</h4>
                                                @if($position->department)
                                                    <p class="text-xs text-gray-500 mt-1">{{ $position->department->name }}</p>
                                                @endif
                                            </div>
                                            <span class="text-xs px-2 py-1 rounded-full {{ $position->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $position->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 col-span-full">No positions found. Add the first position to get started.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                {{-- <!-- Time Schedule Management -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Time Schedule Management</h3>
                            <button type="button" onclick="openCreateTimeSchedule()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Add New Time Schedule
                            </button>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-600 mb-3">Manage available time schedules for employee selection:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @forelse($timeSchedules as $schedule)
                                    <div class="flex items-center justify-between bg-white rounded px-3 py-2 border">
                                        <div>
                                            <span class="text-sm font-medium">{{ $schedule->name }}</span>
                                            <br><span class="text-xs text-gray-500">{{ $schedule->time_range }}</span>
                                            <br><span class="text-xs text-green-600 font-semibold">{{ $schedule->total_hours ?? 'N/A' }} hrs</span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs px-2 py-1 rounded {{ $schedule->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                            <button type="button" onclick="editTimeSchedule({{ $schedule->id }})" 
                                                    class="text-blue-600 hover:text-blue-800 text-xs">Edit</button>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 col-span-full">No time schedules found. Add the first time schedule to get started.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Day Schedule Management -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Day Schedule Management</h3>
                            <button type="button" onclick="openCreateDaySchedule()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Add New Day Schedule
                            </button>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-600 mb-3">Manage available day schedules for employee selection:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                @forelse($daySchedules as $schedule)
                                    <div class="flex items-center justify-between bg-white rounded px-3 py-2 border">
                                        <div>
                                            <span class="text-sm font-medium">{{ $schedule->name }}</span>
                                            <br><span class="text-xs text-gray-500">{{ $schedule->days_display }}</span>
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <span class="text-xs px-2 py-1 rounded {{ $schedule->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                            <button type="button" onclick="editDaySchedule({{ $schedule->id }})" 
                                                    class="text-blue-600 hover:text-blue-800 text-xs">Edit</button>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 col-span-full">No day schedules found. Add the first day schedule to get started.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div> --}}

                <!-- Employment Type Management -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Employment Type Management</h3>
                            <button type="button" onclick="openCreateEmploymentType()" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-sm">
                                Add New Employment Type
                            </button>
                        </div>
                        
                        <div class="bg-gray-50 rounded-lg p-4">
                            <p class="text-sm text-gray-600 mb-3">Manage available employment types with benefit settings for employee selection:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                @forelse($employmentTypes as $employmentType)
                                    <div class="employment-type-card bg-white rounded-lg p-4 border shadow-sm cursor-pointer hover:shadow-md transition-shadow"
                                         data-id="{{ $employmentType->id }}" 
                                         data-name="{{ $employmentType->name }}"
                                         data-has-benefits="{{ $employmentType->has_benefits ? '1' : '0' }}"
                                         data-is-active="{{ $employmentType->is_active ? '1' : '0' }}"
                                         oncontextmenu="showContextMenu(event, 'employmentType', {{ $employmentType->id }}, '{{ $employmentType->name }}'); return false;">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <h4 class="text-sm font-semibold text-gray-900">{{ $employmentType->name }}</h4>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    {{ $employmentType->has_benefits ? 'With Benefits' : 'No Benefits' }}
                                                </p>
                                            </div>
                                            <span class="text-xs px-2 py-1 rounded-full {{ $employmentType->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ $employmentType->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </div>
                                    </div>
                                @empty
                                    <p class="text-sm text-gray-500 col-span-full">No employment types found. Add the first employment type to get started.</p>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-end space-x-3">
                            <button type="submit" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Save Configuration
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Department Modal (Create/Edit) -->
    <div id="departmentModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="departmentModalTitle">Add Department</h3>
                <form id="departmentForm">
                    <input type="hidden" id="departmentId" name="id">
                    <div class="space-y-4">
                        <div>
                            <label for="departmentName" class="block text-sm font-medium text-gray-700">Department Name *</label>
                            <input type="text" id="departmentName" name="name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="departmentCode" class="block text-sm font-medium text-gray-700">Department Code</label>
                            <input type="text" id="departmentCode" name="code" maxlength="10"
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="departmentIsActive" name="is_active" value="1" checked
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <label for="departmentIsActive" class="ml-2 text-sm text-gray-700">Active</label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeDepartmentModal()" 
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Position Modal (Create/Edit) -->
    <div id="positionModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="positionModalTitle">Add Position</h3>
                <form id="positionForm">
                    <input type="hidden" id="positionId" name="id">
                    <div class="space-y-4">
                        <div>
                            <label for="positionTitle" class="block text-sm font-medium text-gray-700">Position Title *</label>
                            <input type="text" id="positionTitle" name="title" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="positionDepartment" class="block text-sm font-medium text-gray-700">Department</label>
                            <select id="positionDepartment" name="department_id"
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select Department</option>
                                @foreach($departments as $department)
                                    @if($department->is_active)
                                        <option value="{{ $department->id }}">{{ $department->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="positionIsActive" name="is_active" value="1" checked
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <label for="positionIsActive" class="ml-2 text-sm text-gray-700">Active</label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closePositionModal()" 
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Time Schedule Modal (Create/Edit) -->
    <div id="timeScheduleModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="timeScheduleModalTitle">Add Time Schedule</h3>
                <form id="timeScheduleForm">
                    <input type="hidden" id="timeScheduleId" name="id">
                    <div class="space-y-4">
                        <div>
                            <label for="timeScheduleName" class="block text-sm font-medium text-gray-700">Schedule Name</label>
                            <input type="text" id="timeScheduleName" name="name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="timeIn" class="block text-sm font-medium text-gray-700">Time In</label>
                                <input type="time" id="timeIn" name="time_in" required 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="timeOut" class="block text-sm font-medium text-gray-700">Time Out</label>
                                <input type="time" id="timeOut" name="time_out" required 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="breakStart" class="block text-sm font-medium text-gray-700">Break Start</label>
                                <input type="time" id="breakStart" name="break_start" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label for="breakEnd" class="block text-sm font-medium text-gray-700">Break End</label>
                                <input type="time" id="breakEnd" name="break_end" 
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeTimeScheduleModal()" 
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Day Schedule Modal (Create/Edit) -->
    <div id="dayScheduleModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="dayScheduleModalTitle">Add Day Schedule</h3>
                <form id="dayScheduleForm">
                    <input type="hidden" id="dayScheduleId" name="id">
                    <div class="space-y-4">
                        <div>
                            <label for="dayScheduleName" class="block text-sm font-medium text-gray-700">Schedule Name</label>
                            <input type="text" id="dayScheduleName" name="name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Working Days</label>
                            <div class="space-y-2">
                                <label class="flex items-center"><input type="checkbox" name="monday" id="monday" class="mr-2"> Monday</label>
                                <label class="flex items-center"><input type="checkbox" name="tuesday" id="tuesday" class="mr-2"> Tuesday</label>
                                <label class="flex items-center"><input type="checkbox" name="wednesday" id="wednesday" class="mr-2"> Wednesday</label>
                                <label class="flex items-center"><input type="checkbox" name="thursday" id="thursday" class="mr-2"> Thursday</label>
                                <label class="flex items-center"><input type="checkbox" name="friday" id="friday" class="mr-2"> Friday</label>
                                <label class="flex items-center"><input type="checkbox" name="saturday" id="saturday" class="mr-2"> Saturday</label>
                                <label class="flex items-center"><input type="checkbox" name="sunday" id="sunday" class="mr-2"> Sunday</label>
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeDayScheduleModal()" 
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Employment Type Modal (Create/Edit) -->
    <div id="employmentTypeModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4" id="employmentTypeModalTitle">Add Employment Type</h3>
                <form id="employmentTypeForm">
                    <input type="hidden" id="employmentTypeId" name="id">
                    <div class="space-y-4">
                        <div>
                            <label for="employmentTypeName" class="block text-sm font-medium text-gray-700">Employment Type Name *</label>
                            <input type="text" id="employmentTypeName" name="name" required 
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="e.g., OJT, Intern, Consultant">
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="employmentTypeHasBenefits" name="has_benefits" value="1"
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <label for="employmentTypeHasBenefits" class="ml-2 text-sm text-gray-700">Includes Benefits (SSS, PhilHealth, Pag-IBIG, etc.)</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="employmentTypeIsActive" name="is_active" value="1" checked
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <label for="employmentTypeIsActive" class="ml-2 text-sm text-gray-700">Active</label>
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3 mt-6">
                        <button type="button" onclick="closeEmploymentTypeModal()" 
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Cancel
                        </button>
                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Context Menu -->
    <div id="contextMenu" class="fixed bg-white border border-gray-200 rounded-lg shadow-lg py-2 z-50 hidden">
        <div class="px-4 py-2 text-xs text-gray-500 border-b" id="contextMenuTitle"></div>
        <button id="editAction" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 text-blue-600 hover:text-blue-800">
            <i class="fas fa-edit mr-2"></i>Edit
        </button>
        <button id="deleteAction" class="w-full text-left px-4 py-2 text-sm hover:bg-gray-50 text-red-600 hover:text-red-800">
            <i class="fas fa-trash mr-2"></i>Delete
        </button>
    </div>

    <script>
        let contextMenuData = {};

        function showContextMenu(event, type, id, name) {
            event.preventDefault();
            event.stopPropagation();
            
            const contextMenu = document.getElementById('contextMenu');
            const contextMenuTitle = document.getElementById('contextMenuTitle');
            
            // Store context data
            contextMenuData = { type, id, name };
            
            // Set title
            contextMenuTitle.textContent = `${type.charAt(0).toUpperCase() + type.slice(1)}: ${name}`;
            
            // Show menu first to get proper dimensions
            contextMenu.classList.remove('hidden');
            
            // Position the context menu using clientX/clientY for accurate mouse positioning
            const rect = contextMenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            let x = event.clientX;
            let y = event.clientY;
            
            // Adjust position to keep menu within viewport
            if (x + rect.width > windowWidth) {
                x = windowWidth - rect.width - 10; // 10px margin
            }
            if (y + rect.height > windowHeight) {
                y = windowHeight - rect.height - 10; // 10px margin
            }
            
            // Ensure minimum distance from edges
            x = Math.max(10, x);
            y = Math.max(10, y);
            
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
        }

        // Hide context menu when clicking elsewhere
        document.addEventListener('click', function() {
            document.getElementById('contextMenu').classList.add('hidden');
        });

        // Handle edit action
        document.getElementById('editAction').addEventListener('click', function() {
            if (contextMenuData.type === 'department') {
                editDepartment(contextMenuData.id);
            } else if (contextMenuData.type === 'position') {
                editPosition(contextMenuData.id);
            } else if (contextMenuData.type === 'employmentType') {
                editEmploymentType(contextMenuData.id);
            }
            document.getElementById('contextMenu').classList.add('hidden');
        });

        // Handle delete action
        document.getElementById('deleteAction').addEventListener('click', function() {
            if (contextMenuData.type === 'department') {
                deleteDepartment(contextMenuData.id, contextMenuData.name);
            } else if (contextMenuData.type === 'position') {
                deletePosition(contextMenuData.id, contextMenuData.name);
            } else if (contextMenuData.type === 'employmentType') {
                deleteEmploymentType(contextMenuData.id, contextMenuData.name);
            }
            document.getElementById('contextMenu').classList.add('hidden');
        });

        // Rest of the JavaScript functions remain the same...
        // Auto-generate employee number toggle function
        function toggleAutoGenerate() {
            // This will be used when saving settings to control behavior in employee creation
            // The actual behavior will be handled in the employee creation form
        }

        // Department Functions
        function openCreateDepartment() {
            document.getElementById('departmentModalTitle').textContent = 'Add Department';
            document.getElementById('departmentForm').reset();
            document.getElementById('departmentId').value = '';
            document.getElementById('departmentIsActive').checked = true;
            document.getElementById('departmentModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function editDepartment(id) {
            document.getElementById('departmentModalTitle').textContent = 'Edit Department';
            document.getElementById('departmentId').value = id;
            
            // Fetch department data
            fetch(`/departments/${id}/edit`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.data) {
                    document.getElementById('departmentName').value = data.data.name || '';
                    document.getElementById('departmentCode').value = data.data.code || '';
                    document.getElementById('departmentIsActive').checked = data.data.is_active ? true : false;
                }
                document.getElementById('departmentModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            })
            .catch(error => {
                console.error('Error fetching department data:', error);
                alert('Error loading department data');
            });
        }

        function closeDepartmentModal() {
            document.getElementById('departmentModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function deleteDepartment(id, name) {
            if (confirm(`Are you sure you want to delete the department "${name}"?`)) {
                fetch(`/departments/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        '_method': 'DELETE'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.message) {
                        location.reload();
                    } else {
                        alert(data.error || 'Error deleting department');
                    }
                })
                .catch(error => {
                    console.error('Error deleting department:', error);
                    alert('Error deleting department');
                });
            }
        }

        // Position Functions
        function openCreatePosition() {
            document.getElementById('positionModalTitle').textContent = 'Add Position';
            document.getElementById('positionForm').reset();
            document.getElementById('positionId').value = '';
            document.getElementById('positionIsActive').checked = true;
            document.getElementById('positionModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function editPosition(id) {
            document.getElementById('positionModalTitle').textContent = 'Edit Position';
            document.getElementById('positionId').value = id;
            
            // Fetch position data
            fetch(`/positions/${id}/edit`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.data && data.data.position) {
                    const position = data.data.position;
                    document.getElementById('positionTitle').value = position.title || '';
                    document.getElementById('positionDepartment').value = position.department_id || '';
                    document.getElementById('positionIsActive').checked = position.is_active ? true : false;
                }
                document.getElementById('positionModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            })
            .catch(error => {
                console.error('Error fetching position data:', error);
                alert('Error loading position data');
            });
        }

        function closePositionModal() {
            document.getElementById('positionModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function deletePosition(id, title) {
            if (confirm(`Are you sure you want to delete the position "${title}"?`)) {
                fetch(`/positions/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        '_method': 'DELETE'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.message) {
                        location.reload();
                    } else {
                        alert(data.error || 'Error deleting position');
                    }
                })
                .catch(error => {
                    console.error('Error deleting position:', error);
                    alert('Error deleting position');
                });
            }
        }

        function deleteEmploymentType(id, name) {
            if (confirm(`Are you sure you want to delete the employment type "${name}"?`)) {
                fetch(`/employment-types/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        '_method': 'DELETE'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success || data.message) {
                        location.reload();
                    } else {
                        alert(data.error || 'Error deleting employment type');
                    }
                })
                .catch(error => {
                    console.error('Error deleting employment type:', error);
                    alert('Error deleting employment type');
                });
            }
        }

        // Form Submissions
        document.getElementById('departmentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const departmentId = document.getElementById('departmentId').value;
            const isEdit = departmentId !== '';
            
            const url = isEdit ? `/departments/${departmentId}` : '/departments';
            const method = isEdit ? 'PUT' : 'POST';
            
            // Convert FormData to regular object for JSON
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key === 'is_active') {
                    data[key] = document.getElementById('departmentIsActive').checked ? 1 : 0;
                } else {
                    data[key] = value;
                }
            }
            
            if (isEdit) {
                data._method = 'PUT';
            }
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.message) {
                    closeDepartmentModal();
                    location.reload(); // Reload to show updated data
                } else {
                    alert('Error saving department');
                }
            })
            .catch(error => {
                console.error('Error saving department:', error);
                alert('Error saving department');
            });
        });

        document.getElementById('positionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const positionId = document.getElementById('positionId').value;
            const isEdit = positionId !== '';
            
            const url = isEdit ? `/positions/${positionId}` : '/positions';
            const method = isEdit ? 'PUT' : 'POST';
            
            // Convert FormData to regular object for JSON
            const data = {};
            for (let [key, value] of formData.entries()) {
                if (key === 'is_active') {
                    data[key] = document.getElementById('positionIsActive').checked ? 1 : 0;
                } else {
                    data[key] = value;
                }
            }
            
            if (isEdit) {
                data._method = 'PUT';
            }
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.message) {
                    closePositionModal();
                    location.reload(); // Reload to show updated data
                } else {
                    alert('Error saving position');
                }
            })
            .catch(error => {
                console.error('Error saving position:', error);
                alert('Error saving position');
            });
        });

        // Employment Type Functions
        function openCreateEmploymentType() {
            document.getElementById('employmentTypeModalTitle').textContent = 'Add Employment Type';
            document.getElementById('employmentTypeForm').reset();
            document.getElementById('employmentTypeId').value = '';
            document.getElementById('employmentTypeModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function editEmploymentType(id) {
            // Fetch employment type data and populate form
            fetch(`/employment-types/${id}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data) {
                        document.getElementById('employmentTypeModalTitle').textContent = 'Edit Employment Type';
                        document.getElementById('employmentTypeId').value = data.id;
                        document.getElementById('employmentTypeName').value = data.name;
                        document.getElementById('employmentTypeHasBenefits').checked = data.has_benefits;
                        document.getElementById('employmentTypeIsActive').checked = data.is_active;
                        document.getElementById('employmentTypeModal').classList.remove('hidden');
                        document.body.style.overflow = 'hidden';
                    }
                })
                .catch(error => {
                    console.error('Error fetching employment type:', error);
                    alert('Error loading employment type data');
                });
        }

        function closeEmploymentTypeModal() {
            document.getElementById('employmentTypeModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Employment Type Form Submit Handler
        document.getElementById('employmentTypeForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const employmentTypeId = document.getElementById('employmentTypeId').value;
            const isEdit = employmentTypeId !== '';
            
            const url = isEdit ? `/employment-types/${employmentTypeId}` : '/employment-types';
            const method = isEdit ? 'PUT' : 'POST';
            
            // Convert FormData to regular object for JSON
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            // Handle checkboxes explicitly
            data.has_benefits = document.getElementById('employmentTypeHasBenefits').checked ? 1 : 0;
            data.is_active = document.getElementById('employmentTypeIsActive').checked ? 1 : 0;
            
            if (isEdit) {
                data._method = 'PUT';
            }
            
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success || data.message) {
                    closeEmploymentTypeModal();
                    location.reload(); // Reload to show updated data
                } else {
                    alert('Error saving employment type');
                }
            })
            .catch(error => {
                console.error('Error saving employment type:', error);
                alert('Error saving employment type');
            });
        });

        // Time Schedule Functions
        function openCreateTimeSchedule() {
            document.getElementById('timeScheduleModalTitle').textContent = 'Add Time Schedule';
            document.getElementById('timeScheduleForm').reset();
            document.getElementById('timeScheduleId').value = '';
            document.getElementById('timeScheduleModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function editTimeSchedule(id) {
            // Fetch schedule data and populate form
            // Implementation would require an AJAX call to get schedule details
            document.getElementById('timeScheduleModalTitle').textContent = 'Edit Time Schedule';
            document.getElementById('timeScheduleId').value = id;
            document.getElementById('timeScheduleModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeTimeScheduleModal() {
            document.getElementById('timeScheduleModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Day Schedule Functions
        function openCreateDaySchedule() {
            document.getElementById('dayScheduleModalTitle').textContent = 'Add Day Schedule';
            document.getElementById('dayScheduleForm').reset();
            document.getElementById('dayScheduleId').value = '';
            document.getElementById('dayScheduleModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function editDaySchedule(id) {
            // Fetch schedule data and populate form
            // Implementation would require an AJAX call to get schedule details
            document.getElementById('dayScheduleModalTitle').textContent = 'Edit Day Schedule';
            document.getElementById('dayScheduleId').value = id;
            document.getElementById('dayScheduleModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeDayScheduleModal() {
            document.getElementById('dayScheduleModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Form Submissions
        document.getElementById('timeScheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Handle time schedule save
            // Implementation would submit via AJAX
            alert('Time schedule save functionality to be implemented');
        });

        document.getElementById('dayScheduleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            // Handle day schedule save
            // Implementation would submit via AJAX
            alert('Day schedule save functionality to be implemented');
        });
    </script>
</x-app-layout>
