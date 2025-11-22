<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('All Payrolls') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('payrolls.automation.index') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                    Automate Payroll
                </a>

            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 border-b border-gray-200">
                    <form method="GET" action="{{ route('payrolls.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label for="schedule" class="block text-sm font-medium text-gray-700 mb-1">Pay Schedule</label>
                            <select name="schedule" id="schedule" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Schedules</option>
                                @foreach($scheduleSettings as $schedule)
                                    <option value="{{ $schedule->code }}" {{ request('schedule') == $schedule->code ? 'selected' : '' }}>
                                        {{ $schedule->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="status" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Statuses</option>
                                <option value="draft" {{ request('status') == 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="processed" {{ request('status') == 'processed' ? 'selected' : '' }}>Processed</option>
                            </select>
                        </div>

                        <div>
                            <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="type" id="type" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">All Types</option>
                                <option value="regular" {{ request('type') == 'regular' ? 'selected' : '' }}>Regular</option>
                                <option value="automated" {{ request('type') == 'automated' ? 'selected' : '' }}>Automated</option>
                                <option value="manual" {{ request('type') == 'manual' ? 'selected' : '' }}>Manual</option>
                            </select>
                        </div>

                        <div>
                            <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date From</label>
                            <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div>
                            <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date To</label>
                            <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                                   class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>

                        <div class="md:col-span-5 flex items-end">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded mr-2">
                                Filter
                            </button>
                            <a href="{{ route('payrolls.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Clear
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Payrolls List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">
                            Payrolls 
                            <span class="text-sm font-normal text-gray-500">
                                (Showing {{ $payrolls->count() }} of {{ $payrolls->total() }} payrolls)
                            </span>
                        </h3>
                        <div class="text-sm text-gray-600">
                            <div class="text-xs text-blue-600">
                                <strong>Tip:</strong> Right-click on any payroll row to access View, Edit, Process, and Delete actions.
                            </div>
                        </div>
                    </div>

                    @if($payrolls->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payroll</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Schedule</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Net</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($payrolls as $payroll)
                                        <tr class="hover:bg-gray-50 cursor-pointer transition-colors duration-150" 
                                           oncontextmenu="showContextMenu(event, '{{ $payroll->id }}', '{{ $payroll->payroll_number }}', '{{ \Carbon\Carbon::parse($payroll->period_start)->format('M d') }} - {{ \Carbon\Carbon::parse($payroll->period_end)->format('M d, Y') }}', '{{ $payroll->status }}', '{{ $payroll->payroll_type }}', '{{ $payroll->pay_schedule }}', '{{ $payroll->payrollDetails->count() === 1 ? $payroll->payrollDetails->first()->employee_id : '' }}')"
                                           onclick="window.location.href='@if($payroll->payroll_type === 'automated' && $payroll->payrollDetails->count() === 1)@if($payroll->status === 'draft'){{ route('payrolls.automation.draft', ['id' => $payroll->id]) }}@elseif(in_array($payroll->status, ['processing', 'pending'])){{ route('payrolls.automation.locked', ['id' => $payroll->id]) }}@elseif($payroll->status === 'approved'){{ route('payrolls.automation.approved', ['id' => $payroll->id]) }}@elseif($payroll->is_paid){{ route('payrolls.automation.paid', ['id' => $payroll->id]) }}@else{{ route('payrolls.automation.draft', ['id' => $payroll->id]) }}@endif@else{{ route('payrolls.show', $payroll) }}@endif'"
                                           title="Right-click for actions">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">{{ $payroll->payroll_number }}</div>
                                                <div class="text-sm text-gray-500">{{ $payroll->created_at->format('M d, Y') }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    {{ \Carbon\Carbon::parse($payroll->period_start)->format('M d') }} - 
                                                    {{ \Carbon\Carbon::parse($payroll->period_end)->format('M d, Y') }}
                                                </div>
                                                <div class="text-sm text-gray-500">Pay: {{ \Carbon\Carbon::parse($payroll->pay_date)->format('M d, Y') }}</div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    {{ $payroll->pay_schedule === 'weekly' ? 'bg-blue-100 text-blue-800' : '' }}
                                                    {{ $payroll->pay_schedule === 'semi_monthly' ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $payroll->pay_schedule === 'monthly' ? 'bg-purple-100 text-purple-800' : '' }}">
                                                    {{ ucfirst(str_replace('_', ' ', $payroll->pay_schedule)) }}
                                                </span>
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
                                                ₱{{ number_format($payroll->total_net, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    {{ $payroll->status === 'draft' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                    {{ $payroll->status === 'approved' ? 'bg-blue-100 text-blue-800' : '' }}
                                                    {{ $payroll->status === 'processed' ? 'bg-green-100 text-green-800' : '' }}">
                                                    {{ ucfirst($payroll->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    {{ $payroll->payroll_type === 'regular' ? 'bg-gray-100 text-gray-800' : '' }}
                                                    {{ $payroll->payroll_type === 'automated' ? 'bg-blue-100 text-blue-800' : '' }}
                                                    {{ $payroll->payroll_type === 'manual' ? 'bg-green-100 text-green-800' : '' }}">
                                                    {{ ucfirst($payroll->payroll_type ?? 'Regular') }}
                                                </span>
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
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 48 48">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                      d="M9 12h6m-6 4h6m2 5l7-7 7 7M9 20h6m-7 4h7m6-4V8a2 2 0 012-2h6a2 2 0 012 2v4m-3 4a2 2 0 01-2 2H9a2 2 0 01-2-2v-4z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">No payrolls found</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                No payrolls match your current filter criteria. Try adjusting your filters or create a new payroll.
                            </p>
                            <div class="mt-6 flex justify-center space-x-4">
                                <a href="{{ route('payrolls.automation.index') }}" 
                                   class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    Create Automated Payroll
                                </a>

                            </div>
                        </div>
                    @endif
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
        
        // Hide context menu when clicking outside
        document.addEventListener('click', function(event) {
            contextMenu.classList.add('hidden');
            contextMenu.classList.remove('opacity-100', 'scale-100');
            contextMenu.classList.add('opacity-0', 'scale-95');
        });

        function showContextMenu(event, payrollId, payrollNumber, period, status, payrollType, paySchedule, employeeId) {
            event.preventDefault();
            event.stopPropagation();
            
            currentPayrollId = payrollId;
            currentPayrollStatus = status;
            
            // Update header info
            document.getElementById('contextMenuPayroll').textContent = payrollNumber;
            document.getElementById('contextMenuPeriod').textContent = period;
            
            // Set up action URLs
            let baseUrl = '{{ url("/payrolls") }}';
            
            // Use new automation URL for automated payrolls with single employee
            if (payrollType === 'automated' && employeeId) {
                document.getElementById('contextMenuView').href = '{{ url("/payrolls/automation") }}/' + paySchedule + '/' + employeeId;
            } else {
                document.getElementById('contextMenuView').href = baseUrl + '/' + payrollId;
            }
            
            document.getElementById('contextMenuEdit').href = baseUrl + '/' + payrollId + '/edit';
            
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
            document.getElementById('contextMenuEdit').style.display = 'none';
            document.getElementById('contextMenuProcess').style.display = 'none';
            document.getElementById('contextMenuApprove').style.display = 'none';
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
            
            // Show Delete if user has permission
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
        
        // Handle delete action
        document.getElementById('contextMenuDelete').addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this payroll? This action cannot be undone.')) {
                let form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ url("/payrolls") }}/' + currentPayrollId;
                
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
    </script>
</x-app-layout>
