<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Edit Employee') }} - {{ $employee->full_name }}
            </h2>
            <a href="{{ route('employees.show', $employee->employee_number) }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Employee
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('employees.update', $employee) }}" class="space-y-6">
                @csrf
                @method('PUT')

                <!-- Personal Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="first_name" class="block text-sm font-medium text-gray-700">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $employee->first_name) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('first_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="middle_name" class="block text-sm font-medium text-gray-700">Middle Name</label>
                                <input type="text" name="middle_name" id="middle_name" value="{{ old('middle_name', $employee->middle_name) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('middle_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="last_name" class="block text-sm font-medium text-gray-700">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $employee->last_name) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('last_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="suffix" class="block text-sm font-medium text-gray-700">Suffix</label>
                                <input type="text" name="suffix" id="suffix" value="{{ old('suffix', $employee->suffix) }}" placeholder="Jr., Sr., III"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('suffix')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="birth_date" class="block text-sm font-medium text-gray-700">Birth Date <span class="text-red-500">*</span></label>
                                <input type="date" name="birth_date" id="birth_date" value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('birth_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gender" class="block text-sm font-medium text-gray-700">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" id="gender" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Gender</option>
                                    <option value="male" {{ old('gender', $employee->gender) == 'male' ? 'selected' : '' }}>Male</option>
                                    <option value="female" {{ old('gender', $employee->gender) == 'female' ? 'selected' : '' }}>Female</option>
                                </select>
                                @error('gender')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="civil_status" class="block text-sm font-medium text-gray-700">Civil Status <span class="text-red-500">*</span></label>
                                <select name="civil_status" id="civil_status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Status</option>
                                    <option value="single" {{ old('civil_status', $employee->civil_status) == 'single' ? 'selected' : '' }}>Single</option>
                                    <option value="married" {{ old('civil_status', $employee->civil_status) == 'married' ? 'selected' : '' }}>Married</option>
                                    <option value="divorced" {{ old('civil_status', $employee->civil_status) == 'divorced' ? 'selected' : '' }}>Divorced</option>
                                    <option value="widowed" {{ old('civil_status', $employee->civil_status) == 'widowed' ? 'selected' : '' }}>Widowed</option>
                                </select>
                                @error('civil_status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="text" name="phone" id="phone" value="{{ old('phone', $employee->phone) }}" placeholder="09XXXXXXXXX"
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
                                        value="{{ old('address', $employee->address) }}">
                                    @error('address')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="md:col-span-1">
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code <span class="text-red-500">*</span></label>
                                    <input type="text" name="postal_code" id="postal_code" required maxlength="4"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        value="{{ old('postal_code', $employee->postal_code) }}" placeholder="0000">
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
                                <input type="email" name="email" id="email" value="{{ old('email', $employee->user->email ?? '') }}" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="role" class="block text-sm font-medium text-gray-700">User Role</label>
                                <select name="role" id="role" disabled class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm cursor-not-allowed">
                                    @php
                                    $currentRole = old('role', $employee->user?->roles->first()?->name) ?? 'No Role Assigned';
                                    @endphp
                                    <option value="{{ $currentRole }}" selected>{{ ucfirst($currentRole) }}</option>
                                </select>
                                <!-- Hidden input to ensure role value is submitted -->
                                <input type="hidden" name="role" value="{{ $currentRole }}">
                                @error('role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                {{-- <p class="mt-1 text-xs text-gray-500">Role cannot be changed from this form. Contact administrator if role change is needed.</p> --}}
                            </div>
                        </div>

                        <p class="mt-2 text-sm text-gray-600">
                            <strong>Note:</strong> To reset the password, leave blank. Otherwise, the current password will remain unchanged.
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
                                <input type="text" name="employee_number" id="employee_number" value="{{ old('employee_number', $employee->employee_number) }}" required
                                    placeholder="EMP-2025-0001"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('employee_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="hire_date" class="block text-sm font-medium text-gray-700">Hire Date <span class="text-red-500">*</span></label>
                                <input type="date" name="hire_date" id="hire_date" value="{{ old('hire_date', $employee->hire_date?->format('Y-m-d')) }}" required
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
                                    <option value="{{ $department->id }}" {{ old('department_id', $employee->department_id) == $department->id ? 'selected' : '' }}>
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
                                    @if($employee->position)
                                    <option value="{{ $employee->position->id }}" selected>{{ $employee->position->title }}</option>
                                    @endif
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
                                        {{ old('employment_type_id', $employee->employment_type_id) == $employmentType->id ? 'selected' : '' }}
                                        data-has-benefits="{{ $employmentType->has_benefits ? '1' : '0' }}">
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
                                    <option value="with_benefits" {{ old('benefits_status', $employee->benefits_status) == 'with_benefits' ? 'selected' : '' }}>With Benefits</option>
                                    <option value="without_benefits" {{ old('benefits_status', $employee->benefits_status) == 'without_benefits' ? 'selected' : '' }}>Without Benefits</option>
                                </select>
                                @error('benefits_status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="day_schedule_id" class="block text-sm font-medium text-gray-700">Day Schedule <span class="text-red-500">*</span></label>
                                <select name="day_schedule_id" id="day_schedule_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Day Schedule</option>
                                    @foreach($daySchedules as $schedule)
                                    <option value="{{ $schedule->id }}" {{ old('day_schedule_id', $employee->daySchedule?->id) == $schedule->id ? 'selected' : '' }}>
                                        {{ $schedule->name }} ({{ $schedule->days_display }})
                                    </option>
                                    @endforeach
                                </select>
                                @error('day_schedule_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="time_schedule_id" class="block text-sm font-medium text-gray-700">Time Schedule <span class="text-red-500">*</span></label>
                                <select name="time_schedule_id" id="time_schedule_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Schedule</option>
                                    @foreach($timeSchedules as $schedule)
                                    <option value="{{ $schedule->id }}" {{ old('time_schedule_id', $employee->timeSchedule?->id) == $schedule->id ? 'selected' : '' }}>
                                        {{ $schedule->name }} ({{ $schedule->time_range_display }})
                                    </option>
                                    @endforeach
                                </select>
                                @error('time_schedule_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pay_schedule" class="block text-sm font-medium text-gray-700">Pay Frequency <span class="text-red-500">*</span></label>
                                <select name="pay_schedule" id="pay_schedule" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Pay Frequency</option>
                                    <option value="daily" {{ old('pay_schedule', $employee->pay_schedule ?? '') == 'daily' ? 'selected' : '' }}>Daily</option>
                                    <option value="weekly" {{ old('pay_schedule', $employee->pay_schedule ?? '') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="semi_monthly" {{ old('pay_schedule', $employee->pay_schedule ?? '') == 'semi_monthly' ? 'selected' : '' }}>Semi-Monthly</option>
                                    <option value="monthly" {{ old('pay_schedule', $employee->pay_schedule ?? '') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                </select>
                                @error('pay_schedule')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pay_schedule_id" class="block text-sm font-medium text-gray-700">Pay Schedule <span class="text-red-500">*</span></label>
                                <select name="pay_schedule_id" id="pay_schedule_id" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select specific pay schedule</option>
                                </select>
                                @error('pay_schedule_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                           
                            </div>

                            <div>
                                <label for="employment_status" class="block text-sm font-medium text-gray-700">Employment Status <span class="text-red-500">*</span></label>
                                <select name="employment_status" id="employment_status" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Status</option>
                                    <option value="active" {{ old('employment_status', $employee->employment_status) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('employment_status', $employee->employment_status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="terminated" {{ old('employment_status', $employee->employment_status) == 'terminated' ? 'selected' : '' }}>Terminated</option>
                                    <option value="resigned" {{ old('employment_status', $employee->employment_status) == 'resigned' ? 'selected' : '' }}>Resigned</option>
                                </select>
                                @error('employment_status')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Salary Information -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Salary Information</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="rate_type" class="block text-sm font-medium text-gray-700">Rate Type <span class="text-red-500">*</span></label>
                                <select name="rate_type" id="rate_type" required class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Rate Type</option>
                                    <option value="hourly" {{ old('rate_type', $employee->rate_type) == 'hourly' ? 'selected' : '' }}>Hourly</option>
                                    <option value="daily" {{ old('rate_type', $employee->rate_type) == 'daily' ? 'selected' : '' }}>Daily</option>
                                    <option value="weekly" {{ old('rate_type', $employee->rate_type) == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    <option value="semi_monthly" {{ old('rate_type', $employee->rate_type) == 'semi_monthly' ? 'selected' : '' }}>Semi-Monthly</option>
                                    <option value="monthly" {{ old('rate_type', $employee->rate_type) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                                </select>
                                @error('rate_type')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="fixed_rate" class="block text-sm font-medium text-gray-700">Rate Amount <span class="text-red-500">*</span></label>
                                <input type="number" name="fixed_rate" id="fixed_rate" step="0.01" min="0" value="{{ old('fixed_rate', $employee->fixed_rate) }}" placeholder="0.00" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('fixed_rate')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Enter the amount for the selected rate type</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Government IDs -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Government IDs</h3>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="sss_number" class="block text-sm font-medium text-gray-700">SSS Number</label>
                                <input type="text" name="sss_number" id="sss_number" value="{{ old('sss_number', $employee->sss_number) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('sss_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="philhealth_number" class="block text-sm font-medium text-gray-700">PhilHealth Number</label>
                                <input type="text" name="philhealth_number" id="philhealth_number" value="{{ old('philhealth_number', $employee->philhealth_number) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('philhealth_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="pagibig_number" class="block text-sm font-medium text-gray-700">Pag-IBIG Number</label>
                                <input type="text" name="pagibig_number" id="pagibig_number" value="{{ old('pagibig_number', $employee->pagibig_number) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('pagibig_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="tin_number" class="block text-sm font-medium text-gray-700">TIN Number</label>
                                <input type="text" name="tin_number" id="tin_number" value="{{ old('tin_number', $employee->tin_number) }}"
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
                                <input type="text" name="emergency_contact_name" id="emergency_contact_name" value="{{ old('emergency_contact_name', $employee->emergency_contact_name) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('emergency_contact_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="emergency_contact_relationship" class="block text-sm font-medium text-gray-700">Relationship</label>
                                <input type="text" name="emergency_contact_relationship" id="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $employee->emergency_contact_relationship) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('emergency_contact_relationship')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="emergency_contact_phone" class="block text-sm font-medium text-gray-700">Phone Number</label>
                                <input type="text" name="emergency_contact_phone" id="emergency_contact_phone" value="{{ old('emergency_contact_phone', $employee->emergency_contact_phone) }}"
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
                                <input type="text" name="bank_name" id="bank_name" value="{{ old('bank_name', $employee->bank_name) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('bank_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bank_account_number" class="block text-sm font-medium text-gray-700">Account Number</label>
                                <input type="text" name="bank_account_number" id="bank_account_number" value="{{ old('bank_account_number', $employee->bank_account_number) }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('bank_account_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="bank_account_name" class="block text-sm font-medium text-gray-700">Account Name</label>
                                <input type="text" name="bank_account_name" id="bank_account_name" value="{{ old('bank_account_name', $employee->bank_account_name) }}"
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
                                Update Employee
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

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

            // Postal code: exactly 4 digits
            function formatPostalCode(input) {
                input.addEventListener('input', function() {
                    // Remove all non-numeric characters
                    let value = this.value.replace(/\D/g, '');
                    // Limit to 4 digits
                    if (value.length > 4) {
                        value = value.substring(0, 4);
                    }
                    this.value = value;
                });

                input.addEventListener('keypress', function(e) {
                    // Only allow numbers
                    if (!/[0-9]/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Escape', 'Enter'].includes(e.key)) {
                        e.preventDefault();
                    }
                });
            }

            // Email to lowercase
            function emailLowercase(input) {
                input.addEventListener('input', function() {
                    this.value = this.value.toLowerCase();
                });
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

            // Postal code field
            const postalCodeInput = document.getElementById('postal_code');
            if (postalCodeInput) {
                formatPostalCode(postalCodeInput);
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

            // Email field
            const emailInput = document.getElementById('email');
            if (emailInput) {
                emailLowercase(emailInput);
            }

            // Department and Position Dynamic Filtering
            function setupDepartmentPositionFilter() {
                const departmentSelect = document.getElementById('department_id');
                const positionSelect = document.getElementById('position_id');

                if (departmentSelect && positionSelect) {
                    // Store current position value for restoration
                    const currentPositionId = '{{ old("position_id", $employee->position_id ?? "") }}';

                    function filterPositions() {
                        const selectedDeptId = departmentSelect.value;

                        // Store the current selected position before clearing
                        const currentSelection = positionSelect.value;

                        // Clear current position options
                        positionSelect.innerHTML = '<option value="">Select Position</option>';

                        if (selectedDeptId) {
                            const fetchUrl = `{{ url('/') }}/departments/${selectedDeptId}/positions`;

                            // Fetch positions for the selected department
                            fetch(fetchUrl)
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error(`HTTP error! status: ${response.status}`);
                                    }
                                    return response.json();
                                })
                                .then(positions => {
                                    positions.forEach(position => {
                                        const option = document.createElement('option');
                                        option.value = position.id;
                                        option.textContent = position.title;

                                        // Check if this is the current employee's position
                                        if (position.id == currentPositionId) {
                                            option.selected = true;
                                        }

                                        positionSelect.appendChild(option);
                                    });

                                    // Also try to restore the previously selected value
                                    if (currentSelection && positionSelect.querySelector(`option[value="${currentSelection}"]`)) {
                                        positionSelect.value = currentSelection;
                                    }
                                })
                                .catch(error => {
                                    console.error('Error fetching positions:', error);
                                });
                        }
                    }

                    // Add event listener for department changes
                    departmentSelect.addEventListener('change', filterPositions);

                    // Initial load if department is already selected - this is crucial for edit view
                    if (departmentSelect.value) {
                        filterPositions();
                    }
                }
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



                    if (selectedOption && selectedOption.value) {
                        const hasBenefits = selectedOption.getAttribute('data-has-benefits') === '1';



                        if (hasBenefits) {
                            benefitsStatusSelect.value = 'with_benefits';
                        } else {
                            benefitsStatusSelect.value = 'without_benefits';
                        }

                        // Trigger change event for any other listeners
                        benefitsStatusSelect.dispatchEvent(new Event('change'));

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
            const currentPayScheduleId = '{{ old('pay_schedule_id', $employee->pay_schedule_id ?? '') }}';

            function loadPaySchedules(type, selectValue = null) {
                if (type) {
                    payScheduleSelect.innerHTML = '<option value="">Loading...</option>';
                    payScheduleSelect.disabled = true;

                    fetch(`{{ url('employees/pay-schedules') }}/${type}`, {
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
                                    
                                    // Select the current value if it matches
                                    if (selectValue && schedule.id == selectValue) {
                                        option.selected = true;
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
            }

            payFrequencySelect.addEventListener('change', function() {
                loadPaySchedules(this.value);
            });

            // Load initial schedules if there's a selected pay frequency
            if (payFrequencySelect.value) {
                loadPaySchedules(payFrequencySelect.value, currentPayScheduleId);
            }
        });
    </script>
</x-app-layout>