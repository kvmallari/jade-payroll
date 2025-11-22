<x-app-layout>
   

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <!-- Quick Stats -->
       
            
            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                 <div class="flex flex-wrap gap-4 items-end mb-4">
                        <div class="flex-1 min-w-48">
                            <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}" 
                                   placeholder="Name, Employee #, Email" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        @if(Auth::user()->isSuperAdmin())
                        <div class="flex-1 min-w-40">
                            <label for="company" class="block text-sm font-medium text-gray-700">Company</label>
                            <select name="company" id="company" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ request('company') == $company->id ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="flex-1 min-w-40">
                            <label for="department" class="block text-sm font-medium text-gray-700">Department</label>
                            <select name="department" id="department" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Departments</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->id }}" {{ request('department') == $department->id ? 'selected' : '' }}>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex-1 min-w-32">
                            <label for="employment_status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="employment_status" id="employment_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Status</option>
                                <option value="active" {{ request('employment_status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('employment_status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                <option value="terminated" {{ request('employment_status') == 'terminated' ? 'selected' : '' }}>Terminated</option>
                                <option value="resigned" {{ request('employment_status') == 'resigned' ? 'selected' : '' }}>Resigned</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-32">
                            <label for="sort_name" class="block text-sm font-medium text-gray-700">Sort Name</label>
                            <select name="sort_name" id="sort_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Default</option>
                                <option value="asc" {{ request('sort_name') == 'asc' ? 'selected' : '' }}>A-Z</option>
                                <option value="desc" {{ request('sort_name') == 'desc' ? 'selected' : '' }}>Z-A</option>
                            </select>
                        </div>
                        <div class="flex-1 min-w-32">
                            <label for="sort_hire_date" class="block text-sm font-medium text-gray-700">Employment</label>
                            <select name="sort_hire_date" id="sort_hire_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Default</option>
                                <option value="desc" {{ request('sort_hire_date') == 'desc' ? 'selected' : '' }}>New-Old</option>
                                <option value="asc" {{ request('sort_hire_date') == 'asc' ? 'selected' : '' }}>Old-New</option>
                            </select>
                        </div>
                        <div class="flex items-center space-x-2">
                            <button type="button" id="reset_filters" class="inline-flex items-center px-4 h-10 bg-gray-600 border border-transparent rounded-md text-white text-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                            <button type="button" onclick="openExportModal()" 
                                    class="inline-flex items-center px-4 h-10 bg-green-600 border border-transparent rounded-md text-white text-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Employees Summary
                            </button>
                        </div>
                    </div>
                    
                    <!-- Records per page selector -->
                    {{-- <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">
                            <div class="text-sm text-gray-500">
                                Total: {{ $employees->total() }} employees
                            </div>
                            <a href="{{ route('employees.create') }}" 
                               class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Add Employee
                            </a>
                        </div>
                    </div> --}}
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-4 mb-6">
                <!-- Employment Types -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-blue-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Employment Types</h3>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                            @forelse($summaryStats['employment_types'] ?? [] as $type => $count)
                                <div class="flex text-xs">
                                    <span class="text-gray-600 mr-1">{{ ucfirst(str_replace('_', ' ', $type)) }}:</span>
                                    <span class="font-semibold text-blue-600">{{ $count }}</span>
                                </div>
                            @empty
                                <div class="col-span-2 text-xs text-gray-500">No data available</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Benefits Status -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Benefits Status</h3>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                            @forelse($summaryStats['benefits_status'] ?? [] as $status => $count)
                                <div class="flex text-xs">
                                    <span class="text-gray-600 mr-1">{{ ucfirst(str_replace('_', ' ', $status)) }}:</span>
                                    <span class="font-semibold text-green-600">{{ $count }}</span>
                                </div>
                            @empty
                                <div class="col-span-2 text-xs text-gray-500">No data available</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Pay Frequency -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Pay Frequency</h3>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                            @forelse($summaryStats['pay_frequency'] ?? [] as $frequency => $count)
                                @php
                                    $displayFrequency = $frequency;
                                    if ($frequency === 'semi_monthly') {
                                        $displayFrequency = 'Semi';
                                    } else {
                                        $displayFrequency = ucfirst(str_replace('_', ' ', $frequency));
                                    }
                                @endphp
                                <div class="flex text-xs">
                                    <span class="text-gray-600 mr-1">{{ $displayFrequency }}:</span>
                                    <span class="font-semibold text-purple-600">{{ $count }}</span>
                                </div>
                            @empty
                                <div class="col-span-2 text-xs text-gray-500">No data available</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Rate Types -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center mb-2">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-orange-500 rounded-md flex items-center justify-center">
                                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-gray-900">Rate Types</h3>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-x-2 gap-y-1">
                            @forelse($summaryStats['rate_types'] ?? [] as $type => $count)
                                <div class="flex text-xs">
                                    <span class="text-gray-600 mr-1">{{ ucfirst(str_replace('_', ' ', $type)) }}:</span>
                                    <span class="font-semibold text-orange-600">{{ $count }}</span>
                                </div>
                            @empty
                                <div class="col-span-2 text-xs text-gray-500">No data available</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6" id="employee-list-container">
                    @include('employees.partials.employee-list', ['employees' => $employees])
                </div>
                
                <div class="px-6 pb-6" id="pagination-container">
                    @include('employees.partials.pagination', ['employees' => $employees])
                </div>
            </div>
        </div>
    </div>

<!-- Export Modal -->
<div id="exportModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50 overflow-y-auto h-full w-full">
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
                    Select the format for your employee summary export:
                </p>
            </div>
            <form id="exportForm" method="POST" action="{{ route('employees.generate-summary') }}">
                @csrf
                <input type="hidden" name="search" id="export_search">
                <input type="hidden" name="company" id="export_company">
                <input type="hidden" name="department" id="export_department">
                <input type="hidden" name="employment_status" id="export_employment_status">
                <input type="hidden" name="sort_name" id="export_sort_name">
                <input type="hidden" name="sort_hire_date" id="export_sort_hire_date">
                
                <div class="items-center px-4 py-3">
                    <button type="submit" name="export" value="pdf" 
                            class="px-4 py-2 bg-red-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300 mb-3">
                        <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"></path>
                        </svg>
                        Export as PDF
                    </button>
                    <button type="submit" name="export" value="excel" 
                            class="px-4 py-2 bg-green-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-300 mb-3">
                        <svg class="w-5 h-5 inline mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm4 2a1 1 0 000 2h4a1 1 0 100-2H8zm0 3a1 1 0 000 2h4a1 1 0 100-2H8zm0 3a1 1 0 000 2h4a1 1 0 100-2H8z" clip-rule="evenodd"></path>
                        </svg>
                        Export as Excel
                    </button>
                    <button type="button" onclick="closeExportModal()" 
                            class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Context Menu -->
<div id="contextMenu" class="fixed z-50 hidden bg-white border border-gray-200 rounded-lg shadow-lg min-w-48 opacity-0 scale-95 transform transition-all duration-200 ease-out">
    <div class="p-2 border-b border-gray-100">
        <div class="text-sm font-semibold text-gray-900" id="contextMenuName"></div>
        <div class="text-xs text-gray-500" id="contextMenuEmpId"></div>
    </div>
    <div class="py-1">
        <a href="#" id="contextMenuView" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
            </svg>
            View Details
        </a>
        @can('edit employees')
            <a href="#" id="contextMenuEdit" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Employee
            </a>
        @endcan
        <a href="#" id="contextMenuViewPayroll" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            </svg>
            View Payroll History
        </a>
        @can('delete employees')
            <hr class="my-1">
            <a href="#" id="contextMenuDelete" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
                Delete Employee
            </a>
        @endcan
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Live filtering functionality
        const searchInput = document.getElementById('search');
        const companySelect = document.getElementById('company');
        const departmentSelect = document.getElementById('department');
        const employmentStatusSelect = document.getElementById('employment_status');
        const sortNameSelect = document.getElementById('sort_name');
        const sortHireDateSelect = document.getElementById('sort_hire_date');
        const perPageSelect = document.getElementById('per_page');

        // Debounce function for search input
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

        // Update URL and apply filters via AJAX (no page reload)
        function updateFilters() {
            const url = new URL(window.location.origin + window.location.pathname);
            const params = new URLSearchParams();

            // Add filter parameters with null checks
            if (searchInput && searchInput.value.trim()) params.set('search', searchInput.value.trim());
            if (companySelect && companySelect.value) params.set('company', companySelect.value);
            if (departmentSelect && departmentSelect.value) params.set('department', departmentSelect.value);
            if (employmentStatusSelect && employmentStatusSelect.value) params.set('employment_status', employmentStatusSelect.value);
            if (sortNameSelect && sortNameSelect.value) params.set('sort_name', sortNameSelect.value);
            if (sortHireDateSelect && sortHireDateSelect.value) params.set('sort_hire_date', sortHireDateSelect.value);
            if (perPageSelect && perPageSelect.value && perPageSelect.value !== '10') params.set('per_page', perPageSelect.value);

            // Update URL without page reload
            url.search = params.toString();
            window.history.pushState({}, '', url.toString());

            // Make AJAX request to get filtered data
            fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                },
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                // Update entire employee list container (includes pagination)
                const container = document.getElementById('employee-list-container');
                if (container && data.html) {
                    container.innerHTML = data.html;
                }
            })
            .catch(error => {
                console.error('Error filtering employees:', error);
            });
        }

        // Add event listeners for live filtering
        if (searchInput) {
            searchInput.addEventListener('input', debounce(function() {
                updateFilters();
            }, 500));
        }
        if (companySelect) {
            companySelect.addEventListener('change', function() {
                updateFilters();
            });
        }
        if (departmentSelect) {
            departmentSelect.addEventListener('change', function() {
                updateFilters();
            });
        }
        if (employmentStatusSelect) {
            employmentStatusSelect.addEventListener('change', function() {
                updateFilters();
            });
        }
        if (sortNameSelect) {
            sortNameSelect.addEventListener('change', function() {
                updateFilters();
            });
        }
        if (sortHireDateSelect) {
            sortHireDateSelect.addEventListener('change', function() {
                updateFilters();
            });
        }
        if (perPageSelect) {
            perPageSelect.addEventListener('change', function() {
                updateFilters();
            });
        }

        // Reset filters functionality
        document.getElementById('reset_filters').addEventListener('click', function() {
            window.location.href = '{{ route("employees.index") }}';
        });

        // Export modal functions - move inside DOMContentLoaded
        window.openExportModal = function() {
            // Copy current filter values to hidden form inputs
            document.getElementById('export_search').value = document.getElementById('search').value;
            if (document.getElementById('company')) {
                document.getElementById('export_company').value = document.getElementById('company').value;
            }
            document.getElementById('export_department').value = document.getElementById('department').value;
            document.getElementById('export_employment_status').value = document.getElementById('employment_status').value;
            document.getElementById('export_sort_name').value = document.getElementById('sort_name').value;
            document.getElementById('export_sort_hire_date').value = document.getElementById('sort_hire_date').value;
            
            document.getElementById('exportModal').classList.remove('hidden');
        };

        window.closeExportModal = function() {
            document.getElementById('exportModal').classList.add('hidden');
        };

        // Close modal when clicking outside
        document.getElementById('exportModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeExportModal();
            }
        });
    }); // End of DOMContentLoaded
</script>

    <script>
        let currentEmployeeId = null;
        let contextMenu = document.getElementById('contextMenu');
        
        // Pre-generate route templates using URL helper
        const baseUrl = "{{ url('/employees') }}";
        const payrollHistoryTemplate = "{{ route('payrolls.index') }}?employee_number=";

        function showContextMenu(event, employeeNumber, employeeName, employeeDisplayNumber) {
            event.preventDefault();
            event.stopPropagation();
            
            currentEmployeeId = employeeNumber;
            
            // Get the clicked row/card to check user role
            const clickedElement = event.target.closest('[data-employee-id]');
            const userRole = clickedElement ? clickedElement.getAttribute('data-user-role') : '';
            
            // Update context menu header
            document.getElementById('contextMenuName').textContent = employeeName;
            document.getElementById('contextMenuEmpId').textContent = employeeDisplayNumber;
            
            // Convert employee number to lowercase for URLs
            const lowercaseEmployeeNumber = employeeNumber.toLowerCase();
            
            // Update links with proper Laravel routes
            document.getElementById('contextMenuView').href = baseUrl + '/' + lowercaseEmployeeNumber;
            @can('edit employees')
            document.getElementById('contextMenuEdit').href = baseUrl + '/' + lowercaseEmployeeNumber + '/edit';
            @endcan
            @can('view employees')
            document.getElementById('contextMenuViewPayroll').href = payrollHistoryTemplate + employeeNumber;
            @endcan
            
            // Show/hide delete option based on user role
            @can('delete employees')
            const deleteOption = document.getElementById('contextMenuDelete');
            if (userRole === 'System Admin') {
                deleteOption.style.display = 'none';
            } else {
                deleteOption.style.display = 'flex';
            }
            @endcan
            
            // Get exact mouse position
            const mouseX = event.clientX;
            const mouseY = event.clientY;
            
            // Position context menu at mouse cursor initially
            contextMenu.style.left = mouseX + 'px';
            contextMenu.style.top = mouseY + 'px';
            contextMenu.classList.remove('hidden');
            
            // Show with animation
            setTimeout(() => {
                contextMenu.classList.remove('opacity-0', 'scale-95');
                contextMenu.classList.add('opacity-100', 'scale-100');
            }, 10);
            
            // Adjust position to prevent menu from going off-screen
            setTimeout(() => {
                const menuRect = contextMenu.getBoundingClientRect();
                const viewportWidth = window.innerWidth;
                const viewportHeight = window.innerHeight;
                
                let adjustedX = mouseX;
                let adjustedY = mouseY;
                
                // Adjust horizontal position if menu goes off right edge
                if (mouseX + menuRect.width > viewportWidth) {
                    adjustedX = mouseX - menuRect.width;
                }
                
                // Adjust vertical position if menu goes off bottom edge  
                if (mouseY + menuRect.height > viewportHeight) {
                    adjustedY = mouseY - menuRect.height;
                }
                
                // Ensure menu doesn't go off left or top edges
                adjustedX = Math.max(0, adjustedX);
                adjustedY = Math.max(0, adjustedY);
                
                contextMenu.style.left = adjustedX + 'px';
                contextMenu.style.top = adjustedY + 'px';
            }, 1);
        }

        // Helper function to hide context menu with animation
        function hideContextMenu() {
            contextMenu.classList.remove('opacity-100', 'scale-100');
            contextMenu.classList.add('opacity-0', 'scale-95');
            setTimeout(() => {
                contextMenu.classList.add('hidden');
            }, 150);
        }

        // Hide context menu when clicking elsewhere or pressing Escape
        document.addEventListener('click', function(event) {
            if (!contextMenu.contains(event.target)) {
                hideContextMenu();
            }
        });

        // Hide context menu on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideContextMenu();
            }
        });

        // Hide context menu on scroll
        document.addEventListener('scroll', function() {
            hideContextMenu();
        }, true); // Use capture to catch all scroll events

        // Hide context menu on window resize
        window.addEventListener('resize', function() {
            hideContextMenu();
        });

        // Handle delete action
        @can('delete employees')
        document.getElementById('contextMenuDelete').addEventListener('click', function(event) {
            event.preventDefault();
            
            if (currentEmployeeId && confirm('Are you sure you want to delete this employee?')) {
                // Create and submit delete form
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = baseUrl + '/' + currentEmployeeId.toLowerCase();
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                
                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'DELETE';
                
                form.appendChild(csrfToken);
                form.appendChild(methodField);
                document.body.appendChild(form);
                form.submit();
            }
            
            hideContextMenu();
        });
        @endcan

        // Prevent default context menu on right-click
        document.addEventListener('contextmenu', function(event) {
            if (event.target.closest('[data-employee-id]')) {
                event.preventDefault();
            }
        });

        // Handle per page selection
        function updatePerPage(value) {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', value);
            url.searchParams.delete('page'); // Reset to first page
            window.location.href = url.toString();
        }

        // Auto-hide success and error messages after 2 seconds
        const successMessage = document.getElementById('success-message');
        const errorMessage = document.getElementById('error-message');
        
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s ease-out';
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.remove();
                }, 500);
            }, 2000);
        }
        
        if (errorMessage) {
            setTimeout(() => {
                errorMessage.style.transition = 'opacity 0.5s ease-out';
                errorMessage.style.opacity = '0';
                setTimeout(() => {
                    errorMessage.remove();
                }, 500);
            }, 2000);
        }
    </script>
</x-app-layout>
