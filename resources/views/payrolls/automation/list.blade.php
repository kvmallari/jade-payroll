<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Automated Payrolls') }} - {{ $selectedSchedule->name }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    View and manage automated payrolls for {{ ucfirst(str_replace('_', ' ', $selectedSchedule->code)) }} schedule
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('payrolls.automation.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Schedules
                </a>
                <a href="{{ route('payrolls.automation.create', ['schedule' => $scheduleCode, 'action' => 'create']) }}" 
                   class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                    </svg>
                    Generate New Payroll
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Payrolls List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Automated Payrolls 
                                <span class="text-sm font-normal text-gray-500">
                                    ({{ $payrolls->total() }} total payrolls)
                                </span>
                            </h3>
                            {{-- @isset($currentPeriod)
                            <p class="text-sm text-blue-600 mt-1">
                                <strong>Current Period:</strong> 
                                {{ \Carbon\Carbon::parse($currentPeriod['start'])->format('M d') }} - 
                                {{ \Carbon\Carbon::parse($currentPeriod['end'])->format('M d, Y') }}
                                (Pay Date: {{ \Carbon\Carbon::parse($currentPeriod['pay_date'])->format('M d, Y') }})
                            </p>
                            @endisset --}}
                        </div>
                        <div class="text-sm text-gray-600">
                            <div class="text-xs text-blue-600">
                                <strong>Tip:</strong> Right-click on any payroll row to access Manage, Process, and Approve actions.
                            </div>
                        </div>
                    </div>

                    @if($payrolls->count() > 0)
                        <!-- Pagination Controls -->
                        <div class="flex justify-between items-center mb-4 px-6 py-3 bg-gray-50 border-b">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-2">
                                    <label for="per_page" class="text-sm font-medium text-gray-700">Records per page:</label>
                                    <select name="per_page" id="per_page" onchange="updatePerPage(this.value)" 
                                            class="border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                                        <option value="10" {{ request('per_page', 10) == 10 ? 'selected' : '' }}>10</option>
                                        <option value="15" {{ request('per_page') == 15 ? 'selected' : '' }}>15</option>
                                        <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                                        <option value="50" {{ request('per_page') == 50 ? 'selected' : '' }}>50</option>
                                        <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                                    </select>
                                </div>
                            </div>
                            <div class="text-sm text-gray-700">
                                Showing {{ $payrolls->firstItem() ?? 0 }} to {{ $payrolls->lastItem() ?? 0 }} of {{ $payrolls->total() }} results
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payroll</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Net</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($payrolls as $payroll)
                                        @php
                                            // Determine the correct route based on whether we're in a period-specific view
                                            $payrollUrl = '';
                                            if ($payroll->payrollDetails->count() == 1) {
                                                if (isset($period) && $period !== 'current') {
                                                    // Use period-specific route
                                                    $payrollUrl = route('payrolls.automation.period.show', [
                                                        'schedule' => $scheduleCode, 
                                                        'period' => $period,
                                                        'id' => $payroll->id
                                                    ]);
                                                } else {
                                                    // Use appropriate route based on context
                                                    if (isset($isLastPayroll) && $isLastPayroll) {
                                                        $payrollUrl = route('payrolls.automation.show', [
                                                            'schedule' => $scheduleCode, 
                                                            'id' => $payroll->payrollDetails->first()->employee_id,
                                                            'from_last_payroll' => 'true'
                                                        ]);
                                                    } else {
                                                        $payrollUrl = route('payrolls.automation.show', [
                                                            'schedule' => $scheduleCode, 
                                                            'id' => $payroll->payrollDetails->first()->employee_id
                                                        ]);
                                                    }
                                                }
                                            } else {
                                                // Multi-employee payroll, use general payroll route
                                                $payrollUrl = route('payrolls.show', $payroll);
                                            }
                                        @endphp
                                        <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150" 
                                           oncontextmenu="showContextMenu(event, '{{ $payroll->id }}', '{{ $payroll->payroll_number }}', '{{ \Carbon\Carbon::parse($payroll->period_start)->format('M d') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('M d, Y') }}', '{{ $payroll->status }}', '{{ $scheduleCode }}', '{{ $payroll->payrollDetails->count() === 1 ? $payroll->payrollDetails->first()->employee_id : "" }}')"
                                           onclick="window.open('{{ $payrollUrl }}', '_blank')"
                                           title="Right-click for actions | Click to view in new tab">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $payroll->payroll_number }}</div>
                                                <div class="text-sm text-gray-500">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                        Automated
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ \Carbon\Carbon::parse($payroll->period_start)->format('M d') }} - 
                                                    {{ \Carbon\Carbon::parse($payroll->period_end)->format('M d, Y') }}
                                                </div>
                                                <div class="text-sm text-gray-500">Pay: {{ \Carbon\Carbon::parse($payroll->pay_date)->format('M d, Y') }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                @if($payroll->payrollDetails->count() > 0)
                                                    @if($payroll->payrollDetails->count() == 1)
                                                        @php $employee = $payroll->payrollDetails->first()->employee @endphp
                                                        <div class="text-sm font-medium text-gray-900">{{ $employee->full_name }}</div>
                                                        <div class="text-sm text-gray-500">{{ $employee->employee_number }}</div>
                                                    @else
                                                        <div class="text-sm font-medium text-gray-900">{{ $payroll->payrollDetails->count() }} employees</div>
                                                        <div class="text-sm text-gray-500">Multiple employees</div>
                                                    @endif
                                                @else
                                                    <div class="text-sm text-gray-500">No employees</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                ₱{{ number_format($payroll->total_net ?? 0, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    {{ $payroll->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                    {{ $payroll->status === 'approved' ? 'bg-blue-100 text-blue-800' : '' }}
                                                    {{ $payroll->status === 'processed' ? 'bg-green-100 text-green-800' : '' }}">
                                                    {{ ucfirst($payroll->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $payroll->created_at->format('M d, Y') }}
                                                <div class="text-xs text-gray-400">{{ $payroll->created_at->format('g:i A') }}</div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $payrolls->links() }}
                        </div>
                    @else
                        <div class="text-center py-8">
                            @if(isset($noActiveEmployees) && $noActiveEmployees)
                                <!-- No active employees for this schedule -->
                                <svg class="mx-auto h-12 w-12 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No Active Employees</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    There are no active employees assigned to the {{ $selectedSchedule->name }} schedule for the current period ({{ \Carbon\Carbon::parse($currentPeriod['start'])->format('M d') }} - {{ \Carbon\Carbon::parse($currentPeriod['end'])->format('M d, Y') }}).
                                </p>
                                <div class="mt-6 space-y-3">
                                    <div class="flex justify-center space-x-4">
                                        <a href="{{ route('employees.index') }}" 
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View Employees
                                        </a>
                                        <a href="{{ route('payrolls.automation.index') }}" 
                                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                            Back to Automation
                                        </a>
                                    </div>
                                    <p class="text-xs text-gray-400">
                                        Add employees to this pay schedule to start creating automated payrolls.
                                    </p>
                                </div>
                            @elseif(isset($allEmployeesHavePayrolls) && $allEmployeesHavePayrolls)
                                <!-- All employees already have payrolls -->
                                <svg class="mx-auto h-12 w-12 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">All employees have payrolls for this period</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    All {{ $totalActiveEmployees }} active employees in the {{ $selectedSchedule->name }} schedule already have payroll records for the current period ({{ \Carbon\Carbon::parse($currentPeriod['start'])->format('M d') }} - {{ \Carbon\Carbon::parse($currentPeriod['end'])->format('M d, Y') }}).
                                </p>
                                <div class="mt-6 space-y-3">
                                    <div class="flex justify-center space-x-4">
                                        <a href="{{ route('payrolls.index') }}" 
                                           class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                            View All Payrolls
                                        </a>
                                        <a href="{{ route('payrolls.automation.index') }}" 
                                           class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                            Back to Automation
                                        </a>
                                    </div>
                                    <p class="text-xs text-gray-400">
                                        To create payrolls for the next period, wait for the current period to end or create manual payrolls.
                                    </p>
                                </div>
                            @else
                                <!-- No automated payrolls created yet -->
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                          d="M9 12h6m-6 4h6m2 5l7-7 7 7M9 20h6m-7 4h7m6-4V8a2 2 0 012-2h6a2 2 0 012 2v4m-3 4a2 2 0 01-2 2H9a2 2 0 01-2-2v-4z"/>
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">No automated payrolls found</h3>
                                <p class="mt-1 text-sm text-gray-500">
                                    No automated payrolls have been created for the {{ $selectedSchedule->name }} schedule yet.
                                </p>
                                <div class="mt-6">
                                    <a href="{{ route('payrolls.automation.create', ['schedule' => $scheduleCode, 'action' => 'create']) }}" 
                                       class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        Generate Your First Automated Payroll
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
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
                Manage Payroll
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
        </div>
    </div>

    <script>
        let contextMenu = document.getElementById('contextMenu');
        let currentPayrollId = null;
        let currentPayrollStatus = null;
        
        // Hide context menu when clicking outside
        document.addEventListener('click', function(event) {
            contextMenu.classList.add('hidden');
            contextMenu.classList.remove('opacity-100', 'scale-100');
            contextMenu.classList.add('opacity-0', 'scale-95');
        });

        function showContextMenu(event, payrollId, payrollNumber, period, status, scheduleCode, employeeId) {
            event.preventDefault();
            event.stopPropagation();
            
            currentPayrollId = payrollId;
            currentPayrollStatus = status;
            
            // Update header info
            document.getElementById('contextMenuPayroll').textContent = payrollNumber;
            document.getElementById('contextMenuPeriod').textContent = period;
            
            // Set up action URLs
            let baseUrl = '{{ url("/payrolls") }}';
            
            // Always use automation URL for automation list context menu
            if (scheduleCode && employeeId) {
                document.getElementById('contextMenuView').href = '{{ url("/payrolls/automation") }}/' + scheduleCode + '/' + employeeId;
            } else {
                document.getElementById('contextMenuView').href = baseUrl + '/' + payrollId;
            }
            
            // Show/hide actions based on status and permissions
            showHideContextMenuItems(status);
            
            // Position and show menu
            let x = event.pageX;
            let y = event.pageY;
            
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
            
            // Animate in
            setTimeout(() => {
                contextMenu.classList.remove('opacity-0', 'scale-95');
                contextMenu.classList.add('opacity-100', 'scale-100');
            }, 10);
        }
        
        function showHideContextMenuItems(status) {
            // Reset all items to hidden
            document.getElementById('contextMenuProcess').style.display = 'none';
            document.getElementById('contextMenuApprove').style.display = 'none';
            
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
        }
        
        // Handle process action
        document.getElementById('contextMenuProcess').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Submit this payroll for processing?')) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("/payrolls") }}/' + currentPayrollId + '/process';
                
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
                form.action = '{{ url("/payrolls") }}/' + currentPayrollId + '/approve';
                
                let csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);
                
                document.body.appendChild(form);
                form.submit();
            }
        });

        // Update per page records
        function updatePerPage(value) {
            const url = new URL(window.location);
            url.searchParams.set('per_page', value);
            url.searchParams.set('page', 1); // Reset to first page
            window.location.href = url.toString();
        }
    </script>
</x-app-layout>
