<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('BIR Form 2316') }} - {{ $employee->first_name }} {{ $employee->last_name }}
            </h2>
            <div class="flex space-x-2">
                <form method="POST" action="{{ route('bir-2316.individual.generate', $employee->id) }}" class="inline">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="export" value="excel">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Download Excel
                    </button>
                </form>
                <form method="POST" action="{{ route('bir-2316.individual.generate', $employee->id) }}" class="inline">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="export" value="pdf">
                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-4 rounded flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Download PDF
                    </button>
                </form>
                <form method="POST" action="{{ route('bir-2316.individual.generate.filled', $employee->id) }}" class="inline">
                    @csrf
                    <input type="hidden" name="year" value="{{ $year }}">
                    <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded flex items-center">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Download Filled PDF
                    </button>
                </form>
                <a href="{{ route('government-forms.bir-2316.employees', ['year' => $year]) }}" 
                   class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <!-- Employee Information Header -->
                    <div class="mb-8 bg-gray-50 p-6 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Employee Information</h3>
                                <p class="text-sm text-gray-600"><strong>Name:</strong> {{ $data['employee']['name'] ?? ($employee->first_name . ' ' . $employee->last_name) }}</p>
                                <p class="text-sm text-gray-600"><strong>Employee Number:</strong> {{ $employee->employee_number }}</p>
                                <p class="text-sm text-gray-600"><strong>TIN:</strong> {{ $data['employee']['tin'] ?? 'Not Available' }}</p>
                                <p class="text-sm text-gray-600"><strong>Address:</strong> {{ $data['employee']['address'] ?? 'Not Available' }}</p>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Employment Details</h3>
                                <p class="text-sm text-gray-600"><strong>Position:</strong> {{ $employee->position->name ?? 'N/A' }}</p>
                                <p class="text-sm text-gray-600"><strong>Department:</strong> {{ $employee->department->name ?? 'N/A' }}</p>
                                <p class="text-sm text-gray-600"><strong>Employment Type:</strong> {{ $employee->employmentType->name ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Employer Information</h3>
                                <p class="text-sm text-gray-600"><strong>Company:</strong> {{ $data['employer']['name'] ?? 'N/A' }}</p>
                                <p class="text-sm text-gray-600"><strong>TIN:</strong> {{ $data['employer']['tin'] ?? 'N/A' }}</p>
                                <p class="text-sm text-gray-600"><strong>Tax Year:</strong> {{ $year }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- BIR 2316 Form Data -->
                    <div class="bg-white border-2 border-gray-200 rounded-lg p-8">
                        <div class="text-center mb-8">
                            <h1 class="text-2xl font-bold text-gray-900">CERTIFICATE OF COMPENSATION PAYMENT/TAX WITHHELD</h1>
                            <h2 class="text-xl font-semibold text-gray-700 mt-2">BIR Form No. 2316</h2>
                            <p class="text-sm text-gray-600 mt-1">For the Year {{ $year }}</p>
                        </div>

                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <!-- Compensation Summary -->
                            <div class="space-y-6">
                                <div class="bg-blue-50 p-4 rounded-lg">
                                    <h3 class="text-lg font-semibold text-blue-900 mb-4">Compensation Summary</h3>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Basic Salary:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['basic_salary'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Overtime Pay:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['overtime_pay'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Night Differential:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['night_differential'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Holiday Pay:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['holiday_pay'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Allowances:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['allowances'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Bonuses:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['bonuses'], 2) }}</span>
                                        </div>
                                        <hr class="my-2">
                                        <div class="flex justify-between font-semibold text-blue-900">
                                            <span>Gross Compensation:</span>
                                            <span>₱{{ number_format($data['gross_compensation'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Non-Taxable Benefits -->
                                <div class="bg-green-50 p-4 rounded-lg">
                                    <h3 class="text-lg font-semibold text-green-900 mb-4">Non-Taxable Benefits</h3>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Non-Taxable 13th Month:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['non_taxable_13th_month'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Non-Taxable De Minimis:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['non_taxable_de_minimis'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Deductions and Tax -->
                            <div class="space-y-6">
                                <div class="bg-yellow-50 p-4 rounded-lg">
                                    <h3 class="text-lg font-semibold text-yellow-900 mb-4">Statutory Contributions</h3>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">SSS Contribution:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['sss_contribution'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">PhilHealth Contribution:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['philhealth_contribution'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Pag-IBIG Contribution:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['pagibig_contribution'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Union Dues:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['union_dues'], 2) }}</span>
                                        </div>
                                        <hr class="my-2">
                                        <div class="flex justify-between font-semibold text-yellow-900">
                                            <span>Total Contributions:</span>
                                            <span>₱{{ number_format($data['total_contributions'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Tax Information -->
                                <div class="bg-red-50 p-4 rounded-lg">
                                    <h3 class="text-lg font-semibold text-red-900 mb-4">Tax Information</h3>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Taxable Compensation:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['taxable_compensation'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between font-semibold text-red-900">
                                            <span>Tax Withheld:</span>
                                            <span>₱{{ number_format($data['tax_withheld'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Net Pay Summary -->
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Summary</h3>
                                    <div class="space-y-2">
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Gross Compensation:</span>
                                            <span class="text-sm font-medium">₱{{ number_format($data['gross_compensation'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Less: Contributions:</span>
                                            <span class="text-sm font-medium">-₱{{ number_format($data['total_contributions'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-sm text-gray-700">Less: Tax Withheld:</span>
                                            <span class="text-sm font-medium">-₱{{ number_format($data['tax_withheld'], 2) }}</span>
                                        </div>
                                        <hr class="my-2">
                                        <div class="flex justify-between font-semibold text-gray-900 text-lg">
                                            <span>Net Pay:</span>
                                            <span>₱{{ number_format($data['gross_compensation'] - $data['total_contributions'] - $data['tax_withheld'], 2) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Note for empty data -->
                        @if($data['gross_compensation'] == 0)
                        <div class="mt-8 bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded">
                            <p class="text-center">
                                <strong>Note:</strong> No payroll data found for {{ $year }}. The form will be generated with basic employee information only.
                                You can still download the Excel/PDF template for manual completion.
                            </p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>