<x-app-layout>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Create Deduction/Tax Setting</h1>
            <a href="{{ route('settings.deductions.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                Back to Deductions
            </a>
        </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('settings.deductions.store') }}">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Deduction Name</label>
                    <input type="text" name="name" id="name" 
                           class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                           value="{{ old('name') }}" required>
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-2">Deduction Type</label>
                    <select name="type" id="type" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Select Type</option>
                        <option value="government" {{ old('type') == 'government' ? 'selected' : '' }}>Government</option>
                        {{-- <option value="loan" {{ old('type') == 'loan' ? 'selected' : '' }}>Loan</option> --}}
                        <option value="custom" {{ old('type') == 'custom' ? 'selected' : '' }}>Custom</option>
                    </select>
                    @error('type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Calculation Type and Rate Percentage/Fixed Amount -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="calculation_type" class="block text-sm font-medium text-gray-700 mb-2">Calculation Type</label>
                    <select name="calculation_type" id="calculation_type" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Select Calculation Type</option>
                        <option value="percentage" {{ old('calculation_type') == 'percentage' ? 'selected' : '' }}>Percentage</option>
                        <option value="fixed_amount" {{ old('calculation_type') == 'fixed_amount' ? 'selected' : '' }}>Fixed Amount</option>
                        <option value="sss_table" data-deduction="sss" style="display: none;" {{ old('calculation_type') == 'bracket' && old('tax_table_type') == 'sss' ? 'selected' : '' }}>SSS Table</option>
                        <option value="philhealth_table" data-deduction="philhealth" style="display: none;" {{ old('calculation_type') == 'bracket' && old('tax_table_type') == 'philhealth' ? 'selected' : '' }}>PhilHealth Table</option>
                        <option value="pagibig_table" data-deduction="pagibig" style="display: none;" {{ old('calculation_type') == 'bracket' && old('tax_table_type') == 'pagibig' ? 'selected' : '' }}>Pag-IBIG Table</option>
                        <option value="withholding_tax_table" data-deduction="withholding_tax" style="display: none;" {{ old('calculation_type') == 'bracket' && old('tax_table_type') == 'withholding_tax' ? 'selected' : '' }}>Withholding Tax Table</option>
                    </select>
                    @error('calculation_type')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="percentage_field" style="display: none;">
                    <label for="rate_percentage" class="block text-sm font-medium text-gray-700 mb-2">Rate Percentage (%)</label>
                    <input type="number" name="rate_percentage" id="rate_percentage" min="0" max="100" step="0.01"
                           class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                           placeholder="Enter percentage (e.g., 10 for 10%)"
                           value="{{ old('rate_percentage') }}">
                    @error('rate_percentage')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="fixed_amount_field" style="display: none;">
                    <label for="fixed_amount" class="block text-sm font-medium text-gray-700 mb-2">Fixed Amount</label>
                    <input type="number" name="fixed_amount" id="fixed_amount" step="0.01" min="0"
                           class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                           value="{{ old('fixed_amount') }}">
                    @error('fixed_amount')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Frequency and Distribution Method -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label for="frequency" class="block text-sm font-medium text-gray-700 mb-2">Frequency</label>
                    <select name="frequency" id="frequency" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Select Frequency</option>
                        <option value="per_payroll" {{ old('frequency') == 'per_payroll' ? 'selected' : '' }}>Per Payroll</option>
                        <option value="monthly" {{ old('frequency') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ old('frequency') == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="annually" {{ old('frequency') == 'annually' ? 'selected' : '' }}>Annually</option>
                    </select>
                    @error('frequency')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="distribution_method_field">
                    <label for="distribution_method" class="block text-sm font-medium text-gray-700 mb-2">Distribution Method</label>
                    <select name="distribution_method" id="distribution_method" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Select Distribution Method</option>
                        <option value="last_payroll" {{ old('distribution_method') == 'last_payroll' ? 'selected' : '' }}>Last Payroll Only</option>
                        <option value="equally_distributed" {{ old('distribution_method') == 'equally_distributed' ? 'selected' : '' }}>Equally Distributed</option>
                    </select>
                    @error('distribution_method')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    {{-- <p class="mt-1 text-xs text-gray-500">Choose how the amount is distributed across payrolls within the frequency period.</p> --}}
                </div>
            </div>

            <!-- Hidden Tax Table Type field -->
            <input type="hidden" name="tax_table_type" id="hidden_tax_table_type" value="{{ old('tax_table_type') }}">

            <!-- Pay Basis and Apply To -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <label for="pay_basis" class="block text-sm font-medium text-gray-700 mb-2">Pay Basis</label>
                    <select name="pay_basis" id="pay_basis" 
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Select Pay Basis</option>
                        <option value="gross_pay" {{ old('pay_basis', 'gross_pay') === 'gross_pay' ? 'selected' : '' }}>
                            Total Gross - basic + OT + holiday pay + allowances + bonus
                        </option>
                        <option value="taxable_income" {{ old('pay_basis') === 'taxable_income' ? 'selected' : '' }}>
                            Taxable Income - includes only taxable allowances and bonuses
                        </option>
                        <option value="monthly_basic_salary" {{ old('pay_basis') === 'monthly_basic_salary' ? 'selected' : '' }}>
                            Monthly Basic Salary - employee's monthly basic salary
                        </option>
                    </select>
                    
                    <div class="mt-2 text-xs text-gray-500">
                        <p><strong>Total Gross:</strong> Includes all earnings</p>
                        <p><strong>Taxable Income:</strong> Only taxable items</p>
                        <p><strong>Monthly Basic Salary:</strong> For government contributions</p>
                    </div>
                    
                    @error('pay_basis')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    
                    <!-- Hidden fields for backward compatibility -->
                    <input type="hidden" name="apply_to_basic_pay" id="hidden_apply_to_basic_pay" value="0">
                    <input type="hidden" name="apply_to_gross_pay" id="hidden_apply_to_gross_pay" value="1">
                    <input type="hidden" name="apply_to_taxable_income" id="hidden_apply_to_taxable_income" value="0">
                    <input type="hidden" name="apply_to_net_pay" id="hidden_apply_to_net_pay" value="0">
                    <input type="hidden" name="apply_to_monthly_basic_salary" id="hidden_apply_to_monthly_basic_salary" value="0">
                </div>

                <div>
                    <label for="benefit_eligibility" class="block text-sm font-medium text-gray-700 mb-2">Apply To</label>
                    <select name="benefit_eligibility" id="benefit_eligibility" required
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        <option value="both" {{ old('benefit_eligibility', 'both') == 'both' ? 'selected' : '' }}>
                            Both (With Benefits & Without Benefits)
                        </option>
                        <option value="with_benefits" {{ old('benefit_eligibility') == 'with_benefits' ? 'selected' : '' }}>
                            Only Employees With Benefits
                        </option>
                        <option value="without_benefits" {{ old('benefit_eligibility') == 'without_benefits' ? 'selected' : '' }}>
                            Only Employees Without Benefits
                        </option>
                    </select>
                    @error('benefit_eligibility')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Choose which employees this deduction/tax setting applies to based on their benefit status.</p>
                </div>
            </div>

            <!-- View Tax Table Button (shown for table types) -->
            <div class="mt-6" id="view_table_section" style="display: none;">
                <button type="button" id="view_tax_table_btn" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                    View Tax Table Guide
                </button>
            </div>

            <!-- Share with Employer and Active checkboxes -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div>
                    <div class="flex items-center h-full">
                        <input type="hidden" name="share_with_employer" value="0">
                        <input type="checkbox" name="share_with_employer" id="share_with_employer" value="1" 
                               {{ old('share_with_employer') ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <label for="share_with_employer" class="ml-2 block text-sm text-gray-700">
                            Share with Employer
                        </label>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Only employee share deducted (for SSS, PhilHealth, Pag-IBIG)</p>
                </div>

                <div>
                    <div class="flex items-center h-full">
                        <input type="checkbox" name="is_active" id="is_active" value="1" 
                               {{ old('is_active', true) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <label for="is_active" class="ml-2 block text-sm text-gray-700">Active</label>
                    </div>
                </div>
            </div>

            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('settings.deductions.index') }}" 
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                    Cancel
                </a>
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    Create Deduction
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tax Table Modal -->
<div id="taxTableModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" style="z-index: 1000;">
    <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900" id="modalTitle">Tax Table Guide</h3>
                <button type="button" class="text-gray-400 hover:text-gray-600" onclick="closeModal()">
                    <span class="sr-only">Close</span>
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div id="modalContent" class="max-h-96 overflow-y-auto">
                <!-- Content will be loaded here -->
            </div>
            <div class="mt-4 flex justify-end">
                <button type="button" class="px-4 py-2 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400" onclick="closeModal()">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('calculation_type').addEventListener('change', function() {
    const value = this.value;
    const percentageField = document.getElementById('percentage_field');
    const fixedAmountField = document.getElementById('fixed_amount_field');
    const viewTableSection = document.getElementById('view_table_section');
    const hiddenTaxTableType = document.getElementById('hidden_tax_table_type');
    
    // Hide all fields first
    percentageField.style.display = 'none';
    fixedAmountField.style.display = 'none';
    viewTableSection.style.display = 'none';
    
    // Show relevant field and set tax table type
    if (value === 'percentage') {
        percentageField.style.display = 'block';
        hiddenTaxTableType.value = '';
    } else if (value === 'fixed_amount') {
        fixedAmountField.style.display = 'block';
        hiddenTaxTableType.value = '';
    } else if (value === 'sss_table') {
        viewTableSection.style.display = 'block';
        hiddenTaxTableType.value = 'sss';
        this.setAttribute('data-actual-type', 'bracket');
    } else if (value === 'philhealth_table') {
        viewTableSection.style.display = 'block';
        hiddenTaxTableType.value = 'philhealth';
        this.setAttribute('data-actual-type', 'bracket');
    } else if (value === 'pagibig_table') {
        viewTableSection.style.display = 'block';
        hiddenTaxTableType.value = 'pagibig';
        this.setAttribute('data-actual-type', 'bracket');
    } else if (value === 'withholding_tax_table') {
        viewTableSection.style.display = 'block';
        hiddenTaxTableType.value = 'withholding_tax';
        this.setAttribute('data-actual-type', 'bracket');
    }
});

// View Tax Table button
document.getElementById('view_tax_table_btn').addEventListener('click', function() {
    const calculationType = document.getElementById('calculation_type').value;
    let taxTableType = '';
    
    // Determine tax table type from calculation type
    if (calculationType === 'sss_table') taxTableType = 'sss';
    else if (calculationType === 'philhealth_table') taxTableType = 'philhealth';
    else if (calculationType === 'pagibig_table') taxTableType = 'pagibig';
    else if (calculationType === 'withholding_tax_table') taxTableType = 'withholding_tax';
    
    if (!taxTableType) {
        alert('Please select a tax table calculation type first.');
        return;
    }
    showTaxTableModal(taxTableType);
});

function showTaxTableModal(tableType) {
    const modal = document.getElementById('taxTableModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalContent = document.getElementById('modalContent');
    
    let title = '';
    let content = '';
    
    switch(tableType) {
        case 'sss':
            title = 'SSS Contribution Table 2025';
            // Show loading message
            content = `
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-600">Loading SSS tax table...</p>
                </div>
            `;
            
            // Set initial content while loading
            modalTitle.textContent = title;
            modalContent.innerHTML = content;
            modal.classList.remove('hidden');
            
            // Fetch data from API
            fetch('{{ route("settings.deductions.sss.tax-table") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let tableRows = '';
                        data.data.forEach(item => {
                            let salaryRange = '';
                            if (item.range_end === null || item.range_end >= 999999) {
                                salaryRange = `₱${item.range_start.toLocaleString('en-PH', {minimumFractionDigits: 2})} - Above`;
                            } else {
                                salaryRange = `₱${item.range_start.toLocaleString('en-PH', {minimumFractionDigits: 2})} - ₱${item.range_end.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                            }
                            
                            tableRows += `
                                <tr>
                                    <td class="px-2 py-1 text-xs">${salaryRange}</td>
                                    <td class="px-2 py-1 text-xs">₱${item.employee_share.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    <td class="px-2 py-1 text-xs">₱${item.employer_share.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                    <td class="px-2 py-1 text-xs">₱${item.total_contribution.toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                </tr>
                            `;
                        });
                        
                        const dynamicContent = `
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Salary Range</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">EE Share</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ER Share</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${tableRows}
                                    </tbody>
                                </table>
                                <div class="mt-4 p-3 bg-blue-50 rounded-md">
                                    <p class="text-sm text-blue-800"><strong>Note:</strong> EE = Employee, ER = Employer</p>
                                </div>
                            </div>
                        `;
                        
                        modalContent.innerHTML = dynamicContent;
                    } else {
                        modalContent.innerHTML = `
                            <div class="text-center py-8">
                                <p class="text-red-600">Error loading SSS tax table data.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching SSS tax table:', error);
                    modalContent.innerHTML = `
                        <div class="text-center py-8">
                            <p class="text-red-600">Error loading SSS tax table data.</p>
                        </div>
                    `;
                });
            return; // Early return to avoid setting content again
            break;
            
        case 'philhealth':
            title = 'PhilHealth Contribution Table 2024-2025';
            // Show loading message
            content = `
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-600">Loading PhilHealth tax table...</p>
                </div>
            `;
            
            // Set initial content while loading
            modalTitle.textContent = title;
            modalContent.innerHTML = content;
            modal.classList.remove('hidden');
            
            // Fetch data from API
            fetch('{{ route("settings.deductions.philhealth.tax-table") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let tableRows = '';
                        data.data.forEach(item => {
                            let salaryRange = '';
                            let rangeStart = parseFloat(item.range_start);
                            let rangeEnd = item.range_end ? parseFloat(item.range_end) : null;
                            
                            if (rangeStart === 0 && rangeEnd < 10000) {
                                salaryRange = `Below ₱${rangeEnd.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                            } else if (rangeStart === rangeEnd) {
                                salaryRange = `₱${rangeStart.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                            } else if (rangeEnd === null || rangeEnd >= 999999) {
                                salaryRange = `₱${rangeStart.toLocaleString('en-PH', {minimumFractionDigits: 2})} and above`;
                            } else {
                                salaryRange = `₱${rangeStart.toLocaleString('en-PH', {minimumFractionDigits: 2})} - ₱${rangeEnd.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                            }
                            
                            let eeShare = parseFloat(item.employee_share).toFixed(1) + '%';
                            let erShare = parseFloat(item.employer_share).toFixed(1) + '%';
                            let total = parseFloat(item.total_contribution).toFixed(1) + '%';
                            
                            let monthlyPremium = '';
                            let minContribution = parseFloat(item.min_contribution);
                            let maxContribution = parseFloat(item.max_contribution);
                            
                            if (minContribution === maxContribution) {
                                monthlyPremium = `₱${minContribution.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                            } else {
                                monthlyPremium = `₱${minContribution.toLocaleString('en-PH', {minimumFractionDigits: 2})} - ₱${maxContribution.toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                            }
                            
                            tableRows += `
                                <tr>
                                    <td class="px-3 py-2">${salaryRange}</td>
                                    <td class="px-3 py-2">${eeShare}</td>
                                    <td class="px-3 py-2">${erShare}</td>
                                    <td class="px-3 py-2">${total}</td>
                                    <td class="px-3 py-2">${monthlyPremium}</td>
                                </tr>
                            `;
                        });
                        
                        const dynamicContent = `
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Salary Range</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">EE Share</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ER Share</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Monthly Premium</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${tableRows}
                                    </tbody>
                                </table>
                                <div class="mt-4 p-3 bg-blue-50 rounded-md">
                                    <p class="text-sm text-blue-800"><strong>Note:</strong> Based on Monthly Basic Salary. EE = Employee, ER = Employer</p>
                                </div>
                            </div>
                        `;
                        
                        modalContent.innerHTML = dynamicContent;
                    } else {
                        modalContent.innerHTML = `
                            <div class="text-center py-8">
                                <p class="text-red-600">Error loading PhilHealth tax table data.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching PhilHealth tax table:', error);
                    modalContent.innerHTML = `
                        <div class="text-center py-8">
                            <p class="text-red-600">Error loading PhilHealth tax table data.</p>
                        </div>
                    `;
                });
            return; // Early return to avoid setting content again
            break;
            
        case 'pagibig':
            title = 'Pag-IBIG Contribution Table 2025';
            // Show loading message
            content = `
                <div class="text-center py-8">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-2 text-gray-600">Loading Pag-IBIG tax table...</p>
                </div>
            `;
            
            // Set initial content while loading
            modalTitle.textContent = title;
            modalContent.innerHTML = content;
            modal.classList.remove('hidden');
            
            // Fetch data from API
            fetch('{{ route("settings.deductions.pagibig.tax-table") }}')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let tableRows = '';
                        data.data.forEach(item => {
                            let salaryRange = '';
                            if (item.range_end === null || item.range_end >= 999999) {
                                salaryRange = `₱${parseFloat(item.range_start).toLocaleString('en-PH', {minimumFractionDigits: 2})} - Above`;
                            } else {
                                salaryRange = `₱${parseFloat(item.range_start).toLocaleString('en-PH', {minimumFractionDigits: 2})} - ₱${parseFloat(item.range_end).toLocaleString('en-PH', {minimumFractionDigits: 2})}`;
                            }
                            
                            tableRows += `
                                <tr>
                                    <td class="px-3 py-2">${salaryRange}</td>
                                    <td class="px-3 py-2">${item.employee_share}</td>
                                    <td class="px-3 py-2">${item.employer_share}</td>
                                    <td class="px-3 py-2">${item.total_contribution}</td>
                                    <td class="px-3 py-2">₱${parseFloat(item.max_contribution).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
                                </tr>
                            `;
                        });
                        
                        const dynamicContent = `
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Salary Range</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">EE Share</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">ER Share</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Max Contribution</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        ${tableRows}
                                    </tbody>
                                </table>
                                <div class="mt-4 p-3 bg-blue-50 rounded-md">
                                    <p class="text-sm text-blue-800"><strong>Note:</strong> Based on Monthly Basic Salary. Maximum contribution is ₱200.00 for both employee and employer</p>
                                </div>
                            </div>
                        `;
                        
                        modalContent.innerHTML = dynamicContent;
                    } else {
                        modalContent.innerHTML = `
                            <div class="text-center py-8">
                                <p class="text-red-600">Error loading Pag-IBIG tax table data.</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error fetching Pag-IBIG tax table:', error);
                    modalContent.innerHTML = `
                        <div class="text-center py-8">
                            <p class="text-red-600">Error loading Pag-IBIG tax table data.</p>
                        </div>
                    `;
                });
            return; // Early return to avoid setting content again
            break;
            
        case 'withholding_tax':
            title = 'BIR Withholding Tax Table (Semi-Monthly) 2023 onwards';
            content = `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Compensation Range</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tax Rate</th>
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Formula</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr><td class="px-3 py-2">₱10,417 and below</td><td class="px-3 py-2">0%</td><td class="px-3 py-2">₱0.00</td></tr>
                            <tr><td class="px-3 py-2">₱10,417 - ₱16,666</td><td class="px-3 py-2">15%</td><td class="px-3 py-2">0.00 +15% over ₱10,417</td></tr>
                            <tr><td class="px-3 py-2">₱16,667 - ₱33,332</td><td class="px-3 py-2">20%</td><td class="px-3 py-2">₱937.50 +20% over ₱16,667</td></tr>
                            <tr><td class="px-3 py-2">₱33,333 - ₱83,332</td><td class="px-3 py-2">25%</td><td class="px-3 py-2">₱4,270.70 +25% over ₱33,333</td></tr>
                            <tr><td class="px-3 py-2">₱83,333 - ₱333,332</td><td class="px-3 py-2">30%</td><td class="px-3 py-2">₱16,770.70 +30% over ₱83,333</td></tr>
                            <tr><td class="px-3 py-2">₱333,333 and above</td><td class="px-3 py-2">35%</td><td class="px-3 py-2">₱91,770.70 +35% over ₱333,333</td></tr>
                        </tbody>
                    </table>
                    <div class="mt-4 p-3 bg-yellow-50 rounded-md">
                        <p class="text-sm text-yellow-800"><strong>Note:</strong> This applies to taxable income (gross pay minus SSS, PhilHealth, and Pag-IBIG contributions)</p>
                    </div>
                </div>
            `;
            break;
    }
    
    modalTitle.textContent = title;
    modalContent.innerHTML = content;
    modal.classList.remove('hidden');
}

function closeModal() {
    document.getElementById('taxTableModal').classList.add('hidden');
}

// Close modal when clicking outside
document.getElementById('taxTableModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Intelligent filtering for calculation type based on deduction name
function filterCalculationTypeOptions() {
    const nameField = document.getElementById('name');
    const calculationSelect = document.getElementById('calculation_type');
    const currentName = nameField.value.toLowerCase();
    
    // Get all options
    const options = calculationSelect.querySelectorAll('option');
    
    // Define filtering rules based on deduction name
    let allowedTypes = [];
    
    if (currentName.includes('sss')) {
        allowedTypes = ['', 'percentage', 'fixed_amount', 'sss_table'];
    } else if (currentName.includes('philhealth') || currentName.includes('phil health')) {
        allowedTypes = ['', 'percentage', 'fixed_amount', 'philhealth_table'];
    } else if (currentName.includes('pagibig') || currentName.includes('pag-ibig') || currentName.includes('hdmf')) {
        allowedTypes = ['', 'percentage', 'fixed_amount', 'pagibig_table'];
    } else if (currentName.includes('withholding') || currentName.includes('income tax') || currentName.includes('bir')) {
        allowedTypes = ['', 'percentage', 'fixed_amount', 'withholding_tax_table'];
    } else {
        // For custom/other deductions, show all except tax tables
        allowedTypes = ['', 'percentage', 'fixed_amount', 'bracket'];
    }
    
    // Show/hide options based on allowed types
    options.forEach(option => {
        if (allowedTypes.includes(option.value)) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
    
    // If currently selected option is not allowed, reset selection
    const currentValue = calculationSelect.value;
    if (!allowedTypes.includes(currentValue)) {
        calculationSelect.value = '';
        calculationSelect.dispatchEvent(new Event('change'));
    }
}

// Add event listener to name field for filtering
document.getElementById('name').addEventListener('input', filterCalculationTypeOptions);
document.getElementById('name').addEventListener('change', filterCalculationTypeOptions);

// Handle frequency change to show/hide distribution method
document.getElementById('frequency').addEventListener('change', function() {
    const frequency = this.value;
    const distributionField = document.getElementById('distribution_method_field');
    
    if (frequency === 'per_payroll') {
        distributionField.style.display = 'none';
    } else {
        distributionField.style.display = 'block';
    }
});

// Setup pay basis select dropdown handler
document.addEventListener('DOMContentLoaded', function() {
    const payBasisSelect = document.getElementById('pay_basis');
    payBasisSelect.addEventListener('change', function() {
        updatePayBasisHiddenFields(this.value);
    });
    
    // Initialize hidden fields based on current selection
    if (payBasisSelect.value) {
        updatePayBasisHiddenFields(payBasisSelect.value);
    }
    
    // Trigger frequency change event to hide/show distribution method on page load
    const frequencySelect = document.getElementById('frequency');
    if (frequencySelect.value) {
        frequencySelect.dispatchEvent(new Event('change'));
    }
    
    // Setup form submission handler
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        // Debug: Log the values being submitted
        const calculationType = document.getElementById('calculation_type');
        console.log('Submitting calculation_type:', calculationType.value);
        console.log('Tax table type:', document.getElementById('hidden_tax_table_type').value);
    });
});

function updatePayBasisHiddenFields(selectedValue) {
    // Reset all hidden fields
    document.getElementById('hidden_apply_to_basic_pay').value = '0';
    document.getElementById('hidden_apply_to_gross_pay').value = '0';
    document.getElementById('hidden_apply_to_taxable_income').value = '0';
    document.getElementById('hidden_apply_to_net_pay').value = '0';
    document.getElementById('hidden_apply_to_monthly_basic_salary').value = '0';
    
    // Set the selected one to 1
    switch(selectedValue) {
        case 'gross_pay':
            document.getElementById('hidden_apply_to_gross_pay').value = '1';
            break;
        case 'taxable_income':
            document.getElementById('hidden_apply_to_taxable_income').value = '1';
            break;
        case 'monthly_basic_salary':
            document.getElementById('hidden_apply_to_monthly_basic_salary').value = '1';
            break;
    }
}

// Trigger on page load
document.getElementById('calculation_type').dispatchEvent(new Event('change'));
</script>
    </div>
</div>
</x-app-layout>
