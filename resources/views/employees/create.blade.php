<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Add New Employee') }}
            </h2>
            <a href="{{ route('employees.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Employees
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('employees.store') }}" class="space-y-6">
                @csrf

                <!-- Personal Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" id="first_name" value="{{ old('first_name') }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" value="{{ old('middle_name') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('middle_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" id="last_name" value="{{ old('last_name') }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="suffix" class="block text-sm font-medium text-gray-700">Suffix</label>
                                <input type="text" name="suffix" id="suffix" value="{{ old('suffix') }}" placeholder="Jr., Sr., III"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('suffix')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="birth_date" class="block text-sm font-medium text-gray-700">Birth Date <span class="text-red-500">*</span></label>
                                <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date') }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('birth_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" id="gender" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Gender</option>
                                    <option value="male" {{ old('gender') == 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                                </select>
                                @error('gender')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="civil_status" class="block text-sm font-medium text-gray-700">Civil Status <span class="text-red-500">*</span></label>
                                <select name="civil_status" id="civil_status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Status</option>
                                    <option value="single" {{ old('civil_status') == 'single' ? 'selected' : '' }}>Single</option>
                                    <option value="married" {{ old('civil_status') == 'married' ? 'selected' : '' }}>Married</option>
                                    <option value="divorced" {{ old('civil_status') == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                    <option value="widowed" {{ old('civil_status') == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                </select>
                                @error('civil_status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="text" name="phone" id="phone" value="{{ old('phone') }}" placeholder="09XXXXXXXXX"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div class="md:col-span-2">
                                    <label for="address" class="block text-sm font-medium text-gray-700">Address <span class="text-red-500">*</span></label>
                                    <input type="text" name="address" id="address" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        value="{{ old('address') }}">
                                    @error('address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="md:col-span-1">
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code <span class="text-red-500">*</span></label>
                                    <input type="text" name="postal_code" id="postal_code" required maxlength="4"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        value="{{ old('postal_code') }}" placeholder="0000">
                                    @error('postal_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700">Email Address <span class="text-red-500">*</span></label>
                                @php
                                    // Get email domain from system settings
                                    $emailDomain = \App\Models\Setting::get('email_domain', 'gmail.com');
                                @endphp
                                <input type="email" name="email" id="email" value="{{ old('email', '@' . $emailDomain) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm lowercase-input">
                                @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">User Role <span class="text-red-500">*</span></label>
                                <select name="role" id="role" required disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm cursor-not-allowed">
                                    <option value="employee" selected>Employee</option>
                                </select>
                                <!-- Hidden input to ensure role value is submitted -->
                                <input type="hidden" name="role" value="employee">
                                @error('role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                {{-- <p class="mt-1 text-xs text-gray-500">Role is automatically set to Employee for new employee registrations</p> --}}
                            </div>
                        </div>

                        <p class="mt-2 text-sm text-gray-600">
                            <strong>Note:</strong> Default password will be set to the <span id="password-preview">employee number</span>. The employee should change this on first login.
                        </p>
                    </div>
                </div>

                <!-- Employment Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Employment Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="employee_number" class="block text-sm font-medium text-gray-700">Employee Number <span class="text-red-500">*</span></label>
                                <div class="mt-1 flex">
                                    <input type="text" name="employee_number" id="employee_number" value="{{ old('employee_number', ($employeeSettings['auto_generate_employee_number'] ?? false) ? $nextEmployeeNumber : '') }}" required
                                        placeholder="{{ ($employeeSettings['auto_generate_employee_number'] ?? false) ? $nextEmployeeNumber : 'Enter employee number' }}"
                                        class="block w-full {{ ($employeeSettings['auto_generate_employee_number'] ?? false) ? 'rounded-l-md' : 'rounded-md' }} border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        {{ ($employeeSettings['auto_generate_employee_number'] ?? false) ? 'readonly' : '' }}>
                                    @if($employeeSettings['auto_generate_employee_number'] ?? false)
                                    <button type="button" id="generate-employee-number"
                                        class="inline-flex items-center px-3 py-2 border border-l-0 border-gray-300 rounded-r-md bg-blue-50 text-blue-600 text-sm hover:bg-blue-100">
                                        Generate
                                    </button>
                                    @endif
                                </div>
                                @if($employeeSettings['auto_generate_employee_number'] ?? false)
                                {{-- <p class="mt-1 text-xs text-gray-500">Employee number will be automatically generated</p> --}}
                                @else
                                {{-- <p class="mt-1 text-xs text-gray-500">Enter employee number manually</p> --}}
                                @endif
                                @error('employee_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="hire_date" class="block text-sm font-medium text-gray-700">Hire Date <span class="text-red-500">*</span></label>
                                <input type="date" name="hire_date" id="hire_date" value="{{ old('hire_date') }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('hire_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="department_id" class="block text-sm font-medium text-gray-700">Department <span class="text-red-500">*</span></label>
                                <select name="department_id" id="department_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Department</option>
                                    @foreach($departments as $department)
                                    <option value="{{ $department->id }}"
                                        {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('department_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="position_id" class="block text-sm font-medium text-gray-700">Position <span class="text-red-500">*</span></label>
                                <select name="position_id" id="position_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Position</option>
                                    {{-- JavaScript will populate this based on department selection --}}
                                </select>
                                @error('position_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="employment_type_id" class="block text-sm font-medium text-gray-700">Employment Type <span class="text-red-500">*</span></label>
                                <select name="employment_type_id" id="employment_type_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Type</option>
                                    @foreach($employmentTypes as $employmentType)
                                    <option value="{{ $employmentType->id }}"
                                        data-has-benefits="{{ $employmentType->has_benefits ? '1' : '0' }}"
                                        {{ old('employment_type_id') == $employmentType->id ? 'selected' : '' }}>
                                        {{ $employmentType->name }}
                                    </option>
                                    @endforeach
                                </select>
                                @error('employment_type_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="benefits_status" class="block text-sm font-medium text-gray-700">Benefits Status <span class="text-red-500">*</span></label>
                                <select name="benefits_status" id="benefits_status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Benefits Status</option>
                                    <option value="with_benefits" {{ old('benefits_status') == 'with_benefits' ? 'selected' : '' }}>With Benefits</option>
                                    <option value="without_benefits" {{ old('benefits_status') == 'without_benefits' ? 'selected' : '' }}>Without Benefits</option>
                                </select>
                                @error('benefits_status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                {{-- <p class="mt-1 text-xs text-gray-500">Determines eligibility for health insurance, SSS, etc.</p> --}}
                            </div>

                            <div>
                                <label for="day_schedule_id" class="block text-sm font-medium text-gray-700">Day Schedule <span class="text-red-500">*</span></label>
                                <select name="day_schedule_id" id="day_schedule_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Day Schedule</option>
                                    @foreach($daySchedules as $daySchedule)
                                    <option value="{{ $daySchedule->id }}"
                                        {{ old('day_schedule_id') == $daySchedule->id ? 'selected' : '' }}>
                                        {{ $daySchedule->name }} ({{ $daySchedule->days_display }})
                                    </option>
                                    @endforeach
                                </select>
                                @error('day_schedule_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                {{-- <p class="mt-1 text-xs text-gray-500">Working days for accurate DTR calculation</p> --}}
                            </div>

                            <div>
                                <label for="time_schedule_id" class="block text-sm font-medium text-gray-700">Time Schedule <span class="text-red-500">*</span></label>
                                <select name="time_schedule_id" id="time_schedule_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Schedule</option>
                                    @foreach($timeSchedules as $schedule)
                                    <option value="{{ $schedule->id }}"
                                        {{ old('time_schedule_id') == $schedule->id ? 'selected' : '' }}>
                                        {{ $schedule->name }} ({{ $schedule->time_range_display }})
                                    </option>
                                    @endforeach
                                    {{-- <option value="custom" {{ old('time_schedule_id') == 'custom' ? 'selected' : '' }}>
                                    Custom Time Schedule
                                    </option> --}}
                                </select>
                                @error('time_schedule_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                {{-- <p class="mt-1 text-xs text-gray-500">Employee's shift timing (e.g., 8:00 AM - 5:00 PM)</p> --}}
                            </div>

                            <div>
                                <label for="pay_schedule" class="block text-sm font-medium text-gray-700">Pay Frequency <span class="text-red-500">*</span></label>
                                <select name="pay_schedule" id="pay_schedule" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Pay Frequency</option>
                                    <option value="daily" {{ old('pay_schedule') == 'daily' ? 'selected' : '' }}>Daily</option>
                                    <option value="weekly" {{ old('pay_schedule') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="semi_monthly" {{ old('pay_schedule') == 'semi_monthly' ? 'selected' : '' }}>Semi-Monthly</option>
                                    <option value="monthly" {{ old('pay_schedule') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                </select>
                                @error('pay_schedule')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pay_schedule_id" class="block text-sm font-medium text-gray-700">Pay Schedule <span class="text-red-500">*</span></label>
                                <select name="pay_schedule_id" id="pay_schedule_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" disabled>
                                    <option value="">First select pay frequency</option>
                                </select>
                                @error('pay_schedule_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                             
                            </div>

                            <!-- Empty div to maintain grid balance -->
                            <div></div>
                        </div>
                    </div>
                </div>

                <!-- Hidden input to automatically set employment status to active -->
                <input type="hidden" name="employment_status" value="active">

                <!-- Salary Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Salary Information</h3>

                        <!-- Rate Input Section -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="rate_type" class="block text-sm font-medium text-gray-700">Rate Type <span class="text-red-500">*</span></label>
                                <select id="rate_type" name="rate_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Rate Type</option>
                                    <option value="hourly" {{ old('rate_type') == 'hourly' ? 'selected' : '' }}>Hourly</option>
                                    <option value="daily" {{ old('rate_type') == 'daily' ? 'selected' : '' }}>Daily</option>
                                    <option value="weekly" {{ old('rate_type') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="semi_monthly" {{ old('rate_type') == 'semi_monthly' ? 'selected' : '' }}>Semi-Monthly</option>
                                    <option value="monthly" {{ old('rate_type') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                </select>
                                @error('rate_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="fixed_rate" class="block text-sm font-medium text-gray-700">Rate Amount <span class="text-red-500">*</span></label>
                                <input type="number" id="fixed_rate" name="fixed_rate" step="0.01" min="0" value="{{ old('fixed_rate') }}" placeholder="0.00" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('fixed_rate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                {{-- <p class="mt-1 text-xs text-gray-500">Enter the amount for the selected rate type</p> --}}
                            </div>
                        </div>

                        <!-- Salary Calculation Summary -->
                        {{-- <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">Salary Breakdown</h4>
                            <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs text-gray-600">
                                <div>Hourly: <span id="calc_hourly" class="font-medium text-gray-900">₱0.00</span></div>
                                <div>Daily: <span id="calc_daily" class="font-medium text-gray-900">₱0.00</span></div>
                                <div>Weekly: <span id="calc_weekly" class="font-medium text-gray-900">₱0.00</span></div>
                                <div>Semi-Monthly: <span id="calc_semi" class="font-medium text-gray-900">₱0.00</span></div>
                                <div>Monthly: <span id="calc_monthly" class="font-medium text-gray-900">₱0.00</span></div>
                            </div>
                        </div> --}}
                    </div>
                </div>

                <!-- Government IDs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Government IDs</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sss_number" class="block text-sm font-medium text-gray-700">SSS Number</label>
                                <input type="text" name="sss_number" id="sss_number" value="{{ old('sss_number') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('sss_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="philhealth_number" class="block text-sm font-medium text-gray-700">PhilHealth Number</label>
                                <input type="text" name="philhealth_number" id="philhealth_number" value="{{ old('philhealth_number') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('philhealth_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pagibig_number" class="block text-sm font-medium text-gray-700">Pag-IBIG Number</label>
                                <input type="text" name="pagibig_number" id="pagibig_number" value="{{ old('pagibig_number') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('pagibig_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tin_number" class="block text-sm font-medium text-gray-700">TIN Number</label>
                                <input type="text" name="tin_number" id="tin_number" value="{{ old('tin_number') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('tin_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Emergency Contact -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Emergency Contact</h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="emergency_contact_name" class="block text-sm font-medium text-gray-700">Contact Name</label>
                                <input type="text" name="emergency_contact_name" id="emergency_contact_name" value="{{ old('emergency_contact_name') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('emergency_contact_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="emergency_contact_relationship" class="block text-sm font-medium text-gray-700">Relationship</label>
                                <input type="text" name="emergency_contact_relationship" id="emergency_contact_relationship" value="{{ old('emergency_contact_relationship') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('emergency_contact_relationship')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" value="{{ old('emergency_contact_phone') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('emergency_contact_phone')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bank Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Bank Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="bank_name" class="block text-sm font-medium text-gray-700">Bank Name</label>
                                <input type="text" name="bank_name" id="bank_name" value="{{ old('bank_name') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('bank_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bank_account_number" class="block text-sm font-medium text-gray-700">Account Number</label>
                                <input type="text" name="bank_account_number" id="bank_account_number" value="{{ old('bank_account_number') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('bank_account_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bank_account_name" class="block text-sm font-medium text-gray-700">Account Name</label>
                                <input type="text" name="bank_account_name" id="bank_account_name" value="{{ old('bank_account_name') }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('bank_account_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-end space-x-4">
                            <a href="{{ route('employees.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Create Employee
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
                    // Auto-capitalize function for names and address
                    function capitalizeWords(input) {
                        let value = input.value;
                        // Split by spaces and capitalize first letter of each word
                        let words = value.split(' ').map(word => {
                            if (word.length > 0) {
                                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
                            }
                            return word;
                        });
                        input.value = words.join(' ');
                    }

                    // Add event listeners for capitalize inputs
                    document.querySelectorAll('.capitalize-input').forEach(function(input) {
                        input.addEventListener('input', function() {
                            capitalizeWords(this);
                        });

                        input.addEventListener('blur', function() {
                            capitalizeWords(this);
                        });
                    });

                    // Email lowercase conversion
                    const emailInput = document.querySelector('.lowercase-input');
                    if (emailInput) {
                        emailInput.addEventListener('input', function() {
                            this.value = this.value.toLowerCase();
                        });
                    }

                    // Update password preview when employee number changes
                    const employeeNumberInput = document.getElementById('employee_number');
                    const passwordPreview = document.getElementById('password-preview');
                    const generateBtn = document.getElementById('generate-employee-number');

                    // Auto-generate employee number on page load if enabled
                    @if($employeeSettings['auto_generate_employee_number'] ?? false)
                    window.addEventListener('load', function() {
                        // Only auto-generate if field is empty (no old value)
                        if (!employeeNumberInput.value.trim()) {
                            generateEmployeeNumber();
                        }
                    });
                    @endif

                    // Generate employee number function
                    function generateEmployeeNumber() {
                        generateBtn.disabled = true;
                        generateBtn.textContent = 'Loading...';

                        fetch('{{ route('settings.employee.next-number') }}')
                            .then(response => response.json())
                            .then(data => {
                                    employeeNumberInput.value = data.employee_number;
                                    updatePasswordPreview();
                                })
                                .catch(error => {
                                    console.error('Error generating employee number:', error);
                                    alert('Failed to generate employee number. Please try again.');
                                })
                                .finally(() => {
                                    generateBtn.disabled = false;
                                    generateBtn.textContent = 'Generate';
                                });
                            }

                        // Bind generate button click
                        if (generateBtn) {
                            generateBtn.addEventListener('click', generateEmployeeNumber);
                        }

                        // Check for duplicate employee numbers on input
                        let duplicateCheckTimeout;
                        if (employeeNumberInput) {
                            employeeNumberInput.addEventListener('input', function() {
                                clearTimeout(duplicateCheckTimeout);
                                duplicateCheckTimeout = setTimeout(() => {
                                    checkEmployeeNumberDuplicate(this.value);
                                }, 500); // Debounce for 500ms
                            });
                        }

                        function checkEmployeeNumberDuplicate(employeeNumber) {
                            if (!employeeNumber.trim()) return;

                            fetch('{{ route('employees.check-duplicate') }}', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                        },
                                        body: JSON.stringify({
                                            employee_number: employeeNumber
                                        })
                                    })
                                .then(response => response.json())
                                .then(data => {
                                    const existingError = employeeNumberInput.parentElement.parentElement.querySelector('.duplicate-error');
                                    if (existingError) {
                                        existingError.remove();
                                    }

                                    if (data.exists) {
                                        const errorDiv = document.createElement('p');
                                        errorDiv.className = 'mt-1 text-sm text-red-600 duplicate-error';
                                        errorDiv.textContent = 'This employee number is already taken.';
                                        employeeNumberInput.parentElement.parentElement.appendChild(errorDiv);
                                        employeeNumberInput.classList.add('border-red-500');
                                    } else {
                                        employeeNumberInput.classList.remove('border-red-500');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error checking duplicate:', error);
                                });
                        }

                        if (employeeNumberInput && passwordPreview) {
                            function updatePasswordPreview() {
                                const empNumber = employeeNumberInput.value.trim();
                                if (empNumber) {
                                    passwordPreview.textContent = `"${empNumber}"`;
                                    passwordPreview.style.fontWeight = 'bold';
                                    passwordPreview.style.color = '#1f2937'; // gray-800
                                } else {
                                    passwordPreview.textContent = 'employee number';
                                    passwordPreview.style.fontWeight = 'normal';
                                    passwordPreview.style.color = '#6b7280'; // gray-500
                                }
                            }

                            // Update on input change
                            employeeNumberInput.addEventListener('input', updatePasswordPreview);
                            employeeNumberInput.addEventListener('blur', updatePasswordPreview);

                            // Initial update if there's already a value
                            updatePasswordPreview();
                        }

                        // Phone number validation - only allow numbers
                        const phoneInput = document.getElementById('phone');
                        if (phoneInput) {
                            phoneInput.addEventListener('input', function() {
                                // Remove any non-digit characters
                                this.value = this.value.replace(/\D/g, '');

                                // Limit to 11 digits
                                if (this.value.length > 11) {
                                    this.value = this.value.slice(0, 11);
                                }
                            });

                            phoneInput.addEventListener('keypress', function(e) {
                                // Only allow numbers
                                if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Escape', 'Enter'].includes(e.key)) {
                                    e.preventDefault();
                                }
                            });
                        }

                        // Postal code validation - only allow numbers, exactly 4 digits
                        const postalCodeInput = document.getElementById('postal_code');
                        if (postalCodeInput) {
                            postalCodeInput.addEventListener('input', function() {
                                // Remove any non-digit characters
                                this.value = this.value.replace(/\D/g, '');

                                // Limit to 4 digits
                                if (this.value.length > 4) {
                                    this.value = this.value.slice(0, 4);
                                }
                            });

                            postalCodeInput.addEventListener('keypress', function(e) {
                                // Only allow numbers
                                if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Escape', 'Enter'].includes(e.key)) {
                                    e.preventDefault();
                                }
                            });
                        }



                        // Department and Position Dynamic Filtering
                        const departmentSelect = document.getElementById('department_id');
                        const positionSelect = document.getElementById('position_id');

                        if (departmentSelect && positionSelect) {
                            // Store all positions for filtering
                            const allPositions = Array.from(positionSelect.options).slice(1); // Remove the first "Select Position" option

                            function filterPositions() {
                                const selectedDeptId = departmentSelect.value;

                                // Clear current position options (keep the first default option)
                                positionSelect.innerHTML = '<option value="">Select Position</option>';

                                if (selectedDeptId) {
                                    // Filter and add relevant positions
                                    const relevantPositions = allPositions.filter(option =>
                                        option.dataset.departmentId === selectedDeptId
                                    );

                                    relevantPositions.forEach(option => {
                                        positionSelect.appendChild(option.cloneNode(true));
                                    });

                                    // If only one position available, auto-select it
                                    if (relevantPositions.length === 1) {
                                        positionSelect.value = relevantPositions[0].value;
                                    }
                                }

                                // Reset position selection if current selection is not valid for new department
                                if (positionSelect.value && !Array.from(positionSelect.options).some(opt => opt.value === positionSelect.value)) {
                                    positionSelect.value = '';
                                }
                            }

                            // Event listener for department change
                            departmentSelect.addEventListener('change', filterPositions);

                            // Initialize on page load
                            filterPositions();
                        }

                        // Custom Schedule Handlers
                        const timeScheduleSelect = document.getElementById('time_schedule_id');
                        const dayScheduleSelect = document.getElementById('day_schedule_id');
                        const customTimeModal = document.getElementById('custom-time-modal');
                        const customDayModal = document.getElementById('custom-day-modal');

                        // Custom time schedule modal handlers
                        if (timeScheduleSelect && customTimeModal) {
                            timeScheduleSelect.addEventListener('change', function() {
                                if (this.value === 'custom') {
                                    customTimeModal.classList.remove('hidden');
                                    document.body.style.overflow = 'hidden';
                                }
                            });

                            // Close modal buttons
                            const closeTimeModal = () => {
                                customTimeModal.classList.add('hidden');
                                document.body.style.overflow = 'auto';
                                timeScheduleSelect.value = timeScheduleSelect.dataset.previousValue || '';
                            };

                            customTimeModal.querySelector('[data-action="close"]').addEventListener('click', closeTimeModal);
                            customTimeModal.querySelector('[data-action="cancel"]').addEventListener('click', closeTimeModal);

                            // Save custom time schedule
                            customTimeModal.querySelector('[data-action="save"]').addEventListener('click', function() {
                                const form = customTimeModal.querySelector('form');
                                const formData = new FormData(form);

                                fetch('{{ route("settings.time-schedules.store") }}', {
                                        method: 'POST',
                                        body: formData,
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Add new option to select
                                            const newOption = document.createElement('option');
                                            newOption.value = data.schedule.id;
                                            newOption.textContent = data.schedule.name + ' (' + data.schedule.time_in + ' - ' + data.schedule.time_out + ')';
                                            timeScheduleSelect.insertBefore(newOption, timeScheduleSelect.lastElementChild);

                                            // Select the new option
                                            timeScheduleSelect.value = data.schedule.id;

                                            // Close modal
                                            closeTimeModal();

                                            // Reset form
                                            form.reset();

                                            alert('Time schedule created successfully!');
                                        } else {
                                            alert('Error creating time schedule: ' + (data.message || 'Unknown error'));
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('Error creating time schedule. Please try again.');
                                    });
                            });

                            // Store previous value for cancel functionality
                            timeScheduleSelect.addEventListener('focus', function() {
                                this.dataset.previousValue = this.value;
                            });
                        }

                        // Custom day schedule modal handlers
                        if (dayScheduleSelect && customDayModal) {
                            dayScheduleSelect.addEventListener('change', function() {
                                if (this.value === 'custom') {
                                    customDayModal.classList.remove('hidden');
                                    document.body.style.overflow = 'hidden';
                                }
                            });

                            // Close modal buttons
                            const closeDayModal = () => {
                                customDayModal.classList.add('hidden');
                                document.body.style.overflow = 'auto';
                                dayScheduleSelect.value = dayScheduleSelect.dataset.previousValue || '';
                            };

                            customDayModal.querySelector('[data-action="close"]').addEventListener('click', closeDayModal);
                            customDayModal.querySelector('[data-action="cancel"]').addEventListener('click', closeDayModal);

                            // Save custom day schedule
                            customDayModal.querySelector('[data-action="save"]').addEventListener('click', function() {
                                const form = customDayModal.querySelector('form');
                                const formData = new FormData(form);

                                fetch('{{ route("settings.day-schedules.store") }}', {
                                        method: 'POST',
                                        body: formData,
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                                        }
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.success) {
                                            // Add new option to select
                                            const newOption = document.createElement('option');
                                            newOption.value = data.schedule.id;
                                            newOption.textContent = data.schedule.name + ' (' + data.schedule.days_display + ')';
                                            dayScheduleSelect.insertBefore(newOption, dayScheduleSelect.lastElementChild);

                                            // Select the new option
                                            dayScheduleSelect.value = data.schedule.id;

                                            // Close modal
                                            closeDayModal();

                                            // Reset form
                                            form.reset();

                                            alert('Day schedule created successfully!');
                                        } else {
                                            alert('Error creating day schedule: ' + (data.message || 'Unknown error'));
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error:', error);
                                        alert('Error creating day schedule. Please try again.');
                                    });
                            });

                            // Store previous value for cancel functionality
                            dayScheduleSelect.addEventListener('focus', function() {
                                this.dataset.previousValue = this.value;
                            });
                        }
                    });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ===== INPUT FORMATTING FUNCTIONS =====

            // Auto Capitalize after spaces
            function autoCapitalize(input) {
                input.addEventListener('input', function() {
                    let value = this.value;
                    // Capitalize first letter and letters after spaces
                    value = value.replace(/\b\w/g, function(char) {
                        return char.toUpperCase();
                    });
                    this.value = value;
                });
            }

            // Phone number: 11 digits max/min, numbers only
            function formatPhoneNumber(input) {
                input.addEventListener('input', function() {
                    // Remove all non-numeric characters
                    let value = this.value.replace(/\D/g, '');
                    // Limit to 11 digits
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    this.value = value;
                });

                input.addEventListener('blur', function() {
                    // Validate on blur - must be exactly 11 digits
                    if (this.value && this.value.length !== 11) {
                        this.setCustomValidity('Phone number must be exactly 11 digits');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            // Numbers only (for account numbers, SSS, PhilHealth, Pag-IBIG, TIN)
            function numbersOnly(input) {
                input.addEventListener('input', function() {
                    // Remove all non-numeric characters
                    this.value = this.value.replace(/\D/g, '');
                });
            }

            // Email to lowercase
            function emailLowercase(input) {
                input.addEventListener('input', function() {
                    this.value = this.value.toLowerCase();
                });
            }

            // Department-Position filtering
            function setupDepartmentPositionFilter() {
                const departmentSelect = document.getElementById('department_id');
                const positionSelect = document.getElementById('position_id');

                if (departmentSelect && positionSelect) {
                    function filterPositions() {
                        const selectedDeptId = departmentSelect.value;

                        // Clear current position options
                        positionSelect.innerHTML = '<option value="">Select Position</option>';

                        if (selectedDeptId) {
                            // Fetch positions for the selected department
                            fetch(`{{ url('/') }}/departments/${selectedDeptId}/positions`)
                                .then(response => response.json())
                                .then(positions => {
                                    positions.forEach(position => {
                                        const option = document.createElement('option');
                                        option.value = position.id;
                                        option.textContent = position.title;
                                        positionSelect.appendChild(option);
                                    });

                                    // Auto-select if there's an old value
                                    const oldPositionId = '{{ old("position_id") }}';
                                    if (oldPositionId) {
                                        positionSelect.value = oldPositionId;
                                    }
                                })
                                .catch(error => {
                                    console.error('Error fetching positions:', error);
                                });
                        }
                    }

                    departmentSelect.addEventListener('change', filterPositions);

                    // Handle form validation errors - restore selected values if any
                    const oldDepartmentId = '{{ old("department_id") }}';
                    if (oldDepartmentId) {
                        departmentSelect.value = oldDepartmentId;
                        filterPositions();
                    }
                }
            }

            // ===== APPLY FORMATTING TO INPUTS =====

            // Auto Capitalize fields
            const capitalizeFields = [
                'first_name', 'middle_name', 'last_name', 'suffix', 'address',
                'emergency_contact_name', 'emergency_contact_relationship',
                'bank_name', 'bank_account_name'
            ];

            capitalizeFields.forEach(fieldId => {
                const input = document.getElementById(fieldId);
                if (input) {
                    autoCapitalize(input);
                }
            });

            // Phone number field
            const phoneInput = document.getElementById('phone');
            if (phoneInput) {
                formatPhoneNumber(phoneInput);
            }

            // Emergency contact phone field
            const emergencyPhoneInput = document.getElementById('emergency_contact_phone');
            if (emergencyPhoneInput) {
                formatPhoneNumber(emergencyPhoneInput);
            }

            // Numbers only fields
            const numberFields = [
                'bank_account_number', 'sss_number', 'philhealth_number',
                'pagibig_number', 'tin_number'
            ];

            numberFields.forEach(fieldId => {
                const input = document.getElementById(fieldId);
                if (input) {
                    numbersOnly(input);
                }
            });

            // Email field with domain protection
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailLowercase(emailInput);
                
                const emailDomain = '@' + '{{ \App\Models\Setting::get("email_domain", "gmail.com") }}';
                
                // Initialize the email field with domain if empty
                if (!emailInput.value || emailInput.value === emailDomain) {
                    emailInput.value = emailDomain;
                }
                
                // Handle input to prevent domain deletion
                emailInput.addEventListener('input', function(e) {
                    const value = this.value;
                    
                    // Always ensure the domain is present
                    if (!value.includes(emailDomain)) {
                        this.value = emailDomain;
                        // Move cursor to the beginning (before @)
                        this.setSelectionRange(0, 0);
                    } else if (!value.endsWith(emailDomain)) {
                        // If domain is not at the end, fix it
                        const atIndex = value.indexOf('@');
                        if (atIndex > -1) {
                            const username = value.substring(0, atIndex);
                            this.value = username + emailDomain;
                        } else {
                            this.value = emailDomain;
                        }
                    }
                });
                
                // Handle keydown to prevent deleting the @ and domain
                emailInput.addEventListener('keydown', function(e) {
                    const value = this.value;
                    const cursorPos = this.selectionStart;
                    const atIndex = value.indexOf('@');
                    
                    // Prevent deletion of @ and anything after it
                    if (atIndex > -1) {
                        // Backspace
                        if (e.key === 'Backspace' && cursorPos <= atIndex + 1) {
                            if (cursorPos === atIndex + 1) {
                                e.preventDefault();
                                this.setSelectionRange(atIndex, atIndex);
                            }
                        }
                        // Delete
                        if (e.key === 'Delete' && cursorPos >= atIndex) {
                            e.preventDefault();
                        }
                        // Arrow right - don't allow cursor past @
                        if (e.key === 'ArrowRight' && cursorPos >= atIndex) {
                            e.preventDefault();
                        }
                        // Prevent selection of domain part
                        if (cursorPos > atIndex && this.selectionEnd > atIndex) {
                            if (e.key !== 'ArrowLeft' && e.key !== 'Home') {
                                this.setSelectionRange(atIndex, atIndex);
                            }
                        }
                    }
                });
                
                // Handle click to prevent cursor placement after @
                emailInput.addEventListener('click', function(e) {
                    const atIndex = this.value.indexOf('@');
                    if (atIndex > -1 && this.selectionStart > atIndex) {
                        this.setSelectionRange(atIndex, atIndex);
                    }
                });
                
                // Handle selection to prevent selecting domain part
                emailInput.addEventListener('select', function(e) {
                    const atIndex = this.value.indexOf('@');
                    if (atIndex > -1 && this.selectionEnd > atIndex) {
                        this.setSelectionRange(Math.min(this.selectionStart, atIndex), atIndex);
                    }
                });
                
                // Handle paste to preserve domain
                emailInput.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                    const atIndex = this.value.indexOf('@');
                    const cursorPos = this.selectionStart;
                    
                    if (atIndex > -1 && cursorPos <= atIndex) {
                        const before = this.value.substring(0, cursorPos);
                        const after = this.value.substring(this.selectionEnd, atIndex);
                        const username = before + pastedText + after;
                        this.value = username + emailDomain;
                        const newCursorPos = (before + pastedText).length;
                        this.setSelectionRange(newCursorPos, newCursorPos);
                    }
                });
            }

            // Setup department-position filtering
            setupDepartmentPositionFilter();

            // ===== EMPLOYMENT TYPE AUTO-SELECTION =====
            // Auto-select benefits status based on employment type
            const employmentTypeSelect = document.getElementById('employment_type_id');
            const benefitsStatusSelect = document.getElementById('benefits_status');

            if (employmentTypeSelect && benefitsStatusSelect) {
                employmentTypeSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];

                    console.log('Employment type changed:', {
                        selectedValue: this.value,
                        selectedText: selectedOption ? selectedOption.text : 'none',
                        hasBenefitsData: selectedOption ? selectedOption.getAttribute('data-has-benefits') : 'none'
                    });

                    if (selectedOption && selectedOption.value) {
                        const hasBenefits = selectedOption.getAttribute('data-has-benefits') === '1';

                        console.log('Setting benefits status to:', hasBenefits ? 'with_benefits' : 'without_benefits');

                        if (hasBenefits) {
                            benefitsStatusSelect.value = 'with_benefits';
                        } else {
                            benefitsStatusSelect.value = 'without_benefits';
                        }

                        // Trigger change event for any other listeners
                        benefitsStatusSelect.dispatchEvent(new Event('change'));

                        console.log('Benefits status now set to:', benefitsStatusSelect.value);
                    }
                });

                // Also trigger on page load if there's a selected employment type
                if (employmentTypeSelect.value) {
                    employmentTypeSelect.dispatchEvent(new Event('change'));
                }
            }
        });

        // Pay Schedule Dynamic Loading
        document.addEventListener('DOMContentLoaded', function() {
            const payFrequencySelect = document.getElementById('pay_schedule');
            const payScheduleSelect = document.getElementById('pay_schedule_id');

            payFrequencySelect.addEventListener('change', function() {
                const selectedType = this.value;
                
                // Reset pay schedule dropdown
                payScheduleSelect.innerHTML = '<option value="">Loading...</option>';
                payScheduleSelect.disabled = true;

                if (selectedType) {
                    // Fetch pay schedules for selected type
                    fetch(`{{ url('employees/pay-schedules') }}/${selectedType}`, {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        credentials: 'same-origin'
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            payScheduleSelect.innerHTML = '<option value="">Select specific pay schedule</option>';
                            
                            if (data.length > 0) {
                                data.forEach(schedule => {
                                    const option = document.createElement('option');
                                    option.value = schedule.id;
                                    option.textContent = schedule.name;
                                    if (schedule.is_default) {
                                        option.textContent += ' (Default)';
                                    }
                                    payScheduleSelect.appendChild(option);
                                });
                                payScheduleSelect.disabled = false;
                            } else {
                                payScheduleSelect.innerHTML = '<option value="">No schedules available for this type</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching pay schedules:', error);
                            payScheduleSelect.innerHTML = `<option value="">Error loading schedules (${error.message})</option>`;
                        });
                } else {
                    payScheduleSelect.innerHTML = '<option value="">First select pay frequency</option>';
                    payScheduleSelect.disabled = true;
                }
            });
        });
    </script>

    <script src="{{ asset('js/salary-calculator.js') }}"></script>
</x-app-layout>