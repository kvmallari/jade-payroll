<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Automated Payroll') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">Automatically include all active employees for the selected pay schedule</p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('payrolls.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    View All Payrolls
                </a>

            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-6 flex flex-row items-center justify-between gap-4">
                        <h3 class="text-lg font-medium text-gray-900">Select Pay Frequency</h3>
                        <div class="flex items-center">
                            <div class="bg-blue-100 border border-blue-300 rounded-lg px-8 py-2 text-right">
                                <span class="text-lg font-bold text-blue-800">{{ now()->format('F d, Y') }}</span>
                                <div class="text-xs text-blue-600 text-center">{{ now()->format('l') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Pay Frequency Selection -->
                    <div class="flex justify-center">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-6xl">
                        @php
                            $frequencyTypes = ['weekly', 'semi_monthly', 'monthly'];
                            $frequencyLabels = [
                                'weekly' => 'Weekly', 
                                'semi_monthly' => 'Semi-Monthly',
                                'monthly' => 'Monthly'
                            ];
                        @endphp
                        
                        @foreach($frequencyTypes as $type)
                            @php
                                $schedulesForType = collect($schedulesByType[$type] ?? [])->where('is_active', true);
                                $totalEmployees = $schedulesForType->sum('active_employees_count');
                                $scheduleCount = $schedulesForType->count();
                            @endphp
                            
                            @if($scheduleCount > 0)
                                @php
                                    $cardColors = [
                                        'weekly' => [
                                            'border' => 'border-green-200',
                                            'hover_border' => 'hover:border-green-400',
                                            'hover_bg' => 'hover:bg-green-50',
                                            'shadow' => 'hover:shadow-green-200/50',
                                            'gradient' => 'bg-gradient-to-br from-green-50 to-emerald-100'
                                        ],
                                        'semi_monthly' => [
                                            'border' => 'border-blue-200',
                                            'hover_border' => 'hover:border-blue-400',
                                            'hover_bg' => 'hover:bg-blue-50',
                                            'shadow' => 'hover:shadow-blue-200/50',
                                            'gradient' => 'bg-gradient-to-br from-blue-50 to-indigo-100'
                                        ],
                                        'monthly' => [
                                            'border' => 'border-purple-200',
                                            'hover_border' => 'hover:border-purple-400',
                                            'hover_bg' => 'hover:bg-purple-50',
                                            'shadow' => 'hover:shadow-purple-200/50',
                                            'gradient' => 'bg-gradient-to-br from-purple-50 to-violet-100'
                                        ]
                                    ];
                                    $colors = $cardColors[$type];
                                @endphp
                                <a href="{{ route('payrolls.automation.schedules', ['frequency' => $type]) }}" 
                                   class="block border-2 {{ $colors['border'] }} {{ $colors['hover_border'] }} {{ $colors['gradient'] }} rounded-xl {{ $colors['hover_bg'] }} hover:shadow-xl {{ $colors['shadow'] }} transition-all duration-300 cursor-pointer transform hover:scale-105 hover:-translate-y-1 h-80 min-h-80 max-h-80 w-full">
                                    <div class="py-6 px-8 h-full flex flex-col relative overflow-hidden">
                                        
                                        <!-- Header with Type Badge -->
                                        <div class="flex items-center justify-between mb-4 relative z-10">
                                            <h4 class="text-lg font-semibold text-gray-900">{{ $frequencyLabels[$type] }}</h4>
                                            {{-- <span class="px-3 py-1 text-xs font-medium rounded-full shadow-sm
                                                {{ $type === 'weekly' ? 'bg-green-500 text-white' : '' }}
                                                {{ $type === 'semi_monthly' ? 'bg-blue-500 text-white' : '' }}
                                                {{ $type === 'monthly' ? 'bg-purple-500 text-white' : '' }}">
                                                {{ ucfirst(str_replace('_', ' ', $type)) }}
                                            </span> --}}
                                        </div>

                                        <!-- Statistics -->
                                        <div class="mb-4 relative z-10">
                                            <div class="flex items-center text-sm text-gray-700 mb-2">
                                                <svg class="w-4 h-4 mr-2 {{ $type === 'weekly' ? 'text-green-600' : '' }}{{ $type === 'semi_monthly' ? 'text-blue-600' : '' }}{{ $type === 'monthly' ? 'text-purple-600' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                                </svg>
                                                Active Schedules:
                                                <span class="font-semibold ml-1 {{ $type === 'weekly' ? 'text-green-700' : '' }}{{ $type === 'semi_monthly' ? 'text-blue-700' : '' }}{{ $type === 'monthly' ? 'text-purple-700' : '' }}">{{ $scheduleCount }}</span>
                                            </div>

                                            <div class="flex items-center text-sm text-gray-700">
                                                <svg class="w-4 h-4 mr-2 {{ $type === 'weekly' ? 'text-green-600' : '' }}{{ $type === 'semi_monthly' ? 'text-blue-600' : '' }}{{ $type === 'monthly' ? 'text-purple-600' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                                Total Employees:
                                                <span class="font-semibold ml-1 {{ $type === 'weekly' ? 'text-green-700' : '' }}{{ $type === 'semi_monthly' ? 'text-blue-700' : '' }}{{ $type === 'monthly' ? 'text-purple-700' : '' }}">{{ $totalEmployees }}</span>
                                            </div>
                                        </div>

                                        <!-- Information Box -->
                                        <div class="bg-white/60 backdrop-blur-sm border border-white/20 rounded-lg p-4 mb-4 flex-grow relative z-10 shadow-sm">
                                            <h5 class="text-sm font-semibold text-gray-900 mb-2 flex items-center">
                                                <span class="w-2 h-2 rounded-full mr-2 {{ $type === 'weekly' ? 'bg-green-500' : '' }}{{ $type === 'semi_monthly' ? 'bg-blue-500' : '' }}{{ $type === 'monthly' ? 'bg-purple-500' : '' }}"></span>
                                                Frequency Overview
                                            </h5>
                                            <div class="text-sm text-gray-700">
                                                <div class="mb-1 font-medium">{{ $scheduleCount }} {{ strtolower($frequencyLabels[$type]) }}</div>
                                                <div class="text-gray-600">covering {{ $totalEmployees }} employee{{ $totalEmployees > 1 ? 's' : '' }}</div>
                                            </div>
                                        </div>

                                        <!-- Action Button -->
                                        <div class="text-center relative z-10">
                                            <div class="inline-flex items-center px-6 py-3 text-sm font-medium rounded-lg shadow-md transition-all duration-200 hover:shadow-lg transform hover:scale-105
                                                {{ $type === 'weekly' ? 'text-white bg-green-600 hover:bg-green-700' : '' }}
                                                {{ $type === 'semi_monthly' ? 'text-white bg-blue-600 hover:bg-blue-700' : '' }}
                                                {{ $type === 'monthly' ? 'text-white bg-purple-600 hover:bg-purple-700' : '' }}">
                                                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                </svg>
                                                View {{ $scheduleCount }} Schedule{{ $scheduleCount > 1 ? 's' : '' }}
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endif
                        @endforeach
                        
                        @if(collect($schedulesByType)->flatten()->where('is_active', true)->isEmpty())
                            <!-- Show message when no active schedules exist -->
                            <div class="col-span-full text-center py-12">
                                <div class="max-w-md mx-auto">
                                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M8 7V3a2 2 0 012-2h8a2 2 0 012 2v4m0 0V7a2 2 0 012 2v6.586l6.414 6.414a2 2 0 010 2.828l-3.828 3.828a2 2 0 01-2.828 0L26 19.414V13a2 2 0 012-2V7m0 0V3a2 2 0 012-2h8a2 2 0 012 2v4"/>
                                    </svg>
                                    <h3 class="mt-4 text-lg font-medium text-gray-900">No Active Pay Schedules</h3>
                                    <p class="mt-2 text-sm text-gray-500">
                                        You need to configure and activate pay schedules before creating automated payrolls.
                                    </p>
                                    <div class="mt-8">
                                        @can('manage settings')
                                            <a href="{{ route('settings.pay-schedules.index') }}" 
                                               class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700 transition-colors duration-200">
                                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                                </svg>
                                                Configure Pay Schedules
                                            </a>
                                        @endcan
                                    </div>
                                </div>
                            </div>
                        @endif
                        </div>


                    </div>

                    <!-- Information Panel -->
                    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800">About Automated Payroll</h3>
                                <div class="mt-2 text-sm text-blue-700">
                                    <p>Create automated payrolls organized by pay frequency:</p>
                                    <ul class="list-disc list-inside mt-1 space-y-1">
                                        <li>Select a pay frequency to view available schedules</li>
                                        <li>Each schedule shows employee count and current pay period</li>
                                        <li>Payrolls automatically include all active assigned employees</li>
                                        <li>Pay periods are calculated based on schedule configuration</li>
                                        <li>Perfect for regular, recurring payroll processing</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
