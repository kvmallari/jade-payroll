<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div class="flex items-center space-x-4">
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                        {{ __('Payroll Management') }}
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Manage all payrolls across different schedules
                    </p>
                </div>
            </div>
        </div>
    </x-slot>

    <style>
        .loading-spinner {
            width: 48px;
            height: 48px;
            border: 4px solid #e5e7eb;
            border-top: 4px solid #3b82f6;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="fixed inset-0 bg-black bg-opacity-70 flex justify-center items-center z-50" style="display: none; backdrop-filter: blur(3px);">
        <div class="bg-white p-8 rounded-xl text-center max-w-md mx-4 shadow-2xl">
            <div class="loading-spinner mx-auto mb-4"></div>
            <div class="text-lg font-semibold text-gray-800 mb-2" id="loadingText">Sending Email...</div>
            <div class="text-sm text-gray-600" id="loadingSubtext">Please wait while we process your request.</div>
        </div>
    </div>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <!-- Filter Inputs and Action Buttons in 1 Row -->
                    <div class="flex flex-wrap items-end gap-4 mb-4 w-full">
                       
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-sm font-medium text-gray-700">Name Search</label>
                            <input type="text" name="name_search" id="name_search" value="{{ request('name_search') }}" 
                                   placeholder="Search employee name..."
                                   class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm payroll-filter focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                         @if(Auth::user()->isSuperAdmin())
                        <div class="flex-1 min-w-[180px]">
                            <label class="block text-sm font-medium text-gray-700">Company</label>
                            <select name="company" id="company" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm payroll-filter focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ strtolower($company->name) }}" {{ request('company') == strtolower($company->name) ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="flex-1 ">
                            <label class="block text-sm font-medium text-gray-700">Pay Schedule</label>
                            <select name="pay_schedule" id="pay_schedule" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm payroll-filter focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Schedules</option>
                                <option value="daily" {{ request('pay_schedule') == 'daily' ? 'selected' : '' }}>Daily</option>
                                <option value="weekly" {{ request('pay_schedule') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                <option value="semi_monthly" {{ request('pay_schedule') == 'semi_monthly' ? 'selected' : '' }}>Semi Monthly</option>
                                <option value="monthly" {{ request('pay_schedule') == 'monthly' ? 'selected' : '' }}>Monthly</option>
                            </select>
                        </div>
                        <div class="flex-1 ">
                            <label class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm payroll-filter focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                <option value="processing" {{ request('status') == 'processing' ? 'selected' : '' }}>Processing</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="paid" {{ request('status') == 'paid' ? 'selected' : '' }}>Paid</option>
                            </select>
                        </div>
                        <div class="flex-1 ">
                            <label class="block text-sm font-medium text-gray-700">Type</label>
                            <select name="type" id="type" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm payroll-filter focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Types</option>
                                <option value="automated" {{ request('type') == 'automated' ? 'selected' : '' }}>Automated</option>
                                <option value="manual" {{ request('type') == 'manual' ? 'selected' : '' }}>Manual</option>
                            </select>
                        </div>
                        <div class="flex-1 ">
                            <label class="block text-sm font-medium text-gray-700">Pay Period</label>
                            <select name="pay_period" id="pay_period" class="mt-1 block w-full h-10 px-3 border-gray-300 rounded-md shadow-sm payroll-filter focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">All Periods</option>
                                <!-- Pay periods will be populated dynamically based on schedule selection -->
                            </select>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" id="reset_filters" class="inline-flex items-center px-4 h-10 bg-gray-600 border border-transparent rounded-md text-white text-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                         <button type="button" id="generate_summary" class="inline-flex items-center px-4 h-10 bg-green-600 border border-transparent rounded-md text-white text-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                 Payroll Summary
                            </button>
                            
                            @canany(['email all payslips'], [auth()->user()])
                                @if(auth()->user()->hasAnyRole(['System Administrator', 'HR Head', 'HR Staff']))
                                    <button type="button" id="send_all_payslips" class="inline-flex items-center px-4 h-10 bg-purple-600 border border-transparent rounded-md text-white text-sm hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500 transition-colors" style="display: none;">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        Send All Payslips
                                    </button>
                                @endif
                            @endcanany
                        </div>
                        
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3 mb-6">
                <!-- Total Net Pay -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Net Pay</dt>
                                    <dd class="text-lg font-medium text-gray-900">₱{{ number_format($summaryStats['total_net_pay'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Deductions -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Deductions</dt>
                                    <dd class="text-lg font-medium text-gray-900">₱{{ number_format($summaryStats['total_deductions'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Total Gross Pay -->
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Gross Pay</dt>
                                    <dd class="text-lg font-medium text-gray-900">₱{{ number_format($summaryStats['total_gross_pay'] ?? 0, 2) }}</dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payrolls Table -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">Payrolls</h3>
                        <div class="text-sm text-gray-600">
                            <div>Showing {{ $payrolls->count() }} of {{ $payrolls->total() }} payrolls</div>
                            <div class="text-xs text-blue-600 mt-1">
                                <strong>Tip:</strong> Right-click on any payroll row to access View, Edit, Process, and Delete actions.
                            </div>
                        </div>
                    </div>

                    <div id="payroll-list-container">
                        @include('payrolls.partials.payroll-list', ['payrolls' => $payrolls])
                    </div>

                    <div id="pagination-container">
                        @include('payrolls.partials.pagination', ['payrolls' => $payrolls])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Context Menu -->
    <div id="contextMenu" class="fixed bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 hidden min-w-52 backdrop-blur-sm transition-all duration-150 transform opacity-0 scale-95">
        <div id="contextMenuHeader" class="px-3 py-2 border-b border-gray-100 bg-gray-50 rounded-t-md">
            <div class="text-sm font-medium text-gray-900" id="contextMenuPayroll"></div>
            <div class="text-xs text-gray-500" id="contextMenuPeriod"></div>
        </div>
        <div class="py-1">
            <a href="#" id="contextMenuView" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                View Details
            </a>
            <a href="#" id="contextMenuEdit" class="flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150" style="display: none;">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Payroll
            </a>
            <a href="#" id="contextMenuProcess" class="flex items-center px-3 py-2 text-sm text-green-600 hover:bg-green-50 hover:text-green-700 transition-colors duration-150" style="display: none;">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Process Payroll
            </a>
            <a href="#" id="contextMenuApprove" class="flex items-center px-3 py-2 text-sm text-purple-600 hover:bg-purple-50 hover:text-purple-700 transition-colors duration-150" style="display: none;">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Approve Payroll
            </a>
            <a href="#" id="contextMenuDownloadPayslip" class="flex items-center px-3 py-2 text-sm text-green-600 hover:bg-green-50 hover:text-green-700 transition-colors duration-150" style="display: none;">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                Download Payslip
            </a>
            <a href="#" id="contextMenuSendPayroll" class="flex items-center px-3 py-2 text-sm text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 transition-colors duration-150" style="display: none;">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
                <div>
                    <div id="contextMenuSendText">Send Payslip</div>
                    <div class="text-xs text-gray-500" id="contextMenuSendStatus"></div>
                </div>
            </a>
            <div class="border-t border-gray-100 my-1"></div>
            <a href="#" id="contextMenuDelete" class="flex items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors duration-150">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete Payroll
            </a>
        </div>
    </div>

    <script>
        let contextMenu = document.getElementById('contextMenu');
        let currentPayrollId = null;
        let currentPayrollStatus = null;
        let currentEmployeeId = null;
        let currentEmployeeName = '';
        
        // Hide context menu when clicking outside
        document.addEventListener('click', function(event) {
            contextMenu.classList.add('hidden');
            contextMenu.classList.remove('opacity-100', 'scale-100');
            contextMenu.classList.add('opacity-0', 'scale-95');
        });
        
        function showContextMenu(event, payrollId, payrollNumber, period, status, payrollType, paySchedule, employeeId, employeeName = '', sendStatus = '', sendDetails = '') {
            event.preventDefault();
            event.stopPropagation();
            
            // Context menu called for payroll
            
            currentPayrollId = payrollId;
            currentPayrollStatus = status;
            currentEmployeeId = employeeId;
            currentEmployeeName = employeeName || 'employee';
            
            // Update header info
            document.getElementById('contextMenuPayroll').textContent = payrollNumber;
            document.getElementById('contextMenuPeriod').textContent = period;
            
            // Set up action URLs
            let baseUrl = '{{ route("payrolls.index") }}';
            
            // Use automation URL pattern with payroll ID for all payroll types
            document.getElementById('contextMenuView').href = '{{ url("/payrolls/automation") }}/' + paySchedule + '/' + payrollId;
            
            document.getElementById('contextMenuEdit').href = baseUrl + '/' + payrollId + '/edit';
            
            // Get current send status from row data attributes (for real-time updates)
            const payrollRow = document.getElementById(`payroll-row-${payrollId}`);
            const currentSendStatus = payrollRow ? payrollRow.getAttribute('data-send-status') : sendStatus;
            const currentSendDetails = payrollRow ? payrollRow.getAttribute('data-send-details') : sendDetails;
            
            // Update send payslip context menu info
            updateSendPayslipContextMenu(currentSendStatus, currentSendDetails);

            // Show/hide actions based on status and permissions
            showHideContextMenuItems(status);
            
            // Position and show menu
            let x = event.clientX;
            let y = event.clientY;
            
            // Position context menu
            
            // Adjust position if menu would go off screen
            let menuWidth = 208; // min-w-52 = 13rem = 208px
            let menuHeight = 280; // approximate height
            
            if (x + menuWidth > window.innerWidth) {
                x = window.innerWidth - menuWidth - 10;
            }
            
            if (y + menuHeight > window.innerHeight) {
                y = window.innerHeight - menuHeight - 10;
            }
            
            contextMenu.style.left = x + 'px';
            contextMenu.style.top = y + 'px';
            contextMenu.classList.remove('hidden');
            
            // Menu is now visible
            
            // Animate in
            setTimeout(() => {
                contextMenu.classList.remove('opacity-0', 'scale-95');
                contextMenu.classList.add('opacity-100', 'scale-100');
            }, 10);
        }
        
        function showHideContextMenuItems(status) {
            // Setting menu items based on status
            
            // Reset all items to hidden
            document.getElementById('contextMenuEdit').style.display = 'none';
            document.getElementById('contextMenuProcess').style.display = 'none';
            document.getElementById('contextMenuApprove').style.display = 'none';
            document.getElementById('contextMenuDownloadPayslip').style.display = 'none';
            document.getElementById('contextMenuSendPayroll').style.display = 'none';
            document.getElementById('contextMenuDelete').style.display = 'none';
            
            // Show Edit if payroll can be edited and user has permission
            @can('edit payrolls')
            if (status === 'draft') {
                document.getElementById('contextMenuEdit').style.display = 'flex';
            }
            @endcan
            
            // Show Process if payroll is draft and user has permission
            @can('process payrolls')
            if (status === 'draft') {
                document.getElementById('contextMenuProcess').style.display = 'flex';
            }
            @endcan
            
            // Show Approve if payroll is processing and user has permission (not HR Staff)
            @can('approve payrolls')
            @if(!auth()->user()->hasRole('HR Staff'))
            if (status === 'processing') {
                document.getElementById('contextMenuApprove').style.display = 'flex';
            }
            @endif
            @endcan
            
            // Show Download Payslip if payroll is approved and has single employee and user has permission
            @can('download payslips')
                if (status === 'approved' && currentEmployeeId) {
                    document.getElementById('contextMenuDownloadPayslip').style.display = 'flex';
                }
            @endcan

            // Show Send Payslip if payroll is approved and user has permission and proper role
            @canany(['email all payslips'], [auth()->user()])
                @if(auth()->user()->hasAnyRole(['System Administrator', 'HR Head', 'HR Staff']))
                    if (status === 'approved') {
                        document.getElementById('contextMenuSendPayroll').style.display = 'flex';
                    }
                @endif
            @endcanany
            
            // Show Delete if user has permission - only for pending/processing
            @can('delete payrolls')
            if (status === 'draft' || status === 'processing') {
                document.getElementById('contextMenuDelete').style.display = 'flex';
            }
            @endcan
            
            // Show Delete for approved payrolls if user has special permission
            @can('delete approved payrolls')
            if (status === 'approved') {
                document.getElementById('contextMenuDelete').style.display = 'flex';
            }
            @endcan
        }

        function updateSendPayslipContextMenu(sendStatus, sendDetails) {
            const sendTextElement = document.getElementById('contextMenuSendText');
            const sendStatusElement = document.getElementById('contextMenuSendStatus');
            
            if (sendStatus === 'All Sent') {
                sendTextElement.textContent = 'Resend Payslip';
                sendStatusElement.textContent = sendDetails;
                sendStatusElement.className = 'text-xs text-gray-500';
            } else {
                sendTextElement.textContent = 'Send Payslip';
                sendStatusElement.textContent = '';
                sendStatusElement.className = 'text-xs text-gray-500';
            }
        }
        
        // Handle process action
        document.getElementById('contextMenuProcess').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Submit this payroll for processing?')) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("payrolls.index") }}/' + currentPayrollId + '/process';
                
                let csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        // Handle approve action
        document.getElementById('contextMenuApprove').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Approve this payroll?')) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("payrolls.index") }}/' + currentPayrollId + '/approve';
                
                let csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        // Handle send payslip action
        document.getElementById('contextMenuSendPayroll').addEventListener('click', function(e) {
            e.preventDefault();
            const employeeName = currentEmployeeName || 'this employee';
            if (confirm(`Send payslip to ${employeeName} via email?`)) {
                showLoading('Sending Payslip...', `Sending payslip to ${employeeName}. Please wait while we generate and send the PDF.`);
                
                // Use individual email route with optional employee_id parameter
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                
                // If we have a specific employee ID from context, add it
                if (currentEmployeeId) {
                    formData.append('employee_id', currentEmployeeId);
                }
                
                fetch('{{ url("/payrolls") }}/' + currentPayrollId + '/email-individual', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    
                    // Small delay to ensure loading overlay is fully hidden before alert
                    setTimeout(() => {
                        if (data.success) {
                            alert('Payslip sent successfully!');
                            
                            // Create timestamp
                            const timestamp = 'Sent: ' + new Date().toLocaleDateString('en-US', {
                                month: 'short',
                                day: 'numeric',
                                hour: 'numeric',
                                minute: '2-digit',
                                hour12: true
                            });
                            
                            // Update context menu to show "Resend" status
                            updateSendPayslipContextMenu('All Sent', timestamp);
                            
                            // Update row data attributes for persistent changes
                            const payrollRow = document.getElementById(`payroll-row-${currentPayrollId}`);
                            if (payrollRow) {
                                payrollRow.setAttribute('data-send-status', 'All Sent');
                                payrollRow.setAttribute('data-send-details', timestamp);
                            }
                        } else {
                            alert('Error sending payslip: ' + (data.message || 'Unknown error'));
                        }
                    }, 100);
                })
                .catch(error => {
                    hideLoading();
                    
                    // Small delay to ensure loading overlay is fully hidden before alert
                    setTimeout(() => {
                        alert('Error sending payslip. Please try again.');
                    }, 100);
                    console.error('Error:', error);
                });
            }
        });
        
        // Handle download payslip action
        document.getElementById('contextMenuDownloadPayslip').addEventListener('click', function(e) {
            e.preventDefault();
            if (currentPayrollId && currentEmployeeId) {
                // Use the same route as individual payslip view: payrolls.payslip.download
                window.location.href = '{{ url("/payrolls") }}/' + currentPayrollId + '/payslip/download';
            } else {
                alert('Download is only available for single-employee payrolls.');
            }
            hideContextMenu();
        });

        
        // Handle delete action
        document.getElementById('contextMenuDelete').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this payroll? This action cannot be undone.')) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("payrolls.index") }}/' + currentPayrollId;
                
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

        // Live filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            const filterSelects = document.querySelectorAll('.payroll-filter');
            const payScheduleSelect = document.getElementById('pay_schedule');
            const payPeriodSelect = document.getElementById('pay_period');

            // Function to update pay periods based on selected schedule
            function updatePayPeriods(schedule) {
                // Clear existing options
                payPeriodSelect.innerHTML = '<option value="">All Periods</option>';
                
                if (!schedule) return;

                // Fetch pay periods for the selected schedule
                fetch(`{{ route('payrolls.index') }}?action=get_periods&schedule=${schedule}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('Response is not JSON');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data && data.periods && Array.isArray(data.periods)) {
                        data.periods.forEach(period => {
                            const option = document.createElement('option');
                            option.value = period.value;
                            option.textContent = period.label;
                            payPeriodSelect.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching periods:', error);
                    // Reset to default state on error
                    payPeriodSelect.innerHTML = '<option value="">All Periods</option>';
                });
            }

            // Function to apply filters via AJAX (no page reload)
            function applyFilters() {
                const url = new URL(window.location.origin + window.location.pathname);
                const params = new URLSearchParams();
                
                // Keep any existing parameters except page
                const currentParams = new URLSearchParams(window.location.search);
                filterSelects.forEach(select => {
                    if (select.value) {
                        params.set(select.name, select.value);
                    }
                });

                // Copy over existing parameters that aren't filters
                for (const [key, value] of currentParams) {
                    if (!['name_search', 'pay_schedule', 'status', 'type', 'pay_period', 'page'].includes(key)) {
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
                .then(response => {
                    // Check if response is ok and content type is JSON
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        // If response is not JSON, reload the page to prevent corruption
                        console.warn('Received non-JSON response, reloading page to prevent corruption');
                        window.location.reload();
                        return;
                    }
                    
                    return response.json();
                })
                .then(data => {
                    if (data && typeof data === 'object' && data.html && data.pagination) {
                        // Update payroll list only if we have valid data
                        const listContainer = document.getElementById('payroll-list-container');
                        const paginationContainer = document.getElementById('pagination-container');
                        
                        if (listContainer && paginationContainer) {
                            listContainer.innerHTML = data.html;
                            paginationContainer.innerHTML = data.pagination;
                        }
                    } else {
                        console.error('Invalid data format received:', data);
                        // Reload page if data format is invalid
                        window.location.reload();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // On any error, reload the page to ensure clean state
                    window.location.reload();
                });
            }

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

            // Add event listeners for live filtering
            filterSelects.forEach(select => {
                if (select.type === 'text') {
                    // Text inputs use debounced 'input' event for live typing
                    select.addEventListener('input', debounce(applyFilters, 500));
                } else {
                    // Select and date inputs use 'change' event
                    select.addEventListener('change', function() {
                        if (this.id === 'pay_schedule') {
                            // For pay schedule changes, first update periods then apply filters
                            updatePayPeriods(this.value);
                            // Apply filters after a short delay to allow periods to load
                            setTimeout(() => {
                                applyFilters();
                            }, 300);
                        } else {
                            // For other filters, apply immediately
                            applyFilters();
                        }
                    });
                }
            });

            // Initialize pay periods on page load
            if (payScheduleSelect.value) {
                updatePayPeriods(payScheduleSelect.value);
                // Set the selected pay period if it exists in URL
                const urlParams = new URLSearchParams(window.location.search);
                const selectedPeriod = urlParams.get('pay_period');
                if (selectedPeriod) {
                    setTimeout(() => {
                        payPeriodSelect.value = selectedPeriod;
                    }, 500); // Wait for periods to load
                }
            }

            // Generate Payroll Summary functionality
            document.getElementById('generate_summary').addEventListener('click', function() {
                // Show the export modal
                document.getElementById('exportModal').classList.remove('hidden');
            });

            // Modal functionality
            document.getElementById('closeModal').addEventListener('click', function() {
                document.getElementById('exportModal').classList.add('hidden');
            });

            // Close modal when clicking outside
            document.getElementById('exportModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.add('hidden');
                }
            });

            // PDF Export
            document.getElementById('exportPDF').addEventListener('click', function() {
                generateSummary('pdf');
                document.getElementById('exportModal').classList.add('hidden');
            });

            // Excel Export
            document.getElementById('exportExcel').addEventListener('click', function() {
                generateSummary('excel');
                document.getElementById('exportModal').classList.add('hidden');
            });

            // Function to generate summary
            function generateSummary(format) {
                const currentFilters = new URLSearchParams(window.location.search);
                
                // Add export format to parameters
                currentFilters.set('export', format);
                currentFilters.set('action', 'generate_summary');

                // Create form and submit for file download
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("payrolls.generate-summary") }}';
                
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

            // Reset filters functionality
            document.getElementById('reset_filters').addEventListener('click', function() {
                window.location.href = '{{ route("payrolls.index") }}';
            });

            // Handle per page selection
            const perPageSelect = document.getElementById('per_page');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', function() {
                    applyFilters(); // Use AJAX instead of page reload
                });
            }

            // Show/hide "Send All Payslips" button based on status filter
            function toggleSendAllButton() {
                const statusSelect = document.getElementById('status');
                const sendAllButton = document.getElementById('send_all_payslips');
                
                if (statusSelect && sendAllButton) {
                    if (statusSelect.value === 'approved') {
                        sendAllButton.style.display = 'inline-flex';
                    } else {
                        sendAllButton.style.display = 'none';
                    }
                }
            }

            // Initial check for send all button visibility
            toggleSendAllButton();

            // Listen for status filter changes
            document.getElementById('status').addEventListener('change', toggleSendAllButton);

            // Handle Send All Payslips button click
            document.getElementById('send_all_payslips').addEventListener('click', function() {
                if (confirm('Send payslips to ALL employees with approved payrolls based on current filters? This action will email PDF payslips to all matching employees.')) {
                    // Get current filter values
                    const formData = new FormData();
                    
                    // Add current filter parameters
                    const nameSearch = document.getElementById('name_search').value;
                    const paySchedule = document.getElementById('pay_schedule').value;
                    const status = document.getElementById('status').value;
                    const type = document.getElementById('type').value;
                    const payPeriod = document.getElementById('pay_period').value;
                    
                    if (nameSearch) formData.append('name_search', nameSearch);
                    if (paySchedule) formData.append('pay_schedule', paySchedule);
                    if (status) formData.append('status', status);
                    if (type) formData.append('type', type);
                    if (payPeriod) formData.append('pay_period', payPeriod);
                    
                    // Add CSRF token
                    formData.append('_token', '{{ csrf_token() }}');

                    // Show loading overlay instead of just button state
                    showLoading('Sending All Payslips...', 'Sending payslips to all employees with approved payrolls. This may take several minutes depending on the number of employees.');
                    
                    fetch('{{ route("payslips.bulk-email-approved") }}', {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        
                        // Small delay to ensure loading overlay is fully hidden before alert
                        setTimeout(() => {
                            if (data.success) {
                                alert(`Success! ${data.message}`);
                                if (data.errors && data.errors.length > 0) {
                                    console.warn('Some emails failed:', data.errors);
                                }
                            } else {
                                alert('Error: ' + (data.message || 'Unknown error occurred'));
                            }
                        }, 100);
                    })
                    .catch(error => {
                        hideLoading();
                        
                        // Small delay to ensure loading overlay is fully hidden before alert
                        setTimeout(() => {
                            alert('Failed to send payslips. Please try again.');
                        }, 100);
                        console.error('Error:', error);
                    });
                }
            });
        });
        
        // Loading helper functions
        function showLoading(title = 'Processing...', message = 'Please wait while we process your request.') {
            const loadingText = document.getElementById('loadingText');
            const loadingSubtext = document.getElementById('loadingSubtext');
            const loadingOverlay = document.getElementById('loadingOverlay');
            
            if (loadingText) loadingText.textContent = title;
            if (loadingSubtext) loadingSubtext.textContent = message;
            if (loadingOverlay) {
                loadingOverlay.style.display = 'flex';
                loadingOverlay.style.opacity = '1';
            }
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        }
        
        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
                overlay.style.opacity = '0';
            }
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
        
        // Global error handler to prevent display corruption
        window.addEventListener('error', function(event) {
            console.error('JavaScript error detected:', event.error);
            // Don't reload automatically, just log the error
        });
        
        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled promise rejection:', event.reason);
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
                        Select the format for your payroll summary export:
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
