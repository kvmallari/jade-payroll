<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('BIR Form 2316 Settings') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Configure BIR Form 2316 settings for tax year {{ $selectedYear }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('government-forms.bir-2316.employees') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to BIR 2316
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
            @endif

            <!-- Year Selector -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Select Tax Year</h3>
                    <form method="GET" action="{{ route('government-forms.bir-2316.settings') }}" class="flex items-center space-x-4">
                        <div>
                            <label for="year" class="block text-sm font-medium text-gray-700">Tax Year</label>
                            <select name="year" id="year" onchange="this.form.submit()"
                                class="mt-1 block rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="{{ $currentYear }}" {{ $selectedYear == $currentYear ? 'selected' : '' }}>{{ $currentYear }} (Current Year)</option>
                                <option value="{{ $lastYear }}" {{ $selectedYear == $lastYear ? 'selected' : '' }}>{{ $lastYear }} (Last Year)</option>
                            </select>
                        </div>
                        <div class="text-sm text-gray-600 pt-6">
                            <strong>Note:</strong> Each tax year has its own independent settings.
                        </div>
                    </form>
                </div>
            </div>

            <!-- Settings Form -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-6">BIR 2316 Configuration for {{ $selectedYear }}</h3>

                    <form method="POST" action="{{ route('government-forms.bir-2316.settings.update') }}" class="space-y-6">
                        @csrf
                        <input type="hidden" name="tax_year" value="{{ $selectedYear }}">

                        <!-- Period From and To -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="period_from" class="block text-sm font-medium text-gray-700">
                                    Period From (Month-Day)
                                </label>
                                <input type="text"
                                    name="period_from"
                                    id="period_from"
                                    placeholder="MM-DD (e.g., 01-01)"
                                    pattern="^\d{2}-\d{2}$"
                                    maxlength="5"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('period_from', $settings->period_from) }}">
                                <p class="mt-1 text-xs text-gray-500">Format: MM-DD (e.g., 01-01 for January 1st)</p>
                                @error('period_from')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="period_to" class="block text-sm font-medium text-gray-700">
                                    Period To (Month-Day)
                                </label>
                                <input type="text"
                                    name="period_to"
                                    id="period_to"
                                    placeholder="MM-DD (e.g., 12-31)"
                                    pattern="^\d{2}-\d{2}$"
                                    maxlength="5"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('period_to', $settings->period_to) }}">
                                <p class="mt-1 text-xs text-gray-500">Format: MM-DD (e.g., 12-31 for December 31st)</p>
                                @error('period_to')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Statutory Minimum Wage Rates -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="statutory_minimum_wage_per_day" class="block text-sm font-medium text-gray-700">
                                    Statutory Minimum Wage Rate Per Day
                                </label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">₱</span>
                                    </div>
                                    <input type="number"
                                        name="statutory_minimum_wage_per_day"
                                        id="statutory_minimum_wage_per_day"
                                        step="0.01"
                                        min="0"
                                        max="99999999.99"
                                        class="block w-full pl-7 pr-12 sm:text-sm rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="0.00"
                                        value="{{ old('statutory_minimum_wage_per_day', $settings->statutory_minimum_wage_per_day) }}">
                                </div>
                                @error('statutory_minimum_wage_per_day')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="statutory_minimum_wage_per_month" class="block text-sm font-medium text-gray-700">
                                    Statutory Minimum Wage Rate Per Month
                                </label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">₱</span>
                                    </div>
                                    <input type="number"
                                        name="statutory_minimum_wage_per_month"
                                        id="statutory_minimum_wage_per_month"
                                        step="0.01"
                                        min="0"
                                        max="99999999.99"
                                        class="block w-full pl-7 pr-12 sm:text-sm rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="0.00"
                                        value="{{ old('statutory_minimum_wage_per_month', $settings->statutory_minimum_wage_per_month) }}">
                                </div>
                                @error('statutory_minimum_wage_per_month')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Issue Information -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label for="place_of_issue" class="block text-sm font-medium text-gray-700">
                                    Place of Issue
                                </label>
                                <input type="text"
                                    name="place_of_issue"
                                    id="place_of_issue"
                                    maxlength="255"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    placeholder="Enter place of issue"
                                    value="{{ old('place_of_issue', $settings->place_of_issue) }}">
                                @error('place_of_issue')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="date_issued" class="block text-sm font-medium text-gray-700">
                                    Date Issued
                                </label>
                                <input type="date"
                                    name="date_issued"
                                    id="date_issued"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('date_issued', $settings->date_issued ? $settings->date_issued->format('Y-m-d') : '') }}">
                                @error('date_issued')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="amount_paid_ctc" class="block text-sm font-medium text-gray-700">
                                    Amount Paid CTC
                                </label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">₱</span>
                                    </div>
                                    <input type="number"
                                        name="amount_paid_ctc"
                                        id="amount_paid_ctc"
                                        step="0.01"
                                        min="0"
                                        max="999999999999.99"
                                        class="block w-full pl-7 pr-12 sm:text-sm rounded-md border-gray-300 focus:ring-indigo-500 focus:border-indigo-500"
                                        placeholder="0.00"
                                        value="{{ old('amount_paid_ctc', $settings->amount_paid_ctc) }}">
                                </div>
                                @error('amount_paid_ctc')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Signature Dates -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="date_signed_by_authorized_person" class="block text-sm font-medium text-gray-700">
                                    Date Signed by Authorized Person
                                </label>
                                <input type="date"
                                    name="date_signed_by_authorized_person"
                                    id="date_signed_by_authorized_person"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('date_signed_by_authorized_person', $settings->date_signed_by_authorized_person ? $settings->date_signed_by_authorized_person->format('Y-m-d') : '') }}">
                                @error('date_signed_by_authorized_person')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="date_signed_by_employee" class="block text-sm font-medium text-gray-700">
                                    Date Signed by Employee
                                </label>
                                <input type="date"
                                    name="date_signed_by_employee"
                                    id="date_signed_by_employee"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                    value="{{ old('date_signed_by_employee', $settings->date_signed_by_employee ? $settings->date_signed_by_employee->format('Y-m-d') : '') }}">
                                @error('date_signed_by_employee')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-3 pt-6 border-t">
                            <a href="{{ route('government-forms.bir-2316.employees') }}"
                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                                Cancel
                            </a>
                            <button type="submit"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Save Settings for {{ $selectedYear }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Format period inputs to MM-DD format
            const periodFromInput = document.getElementById('period_from');
            const periodToInput = document.getElementById('period_to');

            function formatPeriodInput(input) {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, ''); // Remove non-digits
                    if (value.length >= 2) {
                        value = value.slice(0, 2) + '-' + value.slice(2, 4);
                    }
                    e.target.value = value;
                });

                input.addEventListener('keypress', function(e) {
                    // Allow backspace, delete, tab, escape, enter
                    if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                        // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                        (e.keyCode === 65 && e.ctrlKey === true) ||
                        (e.keyCode === 67 && e.ctrlKey === true) ||
                        (e.keyCode === 86 && e.ctrlKey === true) ||
                        (e.keyCode === 88 && e.ctrlKey === true)) {
                        return;
                    }
                    // Ensure that it is a number and stop the keypress
                    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                        e.preventDefault();
                    }
                });
            }

            formatPeriodInput(periodFromInput);
            formatPeriodInput(periodToInput);
        });
    </script>
</x-app-layout>