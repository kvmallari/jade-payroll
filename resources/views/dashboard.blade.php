<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            

            
            <!-- Notifications -->
            @isset($notifications)
                @if(count($notifications) > 0)
                    <div class="mb-6">
                        @foreach($notifications as $notification)
                            @php
                                $colorClass = match($notification['type']) {
                                    'warning' => 'yellow',
                                    'purple' => 'purple',
                                    default => 'blue'
                                };
                            @endphp
                            <div class="bg-{{ $colorClass }}-50 border-l-4 border-{{ $colorClass }}-400 p-4 mb-2">
                                <div class="flex">
                                    <div class="ml-3">
                                        <p class="text-sm text-{{ $colorClass }}-700">
                                            <a href="{{ $notification['link'] ?? '#' }}" class="underline">
                                                {{ $notification['message'] }}
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endisset

            <!-- Main Statistics Cards (Hidden for Employee role) -->
            @if(!Auth::user()->hasRole('Employee'))
                @isset($dashboardData)
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <!-- Employee Statistics -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Total Employees</h3>
                                    <p class="text-2xl font-bold text-blue-600">{{ $dashboardData['employee_stats']['total'] ?? 0 }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="bg-blue-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Active</div>
                                    <div class="text-sm font-bold text-blue-600">{{ $dashboardData['employee_stats']['active'] ?? 0 }}</div>
                                </div>
                                <div class="bg-blue-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Inactive</div>
                                    <div class="text-sm font-bold text-blue-600">{{ $dashboardData['employee_stats']['inactive'] ?? 0 }}</div>
                                </div>
                                <div class="bg-blue-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Terminated</div>
                                    <div class="text-sm font-bold text-blue-600">{{ $dashboardData['employee_stats']['terminated'] ?? 0 }}</div>
                                </div>
                                <div class="bg-blue-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Resigned</div>
                                    <div class="text-sm font-bold text-blue-600">{{ $dashboardData['employee_stats']['resigned'] ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Payroll Statistics -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Total Payrolls</h3>
                                    <p class="text-2xl font-bold text-green-600">{{ $dashboardData['payroll_stats']['total'] ?? 0 }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="bg-green-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Draft</div>
                                    <div class="text-sm font-bold text-green-600">{{ $dashboardData['payroll_stats']['draft'] ?? 0 }}</div>
                                </div>
                                <div class="bg-green-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Processing</div>
                                    <div class="text-sm font-bold text-green-600">{{ $dashboardData['payroll_stats']['processing'] ?? 0 }}</div>
                                </div>
                                <div class="bg-green-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Approved</div>
                                    <div class="text-sm font-bold text-green-600">{{ $dashboardData['payroll_stats']['approved'] ?? 0 }}</div>
                                </div>
                                <div class="bg-green-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Paid</div>
                                    <div class="text-sm font-bold text-green-600">{{ $dashboardData['payroll_stats']['paid'] ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cash Advance Statistics -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-yellow-500 rounded-md flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Cash Advances</h3>
                                    <p class="text-2xl font-bold text-yellow-600">{{ $dashboardData['cash_advance_stats']['total_requests'] ?? 0 }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="bg-yellow-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Pending</div>
                                    <div class="text-sm font-bold text-yellow-600">{{ $dashboardData['cash_advance_stats']['pending_requests'] ?? 0 }}</div>
                                </div>
                                <div class="bg-yellow-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Approved</div>
                                    <div class="text-sm font-bold text-yellow-600">{{ $dashboardData['cash_advance_stats']['approved_requests'] ?? 0 }}</div>
                                </div>
                                <div class="bg-yellow-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Rejected</div>
                                    <div class="text-sm font-bold text-yellow-600">{{ $dashboardData['cash_advance_stats']['rejected_requests'] ?? 0 }}</div>
                                </div>
                                <div class="bg-yellow-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Completed</div>
                                    <div class="text-sm font-bold text-yellow-600">{{ $dashboardData['cash_advance_stats']['completed_requests'] ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Paid Leaves Statistics -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Paid Leaves</h3>
                                    <p class="text-2xl font-bold text-purple-600">{{ $dashboardData['paid_leave_stats']['total'] ?? 0 }}</p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="bg-purple-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Pending</div>
                                    <div class="text-sm font-bold text-purple-600">{{ $dashboardData['paid_leave_stats']['pending'] ?? 0 }}</div>
                                </div>
                                <div class="bg-purple-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Approved</div>
                                    <div class="text-sm font-bold text-purple-600">{{ $dashboardData['paid_leave_stats']['approved'] ?? 0 }}</div>
                                </div>
                                <div class="bg-purple-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Rejected</div>
                                    <div class="text-sm font-bold text-purple-600">{{ $dashboardData['paid_leave_stats']['rejected'] ?? 0 }}</div>
                                </div>
                                <div class="bg-purple-50 rounded p-2">
                                    <div class="font-semibold text-gray-700">Processing</div>
                                    <div class="text-sm font-bold text-purple-600">{{ $dashboardData['paid_leave_stats']['processing'] ?? 0 }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional System Statistics -->
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                    <!-- Active Pay Schedules -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="text-2xl text-blue-500">�</div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Active Pay Schedules</h3>
                                    <p class="text-2xl font-bold text-blue-600">
                                        {{ $dashboardData['other_stats']['active_pay_schedules'] ?? 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Deductions & Tax -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Active Deductions & Tax</h3>
                                    <p class="text-2xl font-bold text-red-600">
                                        {{ $dashboardData['other_stats']['deductions_tax'] ?? 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Allowances & Bonus -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="text-2xl text-green-500">�</div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Active Allowances & Bonus</h3>
                                    <p class="text-2xl font-bold text-green-600">
                                        {{ $dashboardData['other_stats']['allowances_bonus'] ?? 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Holidays -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Active Holidays</h3>
                                    <p class="text-2xl font-bold text-purple-600">
                                        {{ $dashboardData['other_stats']['holidays'] ?? 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Suspensions -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-gray-900">Active Suspensions</h3>
                                    <p class="text-2xl font-bold text-orange-600">
                                        {{ $dashboardData['other_stats']['suspensions'] ?? 0 }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endisset
            @endif

            {{-- <!-- Department Statistics (Hidden for Employee role) -->
            @if(!Auth::user()->hasRole('Employee'))
                @if(count($dashboardData['department_stats'] ?? []) > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0">
                                <div class="text-2xl">🏢</div>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-gray-900">Department Overview</h3>
                                <p class="text-sm text-gray-500">Employee distribution by department</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($dashboardData['department_stats'] as $dept)
                                <div class="bg-gray-50 rounded-lg p-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-semibold text-gray-900">{{ $dept['name'] }}</h4>
                                            <p class="text-sm text-gray-500">{{ $dept['employee_count'] }} employees</p>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-gray-600">
                                                {{ number_format(($dept['employee_count'] / ($dashboardData['employee_stats']['total'] ?? 1)) * 100, 1) }}%
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif

                <!-- Monthly Trends Chart (Hidden for Employee role) -->
                @if(count($dashboardData['monthly_trends'] ?? []) > 0)
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="flex-shrink-0">
                                <div class="text-2xl">📊</div>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-lg font-medium text-gray-900">6-Month Trends</h3>
                                <p class="text-sm text-gray-500">Employee count and payroll amount trends</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            @foreach(array_reverse($dashboardData['monthly_trends']) as $trend)
                                <div class="text-center">
                                    <div class="text-xs text-gray-500 mb-1">{{ $trend['month'] }}</div>
                                    <div class="text-sm font-semibold text-blue-600">{{ $trend['employees'] }} emp</div>
                                    <div class="text-xs text-green-600">₱{{ number_format($trend['payroll'] / 1000, 0) }}K</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                @endif
            @endif --}}

            <!-- Fallback Statistics Cards (Only for non-employee roles) -->
            {{-- @if(!Auth::user()->hasRole('Employee'))
                @isset($stats)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            @foreach($stats as $key => $value)
                                <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                                    <div class="p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <div class="text-2xl text-gray-400">
                                                    @switch($key)
                                                        @case('total_employees')
                                                            👥
                                                            @break
                                                        @case('pending_payrolls')
                                                            ⏰
                                                            @break

                                                        @case('active_payrolls')
                                                            💰
                                                            @break
                                                        @case('monthly_payroll')
                                                            💵
                                                            @break
                                                        @case('my_time_logs')
                                                            ⏰
                                                            @break

                                                        @case('pending_leaves')
                                                            ⏳
                                                            @break
                                                        @case('latest_payroll')
                                                            💰
                                                            @break
                                                        @default
                                                            📊
                                                    @endswitch
                                                </div>
                                            </div>
                                            <div class="ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-sm font-medium text-gray-500 truncate">
                                                        {{ ucwords(str_replace('_', ' ', $key)) }}
                                                    </dt>
                                                    <dd class="flex items-baseline">
                                                        <div class="text-2xl font-semibold text-gray-900">
                                                            @if($key === 'monthly_payroll')
                                                                ₱{{ number_format($value, 2) }}
                                                            @else
                                                                {{ $value }}
                                                            @endif
                                                        </div>
                                                    </dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endisset
                @endif --}}

            <!-- Performance Overview -->
            @isset($topPerformers)
                @if($topPerformers->isNotEmpty())
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top Performers -->
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0">
                                    <div class="text-2xl">🏆</div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-gray-900">Top Performers</h3>
                                    <p class="text-sm text-gray-500">Based on {{ isset($currentMonth) ? $currentMonth->format('F Y') : now()->format('F Y') }} DTR & calculated salary</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                @foreach($topPerformers as $index => $data)
                                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="text-sm font-bold text-green-600 w-6">{{ $index + 1 }}.</div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">{{ $data['employee']->full_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $data['employee']->department->name ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-green-600">₱{{ number_format($data['calculated_salary'], 2) }}</div>
                                        <div class="text-xs text-gray-500">{{ number_format($data['total_hours'], 1) }} hrs</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Least Performers -->
                    @isset($leastPerformers)
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <div class="flex items-center mb-4">
                                <div class="flex-shrink-0">
                                    <div class="text-2xl">📈</div>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-gray-900">Needs Attention</h3>
                                    <p class="text-sm text-gray-500">Employees with lower DTR performance</p>
                                </div>
                            </div>
                            <div class="space-y-3">
                                @foreach($leastPerformers as $index => $data)
                                <div class="flex items-center justify-between p-3 bg-orange-50 rounded-lg">
                                    <div class="flex items-center">
                                        <div class="text-sm font-bold text-orange-600 w-6">{{ $index + 1 }}.</div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">{{ $data['employee']->full_name }}</div>
                                            <div class="text-xs text-gray-500">{{ $data['employee']->department->name ?? 'N/A' }}</div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-sm font-medium text-orange-600">₱{{ number_format($data['calculated_salary'], 2) }}</div>
                                        <div class="text-xs text-gray-500">{{ number_format($data['total_hours'], 1) }} hrs</div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endisset
                </div>
                @endif
            @endisset

            <!-- Quick Actions & Recent Activities -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                
                <!-- Quick Actions -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Quick Actions</h3>
                        <div class="space-y-3">
                            @can('create employees')
                                <a href="{{ route('employees.create') }}" class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                                    <span class="text-blue-600 mr-3">👤</span>
                                    <span class="text-blue-700 font-medium">Add New Employee</span>
                                </a>
                            @endcan
                            
                            @if(Auth::user()->hasRole(['System Administrator', 'HR Head', 'HR Staff']))
                                <a href="{{ url('/payrolls/automation') }}" class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                                    <span class="text-green-600 mr-3">⚙️</span>
                                    <span class="text-green-700 font-medium">Automate Payroll</span>
                                </a>
                                
                                <a href="{{ route('cash-advances.create') }}" class="flex items-center p-3 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                                    <span class="text-yellow-600 mr-3">💵</span>
                                    <span class="text-yellow-700 font-medium">Add Cash Advance</span>
                                </a>
                                
                                <a href="{{ route('paid-leaves.create') }}" class="flex items-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                                    <span class="text-purple-600 mr-3">📅</span>
                                    <span class="text-purple-700 font-medium">Add Paid Leave</span>
                                </a>
                                
                                @if(Auth::user()->isSuperAdmin())
                                    <a href="{{ route('users.create') }}" class="flex items-center p-3 bg-red-50 rounded-lg hover:bg-red-100 transition">
                                        <span class="text-red-600 mr-3">👥</span>
                                        <span class="text-red-700 font-medium">Create New User</span>
                                    </a>
                                @endif
                            @endif
                            
                            {{-- @can('view own time logs')
                                <a href="{{ route('my-time-logs') }}" class="flex items-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                                    <span class="text-purple-600 mr-3">⏰</span>
                                    <span class="text-purple-700 font-medium">My Time Logs</span>
                                </a>
                            @endcan --}}
                            


                            @hasrole('Employee')
                                <a href="{{ route('payrolls.my-payslips') }}" class="flex items-center p-3 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition">
                                    <span class="text-indigo-600 mr-3">�</span>
                                    <span class="text-indigo-700 font-medium">View Payroll</span>
                                </a>
                                
                                <a href="{{ route('cash-advances.index') }}" class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                                    <span class="text-green-600 mr-3">�</span>
                                    <span class="text-green-700 font-medium">View Cash Advance</span>
                                </a>
                                

                            @endhasrole
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Activities</h3>
                        @isset($recentActivities)
                            @if(count($recentActivities) > 0)
                                <div class="space-y-3">
                                    @foreach($recentActivities as $activity)
                                        <div class="flex items-start">
                                            <div class="flex-shrink-0">
                                                <div class="text-sm text-gray-400">
                                                    @switch($activity['type'])
                                                        @case('payroll')
                                                            💰
                                                            @break
                                                        @case('cash_advance')
                                                            🏦
                                                            @break
                                                        @case('employee')
                                                            👤
                                                            @break
                                                        @case('leave')
                                                            📋
                                                            @break
                                                        @case('time_log')
                                                            ⏰
                                                            @break
                                                        @default
                                                            📊
                                                    @endswitch
                                                </div>
                                            </div>
                                            <div class="ml-3 min-w-0 flex-1">
                                                <p class="text-sm text-gray-900">{{ $activity['message'] }}</p>
                                                <p class="text-xs text-gray-500">
                                                    {{ $activity['date']->diffForHumans() }} by {{ $activity['user'] }}
                                                </p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-gray-500 text-sm">No recent activities.</p>
                            @endif
                        @endisset
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
