<x-app-layout>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Pay Schedule Settings</h1>
                <div class="text-sm text-gray-600 mt-1">
                    Manage multiple pay schedules for different employee groups
                </div>
            </div>
            {{-- <div class="flex space-x-3">
                <button onclick="showAddScheduleModal()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                    </svg>
                    Add Schedule
                </button>
            </div> --}}
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

    <!-- Schedule Type Tabs -->
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button onclick="showScheduleType('daily')" 
                        class="schedule-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="daily">
                    Daily Schedules
                </button>
                <button onclick="showScheduleType('weekly')" 
                        class="schedule-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="weekly">
                    Weekly Schedules
                </button>
                <button onclick="showScheduleType('semi_monthly')" 
                        class="schedule-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="semi_monthly">
                    Semi-Monthly Schedules
                </button>
                <button onclick="showScheduleType('monthly')" 
                        class="schedule-tab border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap py-2 px-1 border-b-2 font-medium text-sm"
                        data-type="monthly">
                    Monthly Schedules
                </button>
            </nav>
        </div>
    </div>

    <!-- Daily Schedules -->
    <div id="daily-schedules" class="schedule-type-section hidden">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Daily Pay Schedules</h3>
                <button onclick="showAddScheduleModal('daily')" 
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    + Add Daily Schedule
                </button>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pay Day</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($schedules->where('type', 'daily') as $schedule)
                        <tr class="hover:bg-gray-50 cursor-pointer" 
                            data-context-menu
                            oncontextmenu="showPayScheduleContextMenu(event, {{ $schedule->id }}, {{ json_encode($schedule->name) }}, {{ json_encode($schedule->description ?? 'N/A') }}, {{ $schedule->is_active ? 'true' : 'false' }}, {{ $schedule->is_default ?? 'false' ? 'true' : 'false' }})">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $schedule->name }}</div>
                                @if($schedule->is_default)
                                    <div class="text-xs text-blue-600 font-medium">Default</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    @php $periods = $schedule->cutoff_periods ?? []; @endphp
                                    @if(isset($periods[0]['pay_day']))
                                        {{ ucfirst($periods[0]['pay_day']) }}
                                    @else
                                        Not configured
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $schedule->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-12 text-center text-gray-500">
                                <div class="text-sm">No daily schedules found.</div>
                                <button onclick="showAddScheduleModal('daily')" 
                                        class="mt-2 text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    Create your first daily schedule
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Weekly Schedules -->
    <div id="weekly-schedules" class="schedule-type-section hidden">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Weekly Pay Schedules</h3>
                <button onclick="showAddScheduleModal('weekly')" 
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    + Add Weekly Schedule
                </button>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cut-off Period</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pay Day</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($schedules->where('type', 'weekly') as $schedule)
                        <tr class="hover:bg-gray-50 cursor-pointer" 
                            data-context-menu
                            oncontextmenu="showPayScheduleContextMenu(event, {{ $schedule->id }}, {{ json_encode($schedule->name) }}, {{ json_encode($schedule->description ?? 'N/A') }}, {{ $schedule->is_active ? 'true' : 'false' }}, {{ $schedule->is_default ?? 'false' ? 'true' : 'false' }})">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $schedule->name }}</div>
                                @if($schedule->is_default)
                                    <div class="text-xs text-blue-600 font-medium">Default</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    @php $periods = $schedule->cutoff_periods ?? []; @endphp
                                    @if(isset($periods[0]))
                                        {{ ucfirst($periods[0]['start_day'] ?? '') }} - {{ ucfirst($periods[0]['end_day'] ?? '') }}
                                    @else
                                        Not configured
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    @if(isset($periods[0]['pay_day']))
                                        {{ ucfirst($periods[0]['pay_day']) }}
                                    @else
                                        Not configured
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $schedule->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                <div class="text-sm">No weekly schedules found.</div>
                                <button onclick="showAddScheduleModal('weekly')" 
                                        class="mt-2 text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    Create your first weekly schedule
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Semi-Monthly Schedules -->
    <div id="semi_monthly-schedules" class="schedule-type-section">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Semi-Monthly Pay Schedules</h3>
                <button onclick="showAddScheduleModal('semi_monthly')" 
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    + Add Semi-Monthly Schedule
                </button>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">1st Cutoff</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">2nd Cutoff</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">

                    @forelse($schedules->where('type', 'semi_monthly') as $schedule)
                        <tr class="hover:bg-gray-50 cursor-pointer" 
                            data-context-menu
                            oncontextmenu="showPayScheduleContextMenu(event, {{ $schedule->id }}, {{ json_encode($schedule->name) }}, {{ json_encode($schedule->description ?? 'N/A') }}, {{ $schedule->is_active ? 'true' : 'false' }}, {{ $schedule->is_default ?? 'false' ? 'true' : 'false' }})">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $schedule->name }}</div>
                                @if($schedule->is_default)
                                    <div class="text-xs text-blue-600 font-medium">Default</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    @php $periods = $schedule->cutoff_periods ?? []; @endphp
                                    @if(isset($periods[0]))
                                        {{ $periods[0]['start_day'] ?? '' }}-{{ $periods[0]['end_day'] ?? '' }} (Pay: {{ $periods[0]['pay_date'] ?? '' }})
                                    @else
                                        Not configured
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    @if(isset($periods[1]))
                                        {{ $periods[1]['start_day'] ?? '' }}-{{ $periods[1]['end_day'] ?? '' }} (Pay: {{ $periods[1]['pay_date'] ?? '' }})
                                    @else
                                        Not configured
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $schedule->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                <div class="text-sm">No semi-monthly schedules found.</div>
                                <button onclick="showAddScheduleModal('semi_monthly')" 
                                        class="mt-2 text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    Create your first semi-monthly schedule
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Monthly Schedules -->
    <div id="monthly-schedules" class="schedule-type-section hidden">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">Monthly Pay Schedules</h3>
                <button onclick="showAddScheduleModal('monthly')" 
                        class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                    + Add Monthly Schedule
                </button>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cut-off Dates</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pay Day</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($schedules->where('type', 'monthly') as $schedule)
                        <tr class="hover:bg-gray-50 cursor-pointer" 
                            data-context-menu
                            oncontextmenu="showPayScheduleContextMenu(event, {{ $schedule->id }}, {{ json_encode($schedule->name) }}, {{ json_encode($schedule->description ?? 'N/A') }}, {{ $schedule->is_active ? 'true' : 'false' }}, {{ $schedule->is_default ?? 'false' ? 'true' : 'false' }})">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ $schedule->name }}</div>
                                @if($schedule->is_default)
                                    <div class="text-xs text-blue-600 font-medium">Default</div>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    @php $periods = $schedule->cutoff_periods ?? []; @endphp
                                    @if(isset($periods[0]))
                                        {{ $periods[0]['start_day'] ?? '' }}-{{ $periods[0]['end_day'] ?? '' }}
                                    @else
                                        Not configured
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-500">
                                    @if(isset($periods[0]['pay_date']))
                                        {{ $periods[0]['pay_date'] }}
                                    @else
                                        Not configured
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $schedule->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $schedule->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                <div class="text-sm">No monthly schedules found.</div>
                                <button onclick="showAddScheduleModal('monthly')" 
                                        class="mt-2 text-blue-600 hover:text-blue-800 text-sm font-medium">
                                    Create your first monthly schedule
                                </button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
<!-- Add Schedule Modal -->
<div id="addScheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto hidden z-50">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-md">
            <form id="addScheduleForm" action="{{ route('settings.pay-schedules.store') }}" method="POST">
                @csrf
                <div class="bg-white px-4 pt-5 pb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Add New Pay Schedule
                        </h3>
                        <button type="button" onclick="closeAddScheduleModal()" 
                                class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <input type="hidden" name="type" id="scheduleType">
                        
                        <div>
                            <label for="scheduleName" class="block text-sm font-medium text-gray-700">Schedule Name</label>
                            <input type="text" name="name" id="scheduleName" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Enter a descriptive name for this schedule">
                        </div>

                        <!-- Dynamic fields based on schedule type -->
                        <div id="dynamicFields"></div>

                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="isActive" value="1" checked
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="isActive" class="ml-2 block text-sm text-gray-900">
                                Active schedule
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 flex justify-end space-x-3">
                    <button type="button" onclick="closeAddScheduleModal()" 
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        Create Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</div>

<!-- Edit Schedule Modal -->
<div id="editScheduleModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto hidden z-50">
    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center">
        <div class="bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all w-full max-w-md">
            <form method="POST" id="editScheduleForm">
                @csrf
                @method('PUT')
                <div class="bg-white px-4 pt-5 pb-4">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="edit-modal-title">
                            Edit Pay Schedule
                        </h3>
                        <button type="button" onclick="closeEditScheduleModal()" 
                                class="text-gray-400 hover:text-gray-600">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>
                    
                    <div class="space-y-4">
                        <input type="hidden" name="type" id="editScheduleType">
                        
                        <div>
                            <label for="editScheduleName" class="block text-sm font-medium text-gray-700">Schedule Name</label>
                            <input type="text" name="name" id="editScheduleName" required
                                   class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Enter a descriptive name for this schedule">
                        </div>

                        <!-- Dynamic fields based on schedule type -->
                        <div id="editDynamicFields"></div>

                        <div class="flex items-center">
                            <input type="checkbox" name="is_active" id="editIsActive" value="1"
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="editIsActive" class="ml-2 block text-sm text-gray-900">
                                Active schedule
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 flex justify-end space-x-3">
                    <button type="button" onclick="closeEditScheduleModal()" 
                            class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                        Update Schedule
                    </button>
                </div>
        </form>
    </div>
</div>
</div>

<script>
// DEFINE ALL ESSENTIAL FUNCTIONS IMMEDIATELY to prevent ReferenceError
console.log('Defining pay schedule functions...');

function closeContextMenu() {
    // Remove any context menus that might exist
    const menus = document.querySelectorAll('#contextMenu, [id*="contextMenu"], [class*="context"]');
    menus.forEach(menu => menu.remove());
}

window.handleEdit = function(scheduleId) {
    console.log('Edit clicked for schedule:', scheduleId);
    closeContextMenu();
    if (typeof showEditModal === 'function') {
        showEditModal(scheduleId);
    } else {
        console.error('showEditModal function not found');
    }
};

window.handleToggle = function(scheduleId) {
    console.log('Toggle clicked for schedule:', scheduleId);
    closeContextMenu();
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `/settings/pay-schedules/${scheduleId}/toggle`;
    
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = '{{ csrf_token() }}';
    form.appendChild(csrfToken);
    
    const methodField = document.createElement('input');
    methodField.type = 'hidden';
    methodField.name = '_method';
    methodField.value = 'PATCH';
    form.appendChild(methodField);
    
    // Add active tab to preserve state after reload
    const activeTab = localStorage.getItem('payScheduleActiveTab') || 'semi_monthly';
    const tabField = document.createElement('input');
    tabField.type = 'hidden';
    tabField.name = 'active_tab';
    tabField.value = activeTab;
    form.appendChild(tabField);
    
    document.body.appendChild(form);
    form.submit();
};

window.handleDelete = function(scheduleId, name) {
    console.log('Delete clicked for schedule:', scheduleId, 'name:', name);
    if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
        closeContextMenu();
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `/settings/pay-schedules/${scheduleId}`;
        
        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = '{{ csrf_token() }}';
        form.appendChild(csrfToken);
        
        const methodField = document.createElement('input');
        methodField.type = 'hidden';
        methodField.name = '_method';
        methodField.value = 'DELETE';
        form.appendChild(methodField);
        
        // Add active tab to preserve state after reload
        const activeTab = localStorage.getItem('payScheduleActiveTab') || 'semi_monthly';
        const tabField = document.createElement('input');
        tabField.type = 'hidden';
        tabField.name = 'active_tab';
        tabField.value = activeTab;
        form.appendChild(tabField);
        
        document.body.appendChild(form);
        form.submit();
    } else {
        closeContextMenu();
    }
};

window.handleContextMenuAction = function(action, itemId, name) {
    console.log('INTERCEPTED OLD FUNCTION CALL:', action, itemId, name);
    try {
        // Close any existing context menus first
        closeContextMenu();
        
        // Redirect to our new handlers
        if (action === 'edit') {
            console.log('Redirecting to handleEdit');
            window.handleEdit(itemId);
        } else if (action === 'toggle') {
            console.log('Redirecting to handleToggle');
            window.handleToggle(itemId);
        } else if (action === 'delete') {
            console.log('Redirecting to handleDelete');
            window.handleDelete(itemId, name || 'Schedule');
        } else {
            console.log('Unknown action:', action);
        }
    } catch (error) {
        console.error('Error in handleContextMenuAction fallback:', error);
    }
};

// Clear any old context menus immediately when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Remove any existing old context menus
    const oldMenus = document.querySelectorAll('[id*="contextMenu"], [id*="payScheduleContextMenu"]');
    oldMenus.forEach(menu => menu.remove());
});

// Force cache refresh - timestamp: {{ time() }}
console.log('Pay schedules loaded at:', new Date());

// COMPLETELY DISABLE any external context menu systems
window.showSettingsContextMenu = function() { 
    console.log('External showSettingsContextMenu blocked');
    return false; 
};

// Disable any class-based context menu systems
if (window.SettingsContextMenu) {
    window.SettingsContextMenu = null;
}
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

    // Initialize with the correct tab - check URL hash, localStorage, or default to daily
    const urlHash = window.location.hash.substring(1); // Remove # from hash
    const savedTab = localStorage.getItem('payScheduleActiveTab');
    const initialTab = urlHash || savedTab || 'semi_monthly'; // Default to semi_monthly since it has data
    showScheduleType(initialTab);
});

// Tab switching functionality
function showScheduleType(type) {
    // Hide all sections
    document.querySelectorAll('.schedule-type-section').forEach(section => {
        section.classList.add('hidden');
    });
    
    // Show selected section
    const targetSection = document.getElementById(type + '-schedules');
    if (targetSection) {
        targetSection.classList.remove('hidden');
        
        // Save the active tab to localStorage and URL hash
        localStorage.setItem('payScheduleActiveTab', type);
        window.location.hash = type;
        
        // Update tab styling
        document.querySelectorAll('.schedule-tab').forEach(tab => {
            tab.classList.remove('border-blue-500', 'text-blue-600');
            tab.classList.add('border-transparent', 'text-gray-500');
        });
        
        // Activate current tab
        const activeTab = document.querySelector(`[data-type="${type}"]`);
        if (activeTab) {
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }
    }
}

// Modal functionality
function showAddScheduleModal(type = 'daily') {
    const modal = document.getElementById('addScheduleModal');
    const typeInput = document.getElementById('scheduleType');
    const title = document.getElementById('modal-title');
    const nameInput = document.getElementById('scheduleName');
    const dynamicFields = document.getElementById('dynamicFields');
    
    // Set the type
    typeInput.value = type;
    
    // Update modal title
    const typeNames = {
        daily: 'Daily',
        weekly: 'Weekly',
        semi_monthly: 'Semi-Monthly',
        monthly: 'Monthly'
    };
    title.textContent = `Add New ${typeNames[type]} Pay Schedule`;
    
    // Generate dynamic fields based on type
    dynamicFields.innerHTML = generateDynamicFields(type);
    
    // Clear form but preserve type
    document.getElementById('addScheduleForm').reset();
    typeInput.value = type;
    nameInput.focus();
    
    // Show modal
    modal.classList.remove('hidden');
}

function generateDynamicFields(type) {
    let fields = '';
    
    switch(type) {
        case 'daily':
            fields = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pay Day Configuration</label>
                    <div class="space-y-2">
                        <div>
                            <label for="payDay" class="block text-sm text-gray-600">Pay Day</label>
                            <select name="cutoff_periods[0][pay_day]" id="payDay" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select pay day</option>
                                <option value="same_day">Same Day</option>
                                <option value="next_day">Next Day</option>
                                <option value="friday">Every Friday</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'weekly':
            fields = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Weekly Cycle Configuration</label>
                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="startDay" class="block text-sm text-gray-600">Start Day</label>
                                <select name="cutoff_periods[0][start_day]" id="startDay" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select start day</option>
                                    <option value="monday">Monday</option>
                                    <option value="sunday">Sunday</option>
                                </select>
                            </div>
                            <div>
                                <label for="endDay" class="block text-sm text-gray-600">End Day</label>
                                <select name="cutoff_periods[0][end_day]" id="endDay" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select end day</option>
                                    <option value="sunday">Sunday</option>
                                    <option value="saturday">Saturday</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="weeklyPayDay" class="block text-sm text-gray-600">Pay Day</label>
                            <select name="cutoff_periods[0][pay_day]" id="weeklyPayDay" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select pay day</option>
                                <option value="friday">Friday</option>
                                <option value="thursday">Thursday</option>
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="saturday">Saturday</option>
                                <option value="sunday">Sunday</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'semi_monthly':
            fields = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Semi-Monthly Periods Configuration</label>
                    <div class="space-y-4">
                        <div class="border rounded-lg p-3 bg-gray-50">
                            <h4 class="text-sm font-medium text-gray-800 mb-2">First Period</h4>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Start Day</label>
                                    <input type="text" name="cutoff_periods[0][start_day]" required placeholder="1-31 or EOM"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">End Day</label>
                                    <input type="text" name="cutoff_periods[0][end_day]" required placeholder="1-31 or EOM"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Pay Date</label>
                                    <input type="text" name="cutoff_periods[0][pay_date]" required placeholder="1-31 or EOM"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                            </div>
                        </div>
                        <div class="border rounded-lg p-3 bg-gray-50">
                            <h4 class="text-sm font-medium text-gray-800 mb-2">Second Period</h4>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Start Day</label>
                                    <input type="text" name="cutoff_periods[1][start_day]" required placeholder="1-31 or EOM"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                                <div>
                                    
                                    <label class="block text-xs text-gray-600 mb-1">End Day<br></label>
                                    <input type="text" name="cutoff_periods[1][end_day]" required
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Pay Date</label>
                                    <input type="text" name="cutoff_periods[1][pay_date]" required placeholder="1-31 or EOM"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'monthly':
            fields = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Period Configuration</label>
                    <div class="space-y-2">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="monthlyStartDay" class="block text-sm text-gray-600">Start Day</label>
                                <input type="text" name="cutoff_periods[0][start_day]" id="monthlyStartDay" required placeholder="1-31 or EOM"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                            </div>
                            <div>
                                <label for="monthlyEndDay" class="block text-sm text-gray-600">End Day</label>
                                <input type="text" name="cutoff_periods[0][end_day]" id="monthlyEndDay" required placeholder="1-31 or EOM"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                            </div>
                            <div>
                                <label for="monthlyPayDate" class="block text-sm text-gray-600">Pay Date</label>
                                <input type="text" name="cutoff_periods[0][pay_date]" id="monthlyPayDate" required placeholder="1-31 or EOM"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
    }
    
    return fields;
}

function closeAddScheduleModal() {
    document.getElementById('addScheduleModal').classList.add('hidden');
}

// Delete schedule functionality
function deleteSchedule(scheduleId, scheduleName) {
    if (confirm(`Are you sure you want to delete "${scheduleName}"? This action cannot be undone.`)) {
        // Create a form to delete the schedule
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = `{{ route('settings.pay-schedules.index') }}/${scheduleId}`;
        
        // Add CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        // Add method spoofing
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        form.appendChild(methodInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}

// Close modal when clicking outside
document.getElementById('addScheduleModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeAddScheduleModal();
    }
});

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddScheduleModal();
    }
});

function showPayScheduleContextMenu(event, scheduleId, name, description, isActive, isDefault) {
    console.log('=== RIGHT CLICK DETECTED ===');
    console.log('Schedule:', scheduleId, 'Name:', name, 'Active:', isActive, 'Default:', isDefault);
    
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    
    // FORCE remove ANY existing context menus - old or new
    const allMenus = document.querySelectorAll('[id*="contextMenu"], [id*="payScheduleContextMenu"], .context-menu, [class*="context"]');
    allMenus.forEach(menu => menu.remove());
    
    // ALWAYS use our custom menu to avoid any issues
    createPayScheduleContextMenu(event, scheduleId, name, isActive, isDefault);
}

function createPayScheduleContextMenu(event, scheduleId, name, isActive, isDefault) {
    console.log('Creating context menu for schedule:', scheduleId, 'name:', name);
    
    // Create the context menu element
    const contextMenu = document.createElement('div');
    contextMenu.id = 'contextMenu';
    contextMenu.className = 'fixed bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 min-w-48 backdrop-blur-sm transition-all duration-150 transform opacity-100 scale-100';
    
    // Create header
    const header = document.createElement('div');
    header.className = 'px-3 py-2 border-b border-gray-100 bg-gray-50 rounded-t-md';
    header.innerHTML = `
        <div class="text-sm font-medium text-gray-900">${name}</div>
        <div class="text-xs text-gray-500">${isDefault === 'true' ? 'Default Schedule' : 'Pay Schedule'}</div>
    `;
    contextMenu.appendChild(header);
    
    // Create actions container
    const actionsContainer = document.createElement('div');
    actionsContainer.className = 'py-1';
    
    // Create Edit button
    const editButton = document.createElement('a');
    editButton.href = '#';
    editButton.className = 'flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150';
    editButton.innerHTML = `
        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
        Edit Schedule
    `;
    editButton.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Edit button clicked! Schedule ID:', scheduleId);
        handleEdit(scheduleId);
    });
    actionsContainer.appendChild(editButton);
    
    // Create divider
    const divider = document.createElement('div');
    divider.className = 'border-t border-gray-100 my-1';
    actionsContainer.appendChild(divider);
    
    // Create Toggle button
    const toggleButton = document.createElement('a');
    toggleButton.href = '#';
    toggleButton.className = 'flex items-center px-3 py-2 text-sm text-yellow-700 hover:bg-yellow-50 hover:text-yellow-800 transition-colors duration-150';
    toggleButton.innerHTML = `
        <svg class="w-4 h-4 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
        </svg>
        ${isActive === 'true' ? 'Deactivate' : 'Activate'}
    `;
    toggleButton.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Toggle button clicked! Schedule ID:', scheduleId);
        handleToggle(scheduleId);
    });
    actionsContainer.appendChild(toggleButton);
    
    // Create Delete button (only if not default)
    if (isDefault !== 'true') {
        const deleteButton = document.createElement('a');
        deleteButton.href = '#';
        deleteButton.className = 'flex items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors duration-150';
        deleteButton.innerHTML = `
            <svg class="w-4 h-4 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
            Delete Schedule
        `;
        deleteButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Delete button clicked! Schedule ID:', scheduleId, 'Name:', name);
            handleDelete(scheduleId, name);
        });
        actionsContainer.appendChild(deleteButton);
    }
    
    contextMenu.appendChild(actionsContainer);
    document.body.appendChild(contextMenu);
    contextMenu.style.left = event.pageX + 'px';
    contextMenu.style.top = event.pageY + 'px';
    
    // Close menu when clicking elsewhere
    document.addEventListener('click', function closeMenu() {
        contextMenu.remove();
        document.removeEventListener('click', closeMenu);
    });
}



// Edit Modal Functions
function showEditModal(scheduleId) {
    console.log('Loading edit modal for schedule:', scheduleId);
    
    // Fetch schedule data with proper headers
    fetch(`/settings/pay-schedules/${scheduleId}/edit`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Received data:', data);
        if (data.success) {
            populateEditModal(data.schedule);
            document.getElementById('editScheduleModal').classList.remove('hidden');
        } else {
            console.error('Server returned error:', data);
            alert('Error loading schedule data: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Error loading schedule data: ' + error.message);
    });
}

function populateEditModal(schedule) {
    // Set form action
    document.getElementById('editScheduleForm').action = `/settings/pay-schedules/${schedule.id}`;
    
    // Update modal title
    const typeNames = {
        daily: 'Daily',
        weekly: 'Weekly',
        semi_monthly: 'Semi-Monthly',
        monthly: 'Monthly'
    };
    document.getElementById('edit-modal-title').textContent = `Edit ${typeNames[schedule.type]} Pay Schedule`;
    
    // Populate basic fields
    document.getElementById('editScheduleName').value = schedule.name;
    document.getElementById('editScheduleType').value = schedule.type;
    document.getElementById('editIsActive').checked = schedule.is_active;
    
    // Generate dynamic fields for this schedule type
    generateEditDynamicFields(schedule.type, schedule);
}

function generateEditDynamicFields(type, schedule) {
    const container = document.getElementById('editDynamicFields');
    let fields = '';
    
    // Helper function to get values safely
    const getValue = (path, defaultValue = '') => {
        const keys = path.split('.');
        let current = schedule;
        for (const key of keys) {
            if (current && typeof current === 'object' && key in current) {
                current = current[key];
            } else {
                return defaultValue;
            }
        }
        return current || defaultValue;
    };
    
    switch(type) {
        case 'daily':
            fields = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Pay Day Configuration</label>
                    <div class="space-y-2">
                        <div>
                            <label for="editPayDay" class="block text-sm text-gray-600">Pay Day</label>
                            <select name="cutoff_periods[0][pay_day]" id="editPayDay" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select pay day</option>
                                <option value="same_day" ${getValue('cutoff_periods.0.pay_day') === 'same_day' ? 'selected' : ''}>Same Day</option>
                                <option value="next_day" ${getValue('cutoff_periods.0.pay_day') === 'next_day' ? 'selected' : ''}>Next Day</option>
                                <option value="friday" ${getValue('cutoff_periods.0.pay_day') === 'friday' ? 'selected' : ''}>Every Friday</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'weekly':
            fields = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Weekly Cycle Configuration</label>
                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="editStartDay" class="block text-sm text-gray-600">Start Day</label>
                                <select name="cutoff_periods[0][start_day]" id="editStartDay" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select start day</option>
                                    <option value="monday" ${getValue('cutoff_periods.0.start_day') === 'monday' ? 'selected' : ''}>Monday</option>
                                    <option value="sunday" ${getValue('cutoff_periods.0.start_day') === 'sunday' ? 'selected' : ''}>Sunday</option>
                                </select>
                            </div>
                            <div>
                                <label for="editEndDay" class="block text-sm text-gray-600">End Day</label>
                                <select name="cutoff_periods[0][end_day]" id="editEndDay" required
                                        class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Select end day</option>
                                    <option value="sunday" ${getValue('cutoff_periods.0.end_day') === 'sunday' ? 'selected' : ''}>Sunday</option>
                                    <option value="saturday" ${getValue('cutoff_periods.0.end_day') === 'saturday' ? 'selected' : ''}>Saturday</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label for="editWeeklyPayDay" class="block text-sm text-gray-600">Pay Day</label>
                            <select name="cutoff_periods[0][pay_day]" id="editWeeklyPayDay" required
                                    class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Select pay day</option>
                                <option value="friday" ${getValue('cutoff_periods.0.pay_day') === 'friday' ? 'selected' : ''}>Friday</option>
                                <option value="thursday" ${getValue('cutoff_periods.0.pay_day') === 'thursday' ? 'selected' : ''}>Thursday</option>
                                <option value="monday" ${getValue('cutoff_periods.0.pay_day') === 'monday' ? 'selected' : ''}>Monday</option>
                                <option value="tuesday" ${getValue('cutoff_periods.0.pay_day') === 'tuesday' ? 'selected' : ''}>Tuesday</option>
                                <option value="wednesday" ${getValue('cutoff_periods.0.pay_day') === 'wednesday' ? 'selected' : ''}>Wednesday</option>
                                <option value="saturday" ${getValue('cutoff_periods.0.pay_day') === 'saturday' ? 'selected' : ''}>Saturday</option>
                                <option value="sunday" ${getValue('cutoff_periods.0.pay_day') === 'sunday' ? 'selected' : ''}>Sunday</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'semi_monthly':
            fields = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Semi-Monthly Periods Configuration</label>
                    <div class="space-y-4">
                        <div class="border rounded-lg p-3 bg-gray-50">
                            <h4 class="text-sm font-medium text-gray-800 mb-2">First Period</h4>
                            <div class="grid grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Start Day</label>
                                    <input type="text" name="cutoff_periods[0][start_day]" required placeholder="1-31 or EOM"
                                           value="${getValue('cutoff_periods.0.start_day')}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">End Day</label>
                                    <input type="text" name="cutoff_periods[0][end_day]" required placeholder="1-31 or EOM"
                                           value="${getValue('cutoff_periods.0.end_day')}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Pay Date</label>
                                    <input type="text" name="cutoff_periods[0][pay_date]" required placeholder="1-31 or EOM"
                                           value="${getValue('cutoff_periods.0.pay_date')}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                            </div>
                        </div>
                        <div class="border rounded-lg p-3 bg-gray-50">
                            <h4 class="text-sm font-medium text-gray-800 mb-2">Second Period</h4>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Start Day</label>
                                    <input type="text" name="cutoff_periods[1][start_day]" required placeholder="1-31 or EOM"
                                           value="${getValue('cutoff_periods.1.start_day')}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">End Day</label>
                                    <input type="text" name="cutoff_periods[1][end_day]" required placeholder="1-31 or EOM"
                                           value="${getValue('cutoff_periods.1.end_day')}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-600 mb-1">Pay Date</label>
                                    <input type="text" name="cutoff_periods[1][pay_date]" required placeholder="1-31 or EOM"
                                           value="${getValue('cutoff_periods.1.pay_date')}"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                    <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
            
        case 'monthly':
            fields = `
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Monthly Period Configuration</label>
                    <div class="space-y-2">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label for="editMonthlyStartDay" class="block text-sm text-gray-600">Start Day</label>
                                <input type="text" name="cutoff_periods[0][start_day]" id="editMonthlyStartDay" required placeholder="1-31 or EOM"
                                       value="${getValue('cutoff_periods.0.start_day')}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                            </div>
                            <div>
                                <label for="editMonthlyEndDay" class="block text-sm text-gray-600">End Day</label>
                                <input type="text" name="cutoff_periods[0][end_day]" id="editMonthlyEndDay" required placeholder="1-31 or EOM"
                                       value="${getValue('cutoff_periods.0.end_day')}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                            </div>
                            <div>
                                <label for="editMonthlyPayDate" class="block text-sm text-gray-600">Pay Date</label>
                                <input type="text" name="cutoff_periods[0][pay_date]" id="editMonthlyPayDate" required placeholder="1-31 or EOM"
                                       value="${getValue('cutoff_periods.0.pay_date')}"
                                       class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            break;
    }
    
    container.innerHTML = fields;
}

function closeEditScheduleModal() {
    document.getElementById('editScheduleModal').classList.add('hidden');
    document.getElementById('editScheduleForm').reset();
    document.getElementById('editDynamicFields').innerHTML = '';
}
</script>
    </div>
</div>
</x-app-layout>
