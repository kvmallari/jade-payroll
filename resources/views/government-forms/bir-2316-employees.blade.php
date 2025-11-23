<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl te            <a href=" #" id="context        // Show context menu
        function showContextMenu(event, employeeNumber, employeeNumberDuplicate, employeeName, year) {
            event.preventDefault();
            event.stopPropagation();
            
            currentEmployeeNumber = employeeNumber; // This is employee_number for route binding
            currentYear = year;
            
            // Update menu content
            document.getElementById('contextMenuEmployee').textContent = employeeName;
            document.getElementById('contextMenuYear').textContent = 'Tax Year: ' + year;
            
            // Update view link (still uses GET)
            document.getElementById('contextMenuView').href = '/government-forms/bir-2316/' + employeeNumber + '?year=' + year;l" class="flex items-center px-3 py-2 text-sm text-green-600 hover:bg-green-50 hover:text-green-700 transition-colors duration-150">800 leading-tight">
                    {{ __('BIR Form 2316 Generation') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">
                    Generate BIR Form 2316 (Certificate of Compensation Payment/Tax Withheld) for {{ $year }}
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('government-forms.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Government Forms
                </a>
                <!-- Year selector -->
                <form method="GET" action="{{ route('government-forms.bir-2316.employees') }}" class="inline flex items-center">
                    <label for="year" class="text-sm font-medium text-gray-700 mr-2">Tax Year:</label>
                    <select name="year" id="year" onchange="this.form.submit()"
                        class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        @for($y = now()->year; $y >= now()->year - 1; $y--)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                        @endfor
                    </select>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <!-- Filter Controls -->
                    <div class="flex flex-wrap gap-4 items-end mb-4">
                       
                        <div class="flex-1 min-w-48">
                            <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}"
                                placeholder="Name, Employee #, Email"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                         @if(Auth::user()->isSuperAdmin())
                        <div class="flex-1 min-w-48">
                            <label for="company" class="block text-sm font-medium text-gray-700">Company</label>
                            <select name="company" id="company" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ strtolower($company->name) }}" {{ request('company') == strtolower($company->name) ? 'selected' : '' }}>
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
                                <option value="active" {{ request('employment_status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
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
                        <div class="flex items-end">
                            <button type="button" id="reset_filters" class="inline-flex items-center px-4 h-10 bg-gray-600 border border-transparent rounded-md text-white text-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee List -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">
                                Active Employees
                                <span class="text-sm font-normal text-gray-500">
                                    ({{ $employees->total() }} total employees)
                                </span>
                            </h3>
                            <div class="mt-1">
                                <form method="GET" action="{{ route('government-forms.bir-2316.employees') }}" class="inline-flex items-center space-x-2">
                                    <label for="year-main" class="text-sm font-medium text-blue-600">Tax Year:</label>
                                    <select name="year" id="year-main" onchange="this.form.submit()"
                                        class="text-sm border-blue-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @for($y = now()->year; $y >= now()->year - 1; $y--)
                                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                                        @endfor
                                    </select>
                                </form>
                            </div>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="flex space-x-2">
                                <button onclick="downloadAllExcel()"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500"
                                    title="Downloads all employees in one Excel file (each employee on separate worksheet)">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span id="excelText">All Excel</span>
                                </button>
                                <button onclick="downloadAllPDF()"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                    title="Downloads all employees in one PDF file (each employee on separate page, 8.5x13 inch paper)">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span id="pdfText">All PDF</span>
                                </button>
                                <button onclick="downloadAllFilledPDF()"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500"
                                    title="Downloads all employees as filled PDF forms with populated payroll data">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span id="filledPdfText">All Filled PDF</span>
                                </button>
                                <a href="{{ route('government-forms.bir-2316.settings') }}"
                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500"
                                    title="Configure BIR 2316 form settings">
                                    <svg class="w-4 h-4 " fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>

                                </a>
                            </div>
                            <div class="text-sm text-gray-600">
                                <div class="text-xs text-blue-600">
                                    <strong>Tip:</strong> Right-click on any employee row to access Generate and Download actions.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="employee-list-container">
                        @include('government-forms.partials.bir-2316-employee-list', ['employees' => $employees, 'year' => $year])
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Context Menu -->
    <div id="contextMenu" class="fixed bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 hidden min-w-52 backdrop-blur-sm transition-all duration-200 ease-out transform opacity-0 scale-95">
        <div id="contextMenuHeader" class="px-3 py-2 border-b border-gray-100 bg-gray-50 rounded-t-md">
            <div class="text-sm font-medium text-gray-900" id="contextMenuEmployee"></div>
            <div class="text-xs text-gray-500" id="contextMenuYear"></div>
        </div>
        <div class="py-1">
            <a href="#" id="contextMenuDownloadExcel" onclick="downloadIndividualExcel(event)" class="flex items-center px-3 py-2 text-sm text-green-600 hover:bg-green-50 hover:text-green-700 transition-colors duration-150">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Download Excel
            </a>
            <a href="#" id="contextMenuDownloadPDF" onclick="downloadIndividualPDF(event)" class="flex items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors duration-150">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Download PDF
            </a>
            <a href="#" id="contextMenuDownloadFilledPDF" onclick="downloadIndividualFilledPDF(event)" class="flex items-center px-3 py-2 text-sm text-purple-600 hover:bg-purple-50 hover:text-purple-700 transition-colors duration-150">
                <svg class="mr-3 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Download Filled PDF
            </a>
        </div>
    </div>

    <script>
        // Global variables for context menu
        let contextMenu = null;
        let currentEmployeeNumber = null;
        let currentYear = null;

        document.addEventListener('DOMContentLoaded', function() {
            contextMenu = document.getElementById('contextMenu');

            // Function to hide context menu with smooth animation
            function hideContextMenu() {
                if (contextMenu) {
                    contextMenu.classList.remove('opacity-100', 'scale-100');
                    contextMenu.classList.add('opacity-0', 'scale-95');
                    setTimeout(() => {
                        contextMenu.classList.add('hidden');
                    }, 200); // Match the transition duration
                }
            }

            // Hide context menu when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('#contextMenu')) {
                    hideContextMenu();
                }
            });

            // Hide context menu when scrolling (with smooth animation)
            document.addEventListener('scroll', hideContextMenu);

            // Hide context menu on window resize
            window.addEventListener('resize', hideContextMenu);

            // Show context menu - make it global so inline handlers can access it
            window.showContextMenu = function(event, employeeNumber, employeeNumberDuplicate, employeeName, year) {
                event.preventDefault();
                event.stopPropagation();

                if (!contextMenu) {
                    console.error('Context menu element not found!');
                    return;
                }

                currentEmployeeNumber = employeeNumber;
                currentYear = year;

                // Update menu content
                const menuEmployee = document.getElementById('contextMenuEmployee');
                const menuYear = document.getElementById('contextMenuYear');

                if (menuEmployee) menuEmployee.textContent = employeeName;
                if (menuYear) menuYear.textContent = 'Tax Year: ' + year;

                // Position and show menu at mouse location
                const menuWidth = 210; // approximate width of context menu
                const menuHeight = 160; // approximate height of context menu
                const windowWidth = window.innerWidth;
                const windowHeight = window.innerHeight;

                let x = event.clientX;
                let y = event.clientY;

                // Adjust position if menu would go off screen
                if (x + menuWidth > windowWidth) {
                    x = windowWidth - menuWidth - 10;
                }
                if (y + menuHeight > windowHeight) {
                    y = windowHeight - menuHeight - 10;
                }

                // Ensure minimum margins
                x = Math.max(10, x);
                y = Math.max(10, y);

                contextMenu.style.left = x + 'px';
                contextMenu.style.top = y + 'px';

                contextMenu.classList.remove('hidden');
                contextMenu.classList.remove('opacity-0', 'scale-95');
                contextMenu.classList.add('opacity-100', 'scale-100');
            }; // End of showContextMenu function

            // Update per page records - also make it global
            window.updatePerPage = function(value) {
                const url = new URL(window.location);
                url.searchParams.set('per_page', value);
                url.searchParams.set('page', 1); // Reset to first page
                window.location.href = url.toString();
            };
        }); // End of DOMContentLoaded

        // Filtering functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Live filtering functionality
            const searchInput = document.getElementById('search');
            const departmentSelect = document.getElementById('department');
            const employmentStatusSelect = document.getElementById('employment_status');
            const sortNameSelect = document.getElementById('sort_name');
            const sortHireDateSelect = document.getElementById('sort_hire_date');

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

            // Update URL and reload page with current filter values
            function updateFilters() {
                const url = new URL(window.location.origin + window.location.pathname);
                const params = new URLSearchParams();

                // Preserve year parameter
                if (new URLSearchParams(window.location.search).get('year')) {
                    params.set('year', new URLSearchParams(window.location.search).get('year'));
                }

                // Add filter parameters
                if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
                if (departmentSelect.value) params.set('department', departmentSelect.value);
                if (employmentStatusSelect.value) params.set('employment_status', employmentStatusSelect.value);
                if (sortNameSelect.value) params.set('sort_name', sortNameSelect.value);
                if (sortHireDateSelect.value) params.set('sort_hire_date', sortHireDateSelect.value);

                // Preserve per_page if set
                const currentPerPage = new URLSearchParams(window.location.search).get('per_page');
                if (currentPerPage && currentPerPage !== '10') params.set('per_page', currentPerPage);

                // Update URL without reload
                url.search = params.toString();
                window.history.pushState({}, '', url.toString());

                // Make AJAX request
                fetch(url.toString(), {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        }
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Update the employee list container
                        const employeeListContainer = document.getElementById('employee-list-container');
                        if (employeeListContainer && data.html) {
                            employeeListContainer.innerHTML = data.html;

                            // Verify context menu functionality is preserved after AJAX update
                            if (typeof window.showContextMenu !== 'function') {
                                console.error('Context menu function lost after AJAX update');
                            }
                            if (!document.getElementById('contextMenu')) {
                                console.error('Context menu DOM element lost after AJAX update');
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching filtered data:', error);
                        // Fallback to page reload if AJAX fails
                        window.location.href = url.toString();
                    });
            }

            // Add event listeners for live filtering
            const companySelect = document.getElementById('company');
            if (companySelect) companySelect.addEventListener('change', updateFilters);
            if (searchInput) searchInput.addEventListener('input', debounce(updateFilters, 500));
            if (departmentSelect) departmentSelect.addEventListener('change', updateFilters);
            if (employmentStatusSelect) employmentStatusSelect.addEventListener('change', updateFilters);
            if (sortNameSelect) sortNameSelect.addEventListener('change', updateFilters);
            if (sortHireDateSelect) sortHireDateSelect.addEventListener('change', updateFilters);

            // Reset filters functionality
            document.getElementById('reset_filters').addEventListener('click', function() {
                // Reset to clean URL without any parameters
                window.location.href = '{{ route("government-forms.bir-2316.employees") }}';
            });
        });

        // Download all Excel files
        function downloadAllExcel() {
            const button = document.querySelector('button[onclick="downloadAllExcel()"]');
            const textElement = document.getElementById('excelText');
            const originalText = textElement.textContent;

            // Disable button and show generating text
            button.disabled = true;
            button.classList.add('opacity-50', 'cursor-not-allowed');
            textElement.textContent = 'Generating...';

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("government-forms.bir-2316.generate-summary") }}';
            form.style.display = 'none';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);

            // Add year
            const yearInput = document.createElement('input');
            yearInput.type = 'hidden';
            yearInput.name = 'year';
            yearInput.value = '{{ $year }}';
            form.appendChild(yearInput);

            // Add export format
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'export';
            formatInput.value = 'excel';
            form.appendChild(formatInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Reset button state after a delay
            setTimeout(() => {
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
                textElement.textContent = originalText;
            }, 5000);
        }

        // Download all PDF files
        function downloadAllPDF() {
            const button = document.querySelector('button[onclick="downloadAllPDF()"]');
            const textElement = document.getElementById('pdfText');
            const originalText = textElement.textContent;

            // Disable button and show generating text
            button.disabled = true;
            button.classList.add('opacity-50', 'cursor-not-allowed');
            textElement.textContent = 'Generating...';

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("government-forms.bir-2316.generate-summary") }}';
            form.style.display = 'none';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);

            // Add year
            const yearInput = document.createElement('input');
            yearInput.type = 'hidden';
            yearInput.name = 'year';
            yearInput.value = '{{ $year }}';
            form.appendChild(yearInput);

            // Add export format
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'export';
            formatInput.value = 'pdf';
            form.appendChild(formatInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Reset button state after a delay
            setTimeout(() => {
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
                textElement.textContent = originalText;
            }, 5000);
        }

        // Download all Filled PDF files
        function downloadAllFilledPDF() {
            const button = document.querySelector('button[onclick="downloadAllFilledPDF()"]');
            const textElement = document.getElementById('filledPdfText');
            const originalText = textElement.textContent;

            // Disable button and show generating text
            button.disabled = true;
            button.classList.add('opacity-50', 'cursor-not-allowed');
            textElement.textContent = 'Generating...';

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("government-forms.bir-2316.generate-summary") }}';
            form.style.display = 'none';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);

            // Add year
            const yearInput = document.createElement('input');
            yearInput.type = 'hidden';
            yearInput.name = 'year';
            yearInput.value = '{{ $year }}';
            form.appendChild(yearInput);

            // Add export format for filled PDF
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'export';
            formatInput.value = 'filled-pdf';
            form.appendChild(formatInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);

            // Reset button state after a delay
            setTimeout(() => {
                button.disabled = false;
                button.classList.remove('opacity-50', 'cursor-not-allowed');
                textElement.textContent = originalText;
            }, 5000);
        }

        // Download individual Excel
        function downloadIndividualExcel(event) {
            event.preventDefault();
            event.stopPropagation();

            if (!currentEmployeeNumber || !currentYear) {
                console.error('Missing employee number or year');
                return;
            }

            // Hide context menu
            contextMenu.classList.add('hidden');

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/government-forms/bir-2316/' + currentEmployeeNumber + '/generate';
            form.style.display = 'none';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);

            // Add year
            const yearInput = document.createElement('input');
            yearInput.type = 'hidden';
            yearInput.name = 'year';
            yearInput.value = currentYear;
            form.appendChild(yearInput);

            // Add export format
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'export';
            formatInput.value = 'excel';
            form.appendChild(formatInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Download individual PDF
        function downloadIndividualPDF(event) {
            event.preventDefault();
            event.stopPropagation();

            if (!currentEmployeeNumber || !currentYear) {
                console.error('Missing employee number or year');
                return;
            }

            // Hide context menu
            contextMenu.classList.add('hidden');

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/government-forms/bir-2316/' + currentEmployeeNumber + '/generate';
            form.style.display = 'none';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);

            // Add year
            const yearInput = document.createElement('input');
            yearInput.type = 'hidden';
            yearInput.name = 'year';
            yearInput.value = currentYear;
            form.appendChild(yearInput);

            // Add export format
            const formatInput = document.createElement('input');
            formatInput.type = 'hidden';
            formatInput.name = 'export';
            formatInput.value = 'pdf';
            form.appendChild(formatInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }

        // Download individual Filled PDF
        function downloadIndividualFilledPDF(event) {
            event.preventDefault();
            event.stopPropagation();

            if (!currentEmployeeNumber || !currentYear) {
                console.error('Missing employee number or year');
                return;
            }

            // Hide context menu
            contextMenu.classList.add('hidden');

            // Create and submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/government-forms/bir-2316/' + currentEmployeeNumber + '/generate-filled';
            form.style.display = 'none';

            // Add CSRF token
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);

            // Add year
            const yearInput = document.createElement('input');
            yearInput.type = 'hidden';
            yearInput.name = 'year';
            yearInput.value = currentYear;
            form.appendChild(yearInput);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        }
    </script>
</x-app-layout>