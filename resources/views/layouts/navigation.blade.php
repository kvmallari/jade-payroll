<nav x-data="{ open: false }" class="bg-white border-b border-gray-100">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    
                    @can('view employees')
                    <div class="relative inline-flex" x-data="{ open: false }">
                        <button @click="open = ! open" 
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none h-16
                                {{ request()->routeIs('employees.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }}">
                            <span>{{ __('Employees') }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute top-full left-1/2 transform -translate-x-1/2 z-[60] mt-4 w-80 rounded-md bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200 overflow-hidden"
                             style="display: none;">
                            <div class="py-2">
                                <a href="{{ route('employees.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">View All Employees</div>
                                        <div class="text-xs text-gray-500 mt-1">Manage existing employees</div>
                                    </div>
                                </a>
                                @can('create employees')
                                <a href="{{ route('employees.create') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-green-50 hover:text-green-800 transition-colors duration-150 border-t border-gray-100">
                                    <svg class="mr-4 h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-green-700">Create New Employee</div>
                                        <div class="text-xs text-green-600 mt-1">Add new team member</div>
                                    </div>
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    @endcan

                    @can('view payrolls')
                    <div class="relative inline-flex" x-data="{ open: false }">
                        <button @click="open = ! open" 
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none h-16
                                {{ request()->routeIs('payrolls.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }}">
                            <span>{{ __('Payroll') }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute top-full left-1/2 transform -translate-x-1/2 z-[60] mt-4 w-80 rounded-md bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200 overflow-hidden"
                             style="display: none;">
                            <div class="py-2">
                                <a href="{{ route('payrolls.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">View All Payrolls</div>
                                        <div class="text-xs text-gray-500 mt-1">View all created payrolls</div>
                                    </div>
                                </a>
                                
                                <a href="{{ route('payrolls.automation.index') }}" 
                                  class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-green-50 hover:text-green-800 transition-colors duration-150 border-t border-gray-100">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-green-700">Automate Payroll</div>
                                        <div class="text-xs text-green-600 mt-1">Auto-create payrolls for active employees</div>
                                    </div>
                                </a>
                                
                                {{-- <a href="{{ route('payrolls.manual.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                    </svg>
                                     <div class="flex-1">
                                        <div class="font-medium text-gray-900">Manual Payroll</div>
                                        <div class="text-xs text-gray-500 mt-1">Manually select employees for payroll</div>
                                    </div> 
                               </a> --}} 


                            </div>
                        </div>
                    </div>
                    @endcan

                    {{-- Payslip navigation for employees --}}
                    @hasrole('Employee')
                    <x-nav-link :href="route('payslips.index')" :active="request()->routeIs('payslips.*')">
                        {{ __('Payslips') }}
                    </x-nav-link>
                    @endhasrole

                    {{-- Cash Advances - Available to all authenticated users --}}
                    <div class="relative inline-flex" x-data="{ open: false }">
                        <button @click="open = ! open" 
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none h-16
                                {{ request()->routeIs('cash-advances.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }}">
                            <span>{{ __('Cash Advances') }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute top-full left-1/2 transform -translate-x-1/2 z-[60] mt-4 w-80 rounded-md bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200 overflow-hidden"
                             style="display: none;">
                            <div class="py-2">
                                <a href="{{ route('cash-advances.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">View Cash Advances</div>
                                        <div class="text-xs text-gray-500 mt-1">View all cash advance requests</div>
                                    </div>
                                </a>
                                
                                @hasanyrole('HR Head|HR Staff|System Administrator')
                                <a href="{{ route('cash-advances.create') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-green-50 hover:text-green-800 transition-colors duration-150 border-t border-gray-100">
                                    <svg class="mr-4 h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-green-700">Add Cash Advance</div>
                                        <div class="text-xs text-green-600 mt-1">Submit a new cash advance request</div>
                                    </div>
                                </a>
                                @endhasanyrole
                            </div>
                        </div>
                    </div>

                    {{-- Paid Leaves - Available to all authenticated users --}}
                    <div class="relative inline-flex" x-data="{ open: false }">
                        <button @click="open = ! open" 
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none h-16
                                {{ request()->routeIs('paid-leaves.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }}">
                            <span>{{ __('Paid Leaves') }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute top-full left-1/2 transform -translate-x-1/2 z-[60] mt-4 w-80 rounded-md bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200 overflow-hidden"
                             style="display: none;">
                            <div class="py-2">
                                <a href="{{ route('paid-leaves.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">View Paid Leaves</div>
                                        <div class="text-xs text-gray-500 mt-1">View all paid leave records</div>
                                    </div>
                                </a>
                                
                                <a href="{{ route('paid-leaves.create') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-green-50 hover:text-green-800 transition-colors duration-150 border-t border-gray-100">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-green-700">Add Paid Leave</div>
                                        <div class="text-xs text-green-600 mt-1">Submit a new paid leave request</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    @can('view time logs')
                    <div class="relative inline-flex" x-data="{ open: false }">
                        <button @click="open = ! open" 
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none h-16
                                {{ request()->routeIs('time-logs.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }}">
                            <span>{{ __('DTR') }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute top-full left-1/2 transform -translate-x-1/2 z-[60] mt-4 w-80 rounded-md bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200 overflow-hidden"
                             style="display: none;">
                            <div class="py-2">
                                @can('import time logs')
                                <a href="{{ route('dtr.import') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-blue-700">Import DTR</div>
                                        <div class="text-xs text-blue-600 mt-1">Bulk import from Excel/CSV</div>
                                    </div>
                                </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                    @endcan

                    @can('view reports')
                    <x-nav-link :href="route('government-forms.index')" :active="request()->routeIs('government-forms.*')" class="whitespace-nowrap">
                        {{ __('Government Forms') }}
                    </x-nav-link>
                    @endcan

                    @can('view reports')
                    <div class="relative inline-flex" x-data="{ open: false }">
                        <button @click="open = ! open" 
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none h-16
                                {{ request()->routeIs('reports.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }}">
                            <span>{{ __('Reports') }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute top-full left-1/2 transform -translate-x-1/2 z-[60] mt-4 w-80 rounded-md bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200 overflow-hidden"
                             style="display: none;">
                            <div class="py-2">
                                <a href="{{ route('reports.employer-shares') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-blue-700">Employer Shares</div>
                                        <div class="text-xs text-blue-600 mt-1">EE & ER contribution totals</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    @endcan

                    @hasrole('System Administrator')
                    <div class="relative inline-flex" x-data="{ open: false }">
                        <button @click="open = ! open" 
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none h-16
                                {{ request()->routeIs('users.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }}">
                            <span>{{ __('Users') }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute top-full left-1/2 transform -translate-x-1/2 z-[60] mt-4 w-80 rounded-md bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200 overflow-hidden"
                             style="display: none;">
                            <div class="py-2">
                                <a href="{{ route('users.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">All Users</div>
                                        <div class="text-xs text-gray-500 mt-1">View and manage all system users</div>
                                    </div>
                                </a>
                                
                                <a href="{{ route('users.create') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-green-50 hover:text-green-800 transition-colors duration-150 border-t border-gray-100">
                                    <svg class="mr-4 h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-green-700">Create New User</div>
                                        <div class="text-xs text-green-600 mt-1">Add HR Head, HR Staff, or System Admin</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    @endhasrole

                    @can('edit settings')
                    <div class="relative inline-flex" x-data="{ open: false }">
                        <button @click="open = ! open" 
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none h-16
                                {{ request()->routeIs('settings.*') || request()->routeIs('system-settings.*') ? 'border-indigo-400 text-gray-900 focus:border-indigo-700' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:text-gray-700 focus:border-gray-300' }}">
                            <span>{{ __('Settings') }}</span>
                            <svg class="ml-1 h-4 w-4 transition-transform duration-200 flex-shrink-0" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 transform scale-95" x-transition:enter-end="opacity-100 transform scale-100"
                             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 transform scale-100"
                             x-transition:leave-end="opacity-0 transform scale-95"
                             class="absolute top-full left-1/2 transform -translate-x-1/2 z-[60] mt-4 w-80 rounded-md bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none border border-gray-200 overflow-hidden"
                             style="display: none;">
                            <div class="py-2">
                                <a href="{{ route('system-settings.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900">System Settings</div>
                                        <div class="text-xs text-gray-500 mt-1">General system configuration</div>
                                    </div>
                                </a>
                                
                                <div class="border-t border-gray-100 my-2"></div>
                                <div class="px-6 py-2">
                                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Employee Configuration</div>
                                </div>
                                
                                <a href="{{ route('settings.employee.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-indigo-700">Employee Settings</div>
                                        <div class="text-xs text-indigo-600 mt-1">Default values & configurations</div>
                                    </div>
                                </a>

                                @hasrole(['System Administrator', 'HR Head'])
                                <a href="{{ route('settings.employer.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-purple-700">Employer Settings</div>
                                        <div class="text-xs text-purple-600 mt-1">Business details & government forms</div>
                                    </div>
                                </a>
                                @endhasrole
                                
                                
                                <div class="border-t border-gray-100 my-2"></div>
                                <div class="px-6 py-2">
                                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Payroll Configuration</div>
                                </div>
                                
                                <a href="{{ route('settings.pay-schedules.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 0V3M3 7h18M5 10h14l-1 7H6l-1-7z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-blue-700">Pay Schedules</div>
                                        <div class="text-xs text-blue-600 mt-1">Weekly, Semi-monthly, Monthly</div>
                                    </div>
                                </a>

                                <a href="{{ route('payroll-rate-configurations.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-indigo-50 hover:text-indigo-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-indigo-700">Rate Multiplier</div>
                                        <div class="text-xs text-indigo-600 mt-1">Configure hourly rate multipliers</div>
                                    </div>
                                </a>
                                
                                <a href="{{ route('settings.deductions.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-red-700">Deductions & Tax</div>
                                        <div class="text-xs text-red-600 mt-1">SSS, PhilHealth, Pag-IBIG, Tax</div>
                                    </div>
                                </a>
                                
                                <a href="{{ route('settings.allowances.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-green-50 hover:text-green-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-green-700">Allowances & Bonus</div>
                                        <div class="text-xs text-green-600 mt-1">Rice, Transportation, 13th Month</div>
                                    </div>
                                </a>
                                
                                <a href="{{ route('settings.leaves.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 0V3M3 7h18M5 10h14l-1 7H6l-1-7z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-purple-700">Paid Leaves</div>
                                        <div class="text-xs text-purple-600 mt-1">VL, SL, ML, PL, EL, BL</div>
                                    </div>
                                </a>
                                
                                <a href="{{ route('settings.holidays.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-orange-50 hover:text-orange-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3a4 4 0 118 0v4m-4 0V3M3 7h18M5 10h14l-1 7H6l-1-7z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-orange-700">Holidays</div>
                                        <div class="text-xs text-orange-600 mt-1">Regular & Special holidays</div>
                                    </div>
                                </a>
                                
                                <a href="{{ route('settings.suspension.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-red-50 hover:text-red-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-red-700">Suspension Settings</div>
                                        <div class="text-xs text-red-600 mt-1">Full day & Partial suspension days</div>
                                    </div>
                                </a>                                <a href="{{ route('settings.time-logs.index') }}" 
                                   class="flex items-center px-6 py-3 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-800 transition-colors duration-150">
                                    <svg class="mr-4 h-5 w-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <div class="flex-1">
                                        <div class="font-medium text-blue-700">Time Schedules</div>
                                        <div class="text-xs text-blue-600 mt-1">Schedule, Break period, Grace period</div>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                    @endcan
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div>Welcome back, {{ Auth::user()->name }}</div>

                            <div class="ms-2">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            
            @can('view employees')
            <x-responsive-nav-link :href="route('employees.index')" :active="request()->routeIs('employees.*')">
                {{ __('View Employees') }}
            </x-responsive-nav-link>
            @can('create employees')
            <x-responsive-nav-link :href="route('employees.create')" :active="request()->routeIs('employees.create')">
                {{ __('Create Employee') }}
            </x-responsive-nav-link>
            @endcan
            @endcan

            @can('view payrolls')
            <x-responsive-nav-link :href="route('payrolls.index')" :active="request()->routeIs('payrolls.index')">
                {{ __('View Payrolls') }}
            </x-responsive-nav-link>
            @endcan

            @can('view time logs')
            <x-responsive-nav-link :href="route('time-logs.index')" :active="request()->routeIs('time-logs.index')">
                {{ __('View DTR') }}
            </x-responsive-nav-link>
            @can('create time logs')
            <x-responsive-nav-link :href="route('time-logs.create')" :active="request()->routeIs('time-logs.create')">
                {{ __('Create DTR') }}
            </x-responsive-nav-link>
            @endcan
            @endcan

            @can('view reports')
            <x-responsive-nav-link :href="route('government-forms.index')" :active="request()->routeIs('government-forms.*')">
                {{ __('Government Forms') }}
            </x-responsive-nav-link>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
