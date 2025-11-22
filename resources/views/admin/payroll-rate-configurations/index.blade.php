<x-app-layout>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Rate Multiplier Configurations</h1>
                <div class="text-sm text-gray-600 mt-1">
                    Manage overtime and premium rate multipliers for different work types
                </div>
            </div>
            <a href="{{ route('payroll-rate-configurations.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                Add New Configuration
            </a>
        </div>

    @if(session('success'))
        <div id="successMessage" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div id="errorMessage" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    @php
        $groupedConfigurations = $configurations->groupBy(function($config) {
            $typeName = strtolower($config->type_name);
            
            // Fixed grouping based on type_name for consistent organization
            if ($typeName === 'regular_workday') return 'regular';
            if (str_contains($typeName, 'rest_day')) return 'rest_day';
            if (str_contains($typeName, 'holiday')) return 'holiday';
            if (str_contains($typeName, 'suspension')) return 'suspension';
            
            return 'other';
        });
        
        // Define group display names
        $groupNames = [
            'regular' => 'Regular',
            'rest_day' => 'Rest Day', 
            'holiday' => 'Holiday',
            'suspension' => 'Suspension',
            'other' => 'Other'
        ];
    @endphp

    <!-- Rate Type Tabs -->
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button onclick="showRateType('regular')" 
                        class="rate-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="regular">
                    Regular
                </button>
                <button onclick="showRateType('rest_day')" 
                        class="rate-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="rest_day">
                    Rest Day
                </button>
                <button onclick="showRateType('holiday')" 
                        class="rate-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="holiday">
                    Holiday
                </button>
                <button onclick="showRateType('suspension')" 
                        class="rate-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="suspension">
                    Suspension
                </button>
                @if($groupedConfigurations->has('other'))
                <button onclick="showRateType('other')" 
                        class="rate-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="other">
                    Other
                </button>
                @endif
            </nav>
        </div>
    </div>

    @forelse($groupedConfigurations as $type => $typeConfigurations)
        <div id="{{ $type }}-rates" class="rate-type-section hidden">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="bg-gray-50 px-6 py-3">
                    <h3 class="text-lg font-medium text-gray-900">{{ $groupNames[$type] ?? ucfirst(str_replace('_', ' ', $type)) }} Rates</h3>
                </div>
            <table class="min-w-full table-fixed divide-y divide-gray-200">
                <colgroup>
                    <col style="width: 16%;"> <!-- Type -->
                    <col style="width: 20%;"> <!-- Description -->
                    <col style="width: 15%;"> <!-- Rate 1 -->
                    <col style="width: 15%;"> <!-- Rate 2 -->
                    <col style="width: 22%;"> <!-- Formula -->
                    <col style="width: 8%;">  <!-- Status -->
                </colgroup>
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            @if($type === 'regular')
                                Regular Rate
                            @else
                                Premium Rate
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            @if($type === 'regular')
                                OT Rate
                            @else
                                Premium OT Rate
                            @endif
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Formula (OT)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($typeConfigurations as $configuration)
                        <tr class="hover:bg-gray-50 cursor-pointer {{ !$configuration->is_active ? 'opacity-50 bg-gray-50' : '' }}" 
                            data-context-menu
                            oncontextmenu="showRateConfigContextMenu(event, {{ $configuration->id }}, {{ json_encode($configuration->display_name) }}, {{ json_encode($type) }}, {{ $configuration->is_active ? 'true' : 'false' }}, {{ $configuration->is_system ? 'true' : 'false' }})">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium {{ !$configuration->is_active ? 'text-gray-400' : 'text-gray-900' }}">{{ $configuration->display_name }}</div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm {{ !$configuration->is_active ? 'text-gray-400' : 'text-gray-500' }}">
                                    @php
                                        $typeName = strtolower($configuration->type_name);
                                    @endphp
                                    @if($typeName === 'regular_workday')
                                        Standard working day rates
                                    @elseif(str_contains($typeName, 'rest_day'))
                                        Rest day premium rates
                                    @elseif(str_contains($typeName, 'regular_holiday'))
                                        Regular holiday premium rates
                                    @elseif(str_contains($typeName, 'special'))
                                        Special holiday premium rates
                                    @elseif(str_contains($typeName, 'suspension'))
                                        Premium rate configuration
                                    @else
                                        Premium rate configuration
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm {{ !$configuration->is_active ? 'text-gray-400' : 'text-gray-900' }}">
                                    <span class="text-indigo-600 font-medium">
                                        @if($type === 'regular')
                                            {{ intval($configuration->regular_rate_multiplier * 100) }}%
                                        @else
                                            +{{ intval($configuration->regular_rate_multiplier * 100) }}%
                                        @endif
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm {{ !$configuration->is_active ? 'text-gray-400' : 'text-gray-900' }}">
                                    <span class="text-indigo-600 font-medium">+{{ intval($configuration->overtime_rate_multiplier * 100) }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm {{ !$configuration->is_active ? 'text-gray-400' : 'text-gray-700' }}">
                                    Hourly Rate × {{ number_format($configuration->overtime_rate_multiplier, 2) }} × OT Hours
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $configuration->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $configuration->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @empty
        <div class="bg-white rounded-lg shadow p-8 text-center">
            <p class="text-gray-500">No rate configurations available.</p>
            <div class="mt-4">
                <form action="{{ route('payroll-rate-configurations.initialize-defaults') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        Initialize Default Configurations
                    </button>
                </form>
            </div>
        </div>
    @endforelse
</div>

<script src="{{ asset('js/settings-context-menu.js') }}"></script>
<script>
// Auto-hide success/error messages after 3 seconds
document.addEventListener('DOMContentLoaded', function() {
    const successMessage = document.getElementById('successMessage');
    const errorMessage = document.getElementById('errorMessage');
    
    if (successMessage) {
        setTimeout(() => {
            successMessage.style.transition = 'opacity 0.5s ease-out';
            successMessage.style.opacity = '0';
            setTimeout(() => successMessage.remove(), 500);
        }, 3000);
    }
    
    if (errorMessage) {
        setTimeout(() => {
            errorMessage.style.transition = 'opacity 0.5s ease-out';
            errorMessage.style.opacity = '0';
            setTimeout(() => errorMessage.remove(), 500);
        }, 3000);
    }
    
    // Initialize tabs on page load
    const urlHash = window.location.hash.substring(1); // Remove # from hash
    const savedTab = localStorage.getItem('rateMultiplierActiveTab');
    const initialTab = urlHash || savedTab || 'regular'; // Default to regular
    showRateType(initialTab);
});

// Tab functionality for rate types
function showRateType(type) {
    // Hide all sections
    const sections = document.querySelectorAll('.rate-type-section');
    sections.forEach(section => {
        section.classList.add('hidden');
    });
    
    // Show selected section
    const selectedSection = document.getElementById(type + '-rates');
    if (selectedSection) {
        selectedSection.classList.remove('hidden');
        
        // Save the active tab to localStorage and URL hash
        localStorage.setItem('rateMultiplierActiveTab', type);
        window.location.hash = type;
        
        // Update tab styles
        const tabs = document.querySelectorAll('.rate-tab');
        tabs.forEach(tab => {
            tab.classList.remove('border-blue-500', 'text-blue-600');
            tab.classList.add('border-transparent', 'text-gray-500');
        });
        
        // Highlight active tab
        const activeTab = document.querySelector(`[data-type="${type}"]`);
        if (activeTab) {
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }
    }
}

function showRateConfigContextMenu(event, configId, configName, configType, isActive, isSystem) {
    const config = {
        id: configId,
        name: configName,
        subtitle: configType.replace('_', ' ').toUpperCase(),
        viewText: 'View Configuration',
        editText: 'Edit Configuration',
        deleteText: 'Delete Configuration',
        viewUrl: `{{ url('settings/rate-multiplier') }}/${configId}`,
        editUrl: `{{ url('settings/rate-multiplier') }}/${configId}/edit`,
        toggleUrl: `{{ url('settings/rate-multiplier') }}/${configId}/toggle`,
        deleteUrl: `{{ url('settings/rate-multiplier') }}/${configId}`,
        isActive: isActive === 'true' || isActive === true,
        canDelete: !(isSystem === 'true' || isSystem === true),
        deleteConfirmMessage: 'Are you sure you want to delete this rate configuration?'
    };
    
    showSettingsContextMenu(event, config);
}
</script>
    </div>
</div>
</x-app-layout>