<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Paid Leaves') }}
            </h2>
            @can('create paid leaves')
            <div class="relative">
                <button id="addPaidLeaveBtn" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        oncontextmenu="showAddContextMenu(event)"
                        onclick="window.location.href='{{ route('paid-leaves.create') }}'">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    New Paid Leave
                </button>
                
                <!-- Add Context Menu -->
                <div id="addContextMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 hidden">
                    <a href="{{ route('paid-leaves.create') }}" 
                       class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Submit New Request
                    </a>
                    <a href="{{ route('paid-leaves.index', ['status' => 'pending']) }}" 
                       class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        View Pending Requests
                    </a>
                    @can('approve paid leaves')
                    <div class="border-t border-gray-100 my-1"></div>
                    <a href="{{ route('paid-leaves.index', ['status' => 'approved']) }}" 
                       class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        View Approved Requests
                    </a>
                    @endcan
                </div>
            </div>
            @endcan
        </div>
    </x-slot>
    
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <!-- Filter Inputs in 1 Row -->
                    <div class="flex flex-wrap items-end gap-4 mb-4 w-full">
                        @unless(auth()->user()->hasRole('Employee'))
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-sm font-medium text-gray-700">Name Search</label>
                            <input type="text" name="name_search" id="name_search" value="{{ request('name_search') }}" 
                                   placeholder="Search employee name..."
                                   class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm paid-leave-filter focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        @endunless
                        
                        @if(Auth::user()->isSuperAdmin())
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-sm font-medium text-gray-700">Company</label>
                            <select name="company" id="company" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm paid-leave-filter focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ strtolower($company->name) }}" {{ request('company') == strtolower($company->name) ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm paid-leave-filter focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>

                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-sm font-medium text-gray-700">Leave Type</label>
                            <select name="leave_type" id="leave_type" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm paid-leave-filter focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Types</option>
                                <option value="sick_leave" {{ request('leave_type') === 'sick_leave' ? 'selected' : '' }}>Sick Leave</option>
                                <option value="vacation_leave" {{ request('leave_type') === 'vacation_leave' ? 'selected' : '' }}>Vacation Leave</option>
                                <option value="emergency_leave" {{ request('leave_type') === 'emergency_leave' ? 'selected' : '' }}>Emergency Leave</option>
                                <option value="maternity_leave" {{ request('leave_type') === 'maternity_leave' ? 'selected' : '' }}>Maternity Leave</option>
                                <option value="paternity_leave" {{ request('leave_type') === 'paternity_leave' ? 'selected' : '' }}>Paternity Leave</option>
                                <option value="bereavement_leave" {{ request('leave_type') === 'bereavement_leave' ? 'selected' : '' }}>Bereavement Leave</option>
                            </select>
                        </div>

                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-sm font-medium text-gray-700">Date Approved</label>
                            <input type="date" name="date_approved" id="date_approved" value="{{ request('date_approved') }}" 
                                   class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm paid-leave-filter focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="flex items-end gap-2">
                            <button type="button" id="reset_filters" class="inline-flex items-center px-4 h-10 bg-gray-600 border border-transparent rounded-md text-white text-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                            <button type="button" id="generate_summary" class="inline-flex items-center px-4 h-10 bg-green-600 border border-transparent rounded-md text-white text-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Paid Leave Summary
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-4 mb-6">
                <!-- Total Approved Amount -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Approved Amount</dt>
                                    <dd class="text-lg font-medium text-gray-900" data-summary="total-approved-amount">₱{{ number_format($summaryStats['total_approved_amount'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Approved Leave -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Approved Leave</dt>
                                    <dd class="text-lg font-medium text-gray-900" data-summary="total-approved-leave">{{ $summaryStats['total_approved_leave'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Pending Amount -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Pending Amount</dt>
                                    <dd class="text-lg font-medium text-gray-900" data-summary="total-pending-amount">₱{{ number_format($summaryStats['total_pending_amount'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Pending Leave -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Pending Leave</dt>
                                    <dd class="text-lg font-medium text-gray-900" data-summary="total-pending-leave">{{ $summaryStats['total_pending_leave'] ?? 0 }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Paid Leaves Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Paid Leaves 
                                <span class="text-sm font-normal text-gray-500">
                                    ({{ $paidLeaves->total() }} total paid leaves)
                                </span>
                            </h3>
                        </div>
                        <div class="text-sm text-gray-600">
                            <div class="text-xs text-blue-600">
                                <strong>Tip:</strong> Right-click on any paid leave row to access View, Approve, and Delete actions.
                            </div>
                        </div>
                    </div>
                    
                    <div id="paid-leave-list-container">
                        @include('paid-leaves.partials.paid-leave-list', ['paidLeaves' => $paidLeaves])
                    </div>
                    
                    <div id="pagination-container">
                        @include('paid-leaves.partials.pagination', ['paidLeaves' => $paidLeaves])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Live filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('.paid-leave-filter');

            // Debounce function to limit API calls
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }

            // Function to apply filters via AJAX (no page reload)
            function applyFilters() {
                const url = new URL(window.location.origin + window.location.pathname);
                const params = new URLSearchParams();
                const currentParams = new URLSearchParams(window.location.search);

                // Get filter values
                const nameSearchEl = document.getElementById('name_search');
                const nameSearch = nameSearchEl ? nameSearchEl.value : '';
                const status = document.getElementById('status').value;
                const leaveType = document.getElementById('leave_type').value;
                const dateApproved = document.getElementById('date_approved').value;
                const perPage = document.getElementById('per_page')?.value || 10;

                // Add filter parameters
                if (nameSearch) params.set('name_search', nameSearch);
                if (status) params.set('status', status);
                if (leaveType) params.set('leave_type', leaveType);
                if (dateApproved) params.set('date_approved', dateApproved);
                if (perPage) params.set('per_page', perPage);

                // Copy over existing parameters that aren't filters
                for (const [key, value] of currentParams) {
                    if (!['name_search', 'status', 'leave_type', 'date_approved', 'per_page', 'page'].includes(key)) {
                        params.set(key, value);
                    }
                }

                // Update URL without page reload
                url.search = params.toString();
                window.history.pushState({}, '', url.toString());

                // Make AJAX request to get filtered data
                fetch(url.toString(), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                })
                .then(response => response.json())
                .then(data => {
                    // Update paid leave list
                    document.getElementById('paid-leave-list-container').innerHTML = data.html;
                    
                    // Update pagination
                    document.getElementById('pagination-container').innerHTML = data.pagination;
                    
                    // Update summary statistics
                    if (data.summary_stats) {
                        const stats = data.summary_stats;
                        // Update summary card values
                        document.querySelector('[data-summary="total-approved-amount"]').textContent = '₱' + new Intl.NumberFormat().format(stats.total_approved_amount);
                        document.querySelector('[data-summary="total-approved-leave"]').textContent = stats.total_approved_leave;
                        document.querySelector('[data-summary="total-pending-amount"]').textContent = '₱' + new Intl.NumberFormat().format(stats.total_pending_amount);
                        document.querySelector('[data-summary="total-pending-leave"]').textContent = stats.total_pending_leave;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }

            // Add event listeners for live filtering
            filterSelects.forEach(select => {
                if (select.type === 'text') {
                    // Text inputs use debounced 'input' event for live typing
                    select.addEventListener('input', debounce(applyFilters, 500));
                } else {
                    // Select and date inputs use 'change' event
                    select.addEventListener('change', applyFilters);
                }
            });

            // Handle per page selection
            const perPageSelect = document.getElementById('per_page');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', function() {
                    applyFilters(); // Use AJAX instead of page reload
                });
            }

            // Reset filters functionality
            document.getElementById('reset_filters').addEventListener('click', function() {
                window.location.href = '{{ route("paid-leaves.index") }}';
            });

            // Generate Paid Leave Summary functionality
            document.getElementById('generate_summary').addEventListener('click', function() {
                // Show the export modal or redirect to summary export
                window.open('{{ route("paid-leaves.index") }}?export=summary', '_blank');
            });

            // Context menu functionality
            document.addEventListener('click', function(e) {
                const contextMenu = document.getElementById('addContextMenu');
                if (!e.target.closest('#addPaidLeaveBtn') && !e.target.closest('#addContextMenu')) {
                    contextMenu.classList.add('hidden');
                }
            });

            // Generate summary
            document.getElementById('generate_summary').addEventListener('click', function() {
                // Implement summary generation
                alert('Paid Leave Summary generation will be implemented');
            });
        });

        function showAddContextMenu(event) {
            event.preventDefault();
            const contextMenu = document.getElementById('addContextMenu');
            contextMenu.classList.toggle('hidden');
        }

        function applyFilters() {
            const form = new FormData();
            document.querySelectorAll('.paid-leave-filter').forEach(element => {
                if (element.value) {
                    form.append(element.name, element.value);
                }
            });

            const params = new URLSearchParams(form);
            const url = new URL(window.location.href);
            url.search = params.toString();
            window.location.href = url.toString();
        }

        // Paid Leave Row Context Menu
        let selectedPaidLeaveId = null;

        function showPaidLeaveContextMenu(event, row) {
            event.preventDefault();
            event.stopPropagation();

            const contextMenu = document.getElementById('contextMenu');
            const paidLeaveId = row.dataset.paidLeaveId;
            const reference = row.dataset.reference;
            const employee = row.dataset.employee;
            const status = row.dataset.status;

            selectedPaidLeaveId = paidLeaveId;

            // Update context menu content
            document.getElementById('contextMenuPaidLeave').textContent = reference;
            document.getElementById('contextMenuEmployee').textContent = employee;

            // Show/hide actions based on status
            const editAction = document.getElementById('contextMenuEdit');
            const approveAction = document.getElementById('contextMenuApprove');
            const rejectAction = document.getElementById('contextMenuReject');

            if (status === 'pending') {
                editAction.style.display = 'flex';
                @can('approve paid leaves')
                approveAction.style.display = 'flex';
                rejectAction.style.display = 'flex';
                @else
                approveAction.style.display = 'none';
                rejectAction.style.display = 'none';
                @endcan
            } else {
                editAction.style.display = 'none';
                approveAction.style.display = 'none';
                rejectAction.style.display = 'none';
            }
            
            // Show/hide delete based on status and user role
            @can('delete paid leaves')
            @if(auth()->user()->hasRole(['System Administrator', 'HR Head']))
            // System Admin and HR Head can delete all statuses - no restriction
            @else
            // HR Staff can only delete pending
            const deleteAction = document.getElementById('contextMenuDelete');
            if (status === 'pending') {
                deleteAction.style.display = 'flex';
            } else {
                deleteAction.style.display = 'none';
            }
            @endif
            @else
            // For employees (no delete permission), they can only delete their own pending requests
            @if(auth()->user()->hasRole('Employee'))
            const deleteAction = document.getElementById('contextMenuDelete');
            if (deleteAction && status === 'pending') {
                deleteAction.style.display = 'flex';
            } else if (deleteAction) {
                deleteAction.style.display = 'none';
            }
            @endif
            @endcan

            // Update links
            document.getElementById('contextMenuView').href = '{{ url('paid-leaves') }}/' + paidLeaveId;
            document.getElementById('contextMenuEdit').href = '{{ url('paid-leaves') }}/' + paidLeaveId + '/edit';
            
            // Position and show context menu at mouse position
            const rect = document.body.getBoundingClientRect();
            const menuWidth = 208; // min-w-52 = 208px
            const menuHeight = 300; // approximate height
            
            let left = event.clientX;
            let top = event.clientY;
            
            // Adjust if menu would go off screen
            if (left + menuWidth > window.innerWidth) {
                left = window.innerWidth - menuWidth - 10;
            }
            if (top + menuHeight > window.innerHeight) {
                top = window.innerHeight - menuHeight - 10;
            }
            
            contextMenu.style.left = left + 'px';
            contextMenu.style.top = top + 'px';
            contextMenu.classList.remove('hidden', 'opacity-0', 'scale-95');
            contextMenu.classList.add('opacity-100', 'scale-100');
        }

        // Context menu actions with event delegation
        document.addEventListener('click', function(e) {
            // Check if the clicked element or its parent is the approve button
            const approveBtn = e.target.closest('#contextMenuApprove');
            if (approveBtn) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Approve clicked, selectedPaidLeaveId:', selectedPaidLeaveId);
                if (selectedPaidLeaveId) {
                    // Route to the individual paid leave page
                    window.location.href = '{{ url('paid-leaves') }}/' + selectedPaidLeaveId;
                }
                hideContextMenu();
            }

            const rejectBtn = e.target.closest('#contextMenuReject');
            if (rejectBtn) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Reject clicked, selectedPaidLeaveId:', selectedPaidLeaveId);
                if (selectedPaidLeaveId) {
                    // Route to the individual paid leave page
                    window.location.href = '{{ url('paid-leaves') }}/' + selectedPaidLeaveId;
                }
                hideContextMenu();
            }

            const deleteBtn = e.target.closest('#contextMenuDelete');
            if (deleteBtn) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Delete clicked, selectedPaidLeaveId:', selectedPaidLeaveId);
                
                // Get reference number and status for better confirmation message
                const row = document.querySelector(`tr[data-paid-leave-id="${selectedPaidLeaveId}"]`);
                const reference = row ? row.dataset.reference : 'Unknown';
                const status = row ? row.dataset.status : 'Unknown';
                
                if (selectedPaidLeaveId && confirm(`Are you sure you want to delete paid leave request? This action cannot be undone.`)) {
                    // Create form element
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '{{ url('paid-leaves') }}/' + selectedPaidLeaveId;
                    form.style.display = 'none';
                    
                    // Add CSRF token
                    const tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = '_token';
                    tokenInput.value = '{{ csrf_token() }}';
                    form.appendChild(tokenInput);
                    
                    // Add method override for DELETE
                    const methodInput = document.createElement('input');
                    methodInput.type = 'hidden';
                    methodInput.name = '_method';
                    methodInput.value = 'DELETE';
                    form.appendChild(methodInput);
                    
                    console.log('Submitting delete form:', {
                        action: form.action,
                        method: form.method,
                        token: tokenInput.value,
                        methodOverride: methodInput.value
                    });
                    
                    // Append to body and submit
                    document.body.appendChild(form);
                    
                    // Use setTimeout to ensure form is properly appended before submission
                    setTimeout(() => {
                        form.submit();
                    }, 10);
                } else {
                    console.log('Delete cancelled or no selectedPaidLeaveId');
                }
                hideContextMenu();
            }
        });

        // Hide context menu
        function hideContextMenu() {
            const contextMenu = document.getElementById('contextMenu');
            contextMenu.classList.add('opacity-0', 'scale-95');
            contextMenu.classList.remove('opacity-100', 'scale-100');
            setTimeout(() => {
                contextMenu.classList.add('hidden');
            }, 150);
        }

        // Hide context menu when clicking elsewhere
        document.addEventListener('click', function(event) {
            const contextMenu = document.getElementById('contextMenu');
            if (!contextMenu.contains(event.target) && !event.target.closest('.paid-leave-row')) {
                hideContextMenu();
            }
        });
    </script>

    <!-- Context Menu -->
    <div id="contextMenu" class="fixed bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 hidden min-w-52 backdrop-blur-sm transition-all duration-150 transform opacity-0 scale-95">
        <div id="contextMenuHeader" class="px-3 py-2 border-b border-gray-100 bg-gray-50 rounded-t-md">
            <div class="text-sm font-medium text-gray-900" id="contextMenuPaidLeave"></div>
            <div class="text-xs text-gray-500" id="contextMenuEmployee"></div>
        </div>
        
        <div class="py-1">
            <a href="#" id="contextMenuView" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Details
            </a>

            <a href="#" id="contextMenuEdit" class="flex items-center px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50 hover:text-indigo-900 transition-colors duration-150" style="display: none;">
                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit
            </a>

            <a href="#" id="contextMenuApprove" class="flex items-center px-3 py-2 text-sm text-green-700 hover:bg-green-50 hover:text-green-900 transition-colors duration-150" style="display: none;">
                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Approve
            </a>

            <a href="#" id="contextMenuReject" class="flex items-center px-3 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900 transition-colors duration-150" style="display: none;">
                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Reject
            </a>

            <div class="border-t border-gray-100 my-1"></div>

            <a href="#" id="contextMenuDelete" class="flex items-center px-3 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900 transition-colors duration-150">
                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Delete
            </a>
        </div>
    </div>
</x-app-layout>