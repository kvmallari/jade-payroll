<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Cash Advances') }}
            </h2>
            @can('create cash advances')
            <div class="relative">
                <button id="addCashAdvanceBtn" 
                        class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                        oncontextmenu="showAddContextMenu(event)"
                        onclick="window.location.href='{{ route('cash-advances.create') }}'">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    New Cash Advance
                </button>
                
                <!-- Add Context Menu -->
                <div id="addContextMenu" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 hidden">
                    <a href="{{ route('cash-advances.create') }}" 
                       class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Submit New Request
                    </a>
                    <a href="{{ route('cash-advances.index', ['status' => 'pending']) }}" 
                       class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        View Pending Requests
                    </a>
                    @can('approve cash advances')
                    <div class="border-t border-gray-100 my-1"></div>
                    <a href="{{ route('cash-advances.index', ['status' => 'approved']) }}" 
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
                                   class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm cash-advance-filter focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        @endunless
                        
                        @if(Auth::user()->isSuperAdmin())
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-sm font-medium text-gray-700">Company</label>
                            <select name="company" id="company" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm cash-advance-filter focus:border-indigo-500 focus:ring-indigo-500">
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
                            <select name="status" id="status" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm cash-advance-filter focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                <option value="fully_paid" {{ request('status') === 'fully_paid' ? 'selected' : '' }}>Fully Paid</option>
                                <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>

                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-sm font-medium text-gray-700">Date Approved</label>
                            <input type="date" name="date_approved" id="date_approved" value="{{ request('date_approved') }}" 
                                   class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm cash-advance-filter focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-sm font-medium text-gray-700">Date Completed</label>
                            <input type="date" name="date_completed" id="date_completed" value="{{ request('date_completed') }}" 
                                   class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm cash-advance-filter focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="flex items-end gap-2">
                            <button type="button" id="reset_filters" class="inline-flex items-center px-4 h-10 bg-gray-600 border border-transparent rounded-md text-white text-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                                <svg class="w-4 h-4 " fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                            <button type="button" id="generate_summary" class="inline-flex items-center px-4 h-10 bg-green-600 border border-transparent rounded-md text-white text-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                               Cash Advance Summary
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
                                    <dd class="text-lg font-medium text-gray-900">₱{{ number_format($summaryStats['total_approved_amount'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Interest Amount -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Interest Amount</dt>
                                    <dd class="text-lg font-medium text-gray-900">₱{{ number_format($summaryStats['total_interest_amount'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Paid Amount -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Paid Amount</dt>
                                    <dd class="text-lg font-medium text-gray-900">₱{{ number_format($summaryStats['total_paid_amount'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Unpaid Amount -->
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
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Unpaid Amount</dt>
                                    <dd class="text-lg font-medium text-gray-900">₱{{ number_format($summaryStats['total_unpaid_amount'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>            <!-- Cash Advances List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Cash Advances</h3>
                        <div class="text-sm text-gray-600">
                            <div>Showing {{ $cashAdvances->count() }} of {{ $cashAdvances->total() }} cash advances</div>
                            <div class="text-xs text-blue-600 mt-1">
                                <strong>Tip:</strong> Right-click on any cash advance row to access View, Approve, and Delete actions.
                            </div>
                        </div>
                    </div>

                    <div id="cash-advance-list-container">
                        @include('cash-advances.partials.cash-advance-list', ['cashAdvances' => $cashAdvances])
                    </div>

                    <div id="pagination-container">
                        @include('cash-advances.partials.pagination', ['cashAdvances' => $cashAdvances])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Context Menu -->
    <div id="contextMenu" class="fixed bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 hidden min-w-52 backdrop-blur-sm transition-all duration-150 transform opacity-0 scale-95">
        <div id="contextMenuHeader" class="px-3 py-2 border-b border-gray-100 bg-gray-50 rounded-t-md">
            <div class="text-sm font-medium text-gray-900" id="contextMenuCashAdvance"></div>
            <div class="text-xs text-gray-500" id="contextMenuEmployee"></div>
        </div>
        
        <div class="py-1">
            <a href="#" id="contextMenuView" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-150">
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                View Details
            </a>
            
            @can('edit cash advances')
            <a href="#" id="contextMenuEdit" class="flex items-center px-3 py-2 text-sm text-blue-700 hover:bg-blue-50 hover:text-blue-900 transition-colors duration-150">
                <svg class="w-4 h-4 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit
            </a>
            @endcan
            
            @can('approve cash advances')
            <a href="#" id="contextMenuApprove" class="flex items-center px-3 py-2 text-sm text-green-700 hover:bg-green-50 hover:text-green-900 transition-colors duration-150" style="display: none;">
                <svg class="w-4 h-4 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Approve
            </a>
            
            <a href="#" id="contextMenuReject" class="flex items-center px-3 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900 transition-colors duration-150" style="display: none;">
                <svg class="w-4 h-4 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                Reject
            </a>
            @endcan
            
            @can('delete cash advances')
            <div class="border-t border-gray-100 my-1"></div>
            <a href="#" id="contextMenuDelete" class="flex items-center px-3 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900 transition-colors duration-150">
                <svg class="w-4 h-4 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Delete
            </a>
            @endcan
        </div>
    </div>

    <script>
        let currentCashAdvanceId = null;
        let currentStatus = null;
        
        // Add Cash Advance Context Menu
        function showAddContextMenu(event) {
            event.preventDefault();
            event.stopPropagation();
            
            const addContextMenu = document.getElementById('addContextMenu');
            addContextMenu.classList.toggle('hidden');
        }
        
        // Hide add context menu when clicking elsewhere
        document.addEventListener('click', function(event) {
            const addContextMenu = document.getElementById('addContextMenu');
            const addBtn = document.getElementById('addCashAdvanceBtn');
            
            if (addBtn && addContextMenu && !addBtn.contains(event.target) && !addContextMenu.contains(event.target)) {
                addContextMenu.classList.add('hidden');
            }
        });
        
        // Cash Advance Row Context Menu
        let currentRequestedAmount = 0;
        function showContextMenu(event, id, reference, employee, status, requestedAmount) {
            event.preventDefault();
            event.stopPropagation();
            
            currentCashAdvanceId = id;
            currentStatus = status;
            currentRequestedAmount = requestedAmount || 0;
            
            // Update context menu content
            document.getElementById('contextMenuCashAdvance').textContent = reference;
            document.getElementById('contextMenuEmployee').textContent = employee;
            
            // Update action URLs
            document.getElementById('contextMenuView').href = '{{ route("cash-advances.index") }}/' + id;
            @can('edit cash advances')
            const contextMenuEdit = document.getElementById('contextMenuEdit');
            if (contextMenuEdit) {
                contextMenuEdit.href = '{{ route("cash-advances.index") }}/' + id + '/edit';
                
                // Show/hide Edit button based on status (only for pending)
                if (status === 'pending') {
                    contextMenuEdit.style.display = 'flex';
                } else {
                    contextMenuEdit.style.display = 'none';
                }
            }
            @endcan
            
            // Show/hide actions based on status and permissions
            @can('approve cash advances')
            if (status === 'pending') {
                document.getElementById('contextMenuApprove').style.display = 'flex';
                document.getElementById('contextMenuReject').style.display = 'flex';
            } else {
                document.getElementById('contextMenuApprove').style.display = 'none';
                document.getElementById('contextMenuReject').style.display = 'none';
            }
            @endcan
            
            // Show/hide delete based on status and user role
            @can('delete cash advances')
            @if(auth()->user()->hasRole(['System Administrator', 'HR Head']))
            // System Admin and HR Head can delete all statuses
            // No status restriction for these roles
            @else
            // HR Staff can only delete pending/processing
            if (status === 'pending') {
                document.getElementById('contextMenuDelete').style.display = 'flex';
            } else {
                document.getElementById('contextMenuDelete').style.display = 'none';
            }
            @endif
            @endcan
            
            // Show context menu
            const contextMenu = document.getElementById('contextMenu');
            contextMenu.style.left = event.pageX + 'px';
            contextMenu.style.top = event.pageY + 'px';
            contextMenu.classList.remove('hidden');
            contextMenu.classList.add('opacity-100', 'scale-100');
            contextMenu.classList.remove('opacity-0', 'scale-95');
        }
        
        // Hide context menu when clicking elsewhere
        document.addEventListener('click', function(event) {
            const contextMenu = document.getElementById('contextMenu');
            if (contextMenu && !contextMenu.contains(event.target)) {
                contextMenu.classList.add('opacity-0', 'scale-95');
                contextMenu.classList.remove('opacity-100', 'scale-100');
                setTimeout(() => {
                    contextMenu.classList.add('hidden');
                }, 150);
            }
        });
        
        // Handle approve action
        const contextMenuApprove = document.getElementById('contextMenuApprove');
        if (contextMenuApprove) {
            contextMenuApprove.addEventListener('click', function(e) {
                e.preventDefault();
                if (currentCashAdvanceId && confirm('Are you sure you want to approve this cash advance request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("cash-advances") }}/' + currentCashAdvanceId + '/approve';
                
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = '{{ csrf_token() }}';
                form.appendChild(tokenInput);

                // Add required fields for approval with default values
                const approvedAmountInput = document.createElement('input');
                approvedAmountInput.type = 'hidden';
                approvedAmountInput.name = 'approved_amount';
                approvedAmountInput.value = currentRequestedAmount;
                form.appendChild(approvedAmountInput);

                const installmentsInput = document.createElement('input');
                installmentsInput.type = 'hidden';
                installmentsInput.name = 'installments';
                installmentsInput.value = '1';
                form.appendChild(installmentsInput);

                const interestRateInput = document.createElement('input');
                interestRateInput.type = 'hidden';
                interestRateInput.name = 'interest_rate';
                interestRateInput.value = '0';
                form.appendChild(interestRateInput);

                const remarksInput = document.createElement('input');
                remarksInput.type = 'hidden';
                remarksInput.name = 'remarks';
                remarksInput.value = 'Approved by administrator';
                form.appendChild(remarksInput);
                
                document.body.appendChild(form);
                form.submit();
                }
            });
        }
        
        // Handle reject action
        const contextMenuReject = document.getElementById('contextMenuReject');
        if (contextMenuReject) {
            contextMenuReject.addEventListener('click', function(e) {
                e.preventDefault();
                if (currentCashAdvanceId && confirm('Are you sure you want to reject this cash advance request?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("cash-advances") }}/' + currentCashAdvanceId + '/reject';
                
                const tokenInput = document.createElement('input');
                tokenInput.type = 'hidden';
                tokenInput.name = '_token';
                tokenInput.value = '{{ csrf_token() }}';
                form.appendChild(tokenInput);

                const remarksInput = document.createElement('input');
                remarksInput.type = 'hidden';
                remarksInput.name = 'remarks';
                remarksInput.value = 'Request rejected by administrator';
                form.appendChild(remarksInput);
                
                document.body.appendChild(form);
                form.submit();
                }
            });
        }
        
        // Handle delete action
        @can('delete cash advances')
        const contextMenuDelete = document.getElementById('contextMenuDelete');
        if (contextMenuDelete) {
            contextMenuDelete.addEventListener('click', function(e) {
                e.preventDefault();
            if (confirm('Are you sure you want to delete this cash advance? This action cannot be undone.')) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("cash-advances.index") }}/' + currentCashAdvanceId;
                
                let csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);
                
                let methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'DELETE';
                form.appendChild(methodInput);
                
                document.body.appendChild(form);
                form.submit();
            }
            });
        }
        @endcan

        // Live filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('.cash-advance-filter');

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
                const statusEl = document.getElementById('status');
                const status = statusEl ? statusEl.value : '';
                const dateApprovedEl = document.getElementById('date_approved');
                const dateApproved = dateApprovedEl ? dateApprovedEl.value : '';
                const dateCompletedEl = document.getElementById('date_completed');
                const dateCompleted = dateCompletedEl ? dateCompletedEl.value : '';
                const perPage = document.getElementById('per_page')?.value || 10;

                // Add filter parameters
                if (nameSearch) params.set('name_search', nameSearch);
                if (status) params.set('status', status);
                if (dateApproved) params.set('date_approved', dateApproved);
                if (dateCompleted) params.set('date_completed', dateCompleted);
                if (perPage) params.set('per_page', perPage);

                // Copy over existing parameters that aren't filters
                for (const [key, value] of currentParams) {
                    if (!['name_search', 'status', 'date_approved', 'date_completed', 'per_page', 'page'].includes(key)) {
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
                    // Update cash advance list
                    document.getElementById('cash-advance-list-container').innerHTML = data.html;
                    
                    // Update pagination
                    document.getElementById('pagination-container').innerHTML = data.pagination;
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
            const resetBtn = document.getElementById('reset_filters');
            if (resetBtn) {
                resetBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.location.href = '{{ route("cash-advances.index") }}';
                });
            }

            // Generate Cash Advance Summary functionality
            const generateSummaryBtn = document.getElementById('generate_summary');
            if (generateSummaryBtn) {
                generateSummaryBtn.addEventListener('click', function() {
                    // Show the export modal
                    const exportModal = document.getElementById('exportModal');
                    if (exportModal) {
                        exportModal.classList.remove('hidden');
                    }
                });
            }

            // Modal functionality
            const closeModal = document.getElementById('closeModal');
            if (closeModal) {
                closeModal.addEventListener('click', function() {
                    const exportModal = document.getElementById('exportModal');
                    if (exportModal) {
                        exportModal.classList.add('hidden');
                    }
                });
            }

            // Close modal when clicking outside
            const exportModal = document.getElementById('exportModal');
            if (exportModal) {
                exportModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        this.classList.add('hidden');
                    }
                });
            }

            // PDF Export
            const exportPDF = document.getElementById('exportPDF');
            if (exportPDF) {
                exportPDF.addEventListener('click', function() {
                    generateSummary('pdf');
                    const exportModal = document.getElementById('exportModal');
                    if (exportModal) {
                        exportModal.classList.add('hidden');
                    }
                });
            }

            // Excel Export
            const exportExcel = document.getElementById('exportExcel');
            if (exportExcel) {
                exportExcel.addEventListener('click', function() {
                    generateSummary('excel');
                    const exportModal = document.getElementById('exportModal');
                    if (exportModal) {
                        exportModal.classList.add('hidden');
                    }
                });
            }

            // Function to generate summary
            function generateSummary(format) {
                const currentFilters = new URLSearchParams(window.location.search);
                
                // Add export format to parameters
                currentFilters.set('export', format);
                currentFilters.set('action', 'generate_summary');

                // Create form and submit for file download
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("cash-advances.generate-summary") }}';
                
                // Add CSRF token
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);

                // Add all current filter parameters
                for (const [key, value] of currentFilters) {
                    if (key !== 'page') { // Exclude pagination
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = key;
                        input.value = value;
                        form.appendChild(input);
                    }
                }

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        });
    </script>

    <!-- Export Format Modal -->
    <div id="exportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-green-100">
                    <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <h3 class="text-lg leading-6 font-medium text-gray-900 mt-4">Choose Export Format</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500">
                        Select the format for your cash advance summary export:
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="exportPDF" class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300 mb-3">
                        <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                        Export as PDF
                    </button>
                    <button id="exportExcel" class="px-4 py-2 bg-green-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-300 mb-3">
                        <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm4 2a1 1 0 000 2h4a1 1 0 100-2H8zm0 3a1 1 0 000 2h4a1 1 0 100-2H8zm0 3a1 1 0 000 2h4a1 1 0 100-2H8z" clip-rule="evenodd"></path>
                        </svg>
                        Export as Excel
                    </button>
                    <button id="closeModal" class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

