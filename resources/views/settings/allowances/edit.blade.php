<x-app-layout>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Edit Allowance/Bonus: {{ $allowance->name }}</h1>
            <a href="{{ route('settings.allowances.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                Back to List
            </a>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
        <form method="POST" action="{{ route('settings.allowances.update', $allowance) }}" class="p-6 space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name', $allowance->name) }}" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                    @error('name')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Type</label>
                    <select name="type" id="type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Type</option>
                        <option value="allowance" {{ old('type', $allowance->type) == 'allowance' ? 'selected' : '' }}>Allowance</option>
                        <option value="bonus" {{ old('type', $allowance->type) == 'bonus' ? 'selected' : '' }}>Bonus</option>
                        <option value="incentives" {{ old('type', $allowance->type) == 'incentives' ? 'selected' : '' }}>Incentives</option>
                    </select>
                    @error('type')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="calculation_type" class="block text-sm font-medium text-gray-700">Calculation Type</label>
                    <select name="calculation_type" id="calculation_type" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Calculation Type</option>
                        <option value="percentage" {{ old('calculation_type', $allowance->calculation_type) == 'percentage' ? 'selected' : '' }}>Percentage</option>
                        <option value="fixed_amount" {{ old('calculation_type', $allowance->calculation_type) == 'fixed_amount' ? 'selected' : '' }}>Fixed Amount</option>
                        <option value="daily_rate_multiplier" {{ old('calculation_type', $allowance->calculation_type) == 'daily_rate_multiplier' ? 'selected' : '' }}>Daily Rate Multiplier</option>
                        <option value="automatic" 
                                id="automatic_option" 
                                style="{{ (stripos($allowance->name, '13th') !== false) ? '' : 'display: none;' }}"
                                {{ old('calculation_type', $allowance->calculation_type) == 'automatic' ? 'selected' : '' }}>
                            Automatic (13th Month Pay)
                        </option>
                    </select>
                    @error('calculation_type')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="frequency" class="block text-sm font-medium text-gray-700">Frequency</label>
                    <select name="frequency" id="frequency" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Select Frequency</option>
                        <option value="per_payroll" {{ old('frequency', $allowance->frequency) == 'per_payroll' ? 'selected' : '' }}>Per Payroll</option>
                        <option value="monthly" {{ old('frequency', $allowance->frequency) == 'monthly' ? 'selected' : '' }}>Monthly</option>
                        <option value="quarterly" {{ old('frequency', $allowance->frequency) == 'quarterly' ? 'selected' : '' }}>Quarterly</option>
                        <option value="annually" {{ old('frequency', $allowance->frequency) == 'annually' ? 'selected' : '' }}>Annually</option>
                    </select>
                    @error('frequency')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="distribution_method_field">
                    <label for="distribution_method" class="block text-sm font-medium text-gray-700">Distribution Method</label>
                    <select name="distribution_method" id="distribution_method" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="first_payroll" {{ old('distribution_method', $allowance->distribution_method) == 'first_payroll' ? 'selected' : '' }}>First Payroll Only</option>
                        <option value="last_payroll" {{ old('distribution_method', $allowance->distribution_method) == 'last_payroll' ? 'selected' : '' }}>Last Payroll Only</option>
                        <option value="equally_distributed" {{ old('distribution_method', $allowance->distribution_method) == 'equally_distributed' ? 'selected' : '' }}>Equally Distributed</option>
                    </select>
                    @error('distribution_method')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-500">Choose how the amount is distributed across payrolls within the frequency period.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div id="rate_percentage_field" style="display: none;">
                    <label for="rate_percentage" class="block text-sm font-medium text-gray-700">Rate Percentage (%)</label>
                    <input type="number" name="rate_percentage" id="rate_percentage" step="0.01" min="0" max="100" value="{{ old('rate_percentage', $allowance->rate_percentage) }}" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('rate_percentage')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="fixed_amount_field" style="display: none;">
                    <label for="fixed_amount" class="block text-sm font-medium text-gray-700">Fixed Amount (₱)</label>
                    <input type="number" name="fixed_amount" id="fixed_amount" step="0.01" min="0" value="{{ old('fixed_amount', $allowance->fixed_amount) }}" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('fixed_amount')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div id="multiplier_field" style="display: none;">
                    <label for="multiplier" class="block text-sm font-medium text-gray-700">Multiplier</label>
                    <input type="number" name="multiplier" id="multiplier" step="0.01" min="0" value="{{ old('multiplier', $allowance->multiplier) }}" 
                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    @error('multiplier')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Perfect Attendance Option -->
            <div class="mt-6" id="perfect-attendance-section">
                <div class="flex items-center">
                    <input type="hidden" name="requires_perfect_attendance" value="0">
                    <input type="checkbox" name="requires_perfect_attendance" id="requires_perfect_attendance" 
                           value="1" {{ old('requires_perfect_attendance', $allowance->requires_perfect_attendance) ? 'checked' : '' }}
                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="requires_perfect_attendance" class="ml-2 block text-sm text-gray-900">
                        Apply to employees with perfect attendance only
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-500">When checked, this allowance/bonus will only be given to employees who have perfect attendance for the pay period.</p>
                @error('requires_perfect_attendance')
                    <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-4">
                <h3 class="text-lg font-medium text-gray-900">Application Settings</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex items-center">
                        <input type="hidden" name="is_taxable" value="0">
                        <input type="checkbox" name="is_taxable" id="is_taxable" value="1" {{ old('is_taxable', $allowance->is_taxable) ? 'checked' : '' }} 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_taxable" class="ml-2 block text-sm text-gray-900">Taxable</label>
                    </div>

                    <div class="flex items-center">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', $allowance->is_active) ? 'checked' : '' }} 
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-2 block text-sm text-gray-900">Active</label>
                    </div>
                </div>
                
                <div class="mt-4 text-sm text-gray-600">
                    <p><strong>Taxable:</strong> When checked, this allowance/bonus will be included in taxable income calculations for deductions.</p>
                </div>
            </div>

            <div class="mt-6">
                <label for="benefit_eligibility" class="block text-sm font-medium text-gray-700 mb-2">Apply To</label>
                <select name="benefit_eligibility" id="benefit_eligibility" required
                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    <option value="both" {{ old('benefit_eligibility', $allowance->benefit_eligibility ?? 'both') == 'both' ? 'selected' : '' }}>
                        Both (With Benefits & Without Benefits)
                    </option>
                    <option value="with_benefits" {{ old('benefit_eligibility', $allowance->benefit_eligibility ?? 'both') == 'with_benefits' ? 'selected' : '' }}>
                        Only Employees With Benefits
                    </option>
                    <option value="without_benefits" {{ old('benefit_eligibility', $allowance->benefit_eligibility ?? 'both') == 'without_benefits' ? 'selected' : '' }}>
                        Only Employees Without Benefits
                    </option>
                </select>
                @error('benefit_eligibility')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
                <p class="mt-1 text-xs text-gray-500">Choose which employees this allowance/bonus setting applies to based on their benefit status.</p>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('settings.allowances.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                    Cancel
                </a>
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    Update Allowance/Bonus
                </button>
            </div>
        </form>
    </div>
</div>

<script>

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

// Handle name field changes to show/hide automatic calculation type for 13th month pay
document.getElementById('name').addEventListener('input', function() {
    const nameValue = this.value.toLowerCase();
    const automaticOption = document.getElementById('automatic_option');
    const calculationType = document.getElementById('calculation_type');
    
    if (nameValue.includes('13th') || nameValue.includes('thirteenth')) {
        automaticOption.style.display = 'block';
    } else {
        automaticOption.style.display = 'none';
        // If automatic is currently selected, reset to empty
        if (calculationType.value === 'automatic') {
            calculationType.value = '';
            calculationType.dispatchEvent(new Event('change'));
        }
    }
});

// Update calculation type handling to include automatic
document.getElementById('calculation_type').addEventListener('change', function() {
    const calculationType = this.value;
    
    // Hide all calculation fields
    document.getElementById('rate_percentage_field').style.display = 'none';
    document.getElementById('fixed_amount_field').style.display = 'none';
    document.getElementById('multiplier_field').style.display = 'none';
    
    // Show the relevant field
    if (calculationType === 'percentage') {
        document.getElementById('rate_percentage_field').style.display = 'block';
    } else if (calculationType === 'fixed_amount') {
        document.getElementById('fixed_amount_field').style.display = 'block';
    } else if (calculationType === 'daily_rate_multiplier') {
        document.getElementById('multiplier_field').style.display = 'block';
    } else if (calculationType === 'automatic') {
        // For automatic calculation, no additional fields are needed
        // The calculation is done automatically based on payroll data
    }
});

// Trigger change events on page load to show the correct fields
document.addEventListener('DOMContentLoaded', function() {
    // Check name field on page load for 13th month
    const nameField = document.getElementById('name');
    if (nameField) {
        nameField.dispatchEvent(new Event('input'));
    }
    
    const calculationType = document.getElementById('calculation_type').value;
    if (calculationType) {
        document.getElementById('calculation_type').dispatchEvent(new Event('change'));
    }
    
    const frequency = document.getElementById('frequency').value;
    if (frequency) {
        document.getElementById('frequency').dispatchEvent(new Event('change'));
    }
});
</script>
    </div>
</div>
</x-app-layout>
