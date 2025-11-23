<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Employer Shares Report') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filter Section -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Filter Reports</h3>
                    <div class="flex flex-wrap gap-4 items-end">
                        @if(Auth::user()->isSuperAdmin())
                        <div class="flex-1 min-w-32">
                            <label for="company" class="block text-sm font-medium text-gray-700">Company</label>
                            <select name="company" id="company" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">All Companies</option>
                                @foreach($companies as $company)
                                    <option value="{{ strtolower($company->name) }}" {{ request('company') == strtolower($company->name) ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @endif
                        <div class="flex-1 min-w-32">
                            <label for="year" class="block text-sm font-medium text-gray-700">Year</label>
                            <select name="year" id="year" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                @foreach($availableYears as $availableYear)
                                    <option value="{{ $availableYear }}" {{ $year == $availableYear ? 'selected' : '' }}>
                                        {{ $availableYear }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <div class="flex-1 min-w-32">
                            <label for="month" class="block text-sm font-medium text-gray-700">Month</label>
                            <select name="month" id="month" class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">All Months</option>
                                @for($i = 1; $i <= 12; $i++)
                                    <option value="{{ $i }}" {{ $month == $i ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                                    </option>
                                @endfor
                            </select>
                        </div>
                        
                        <div class="flex items-center space-x-2">
                            <button type="button" id="reset_filters" 
                                    class="inline-flex items-center px-4 h-10 bg-gray-600 border border-transparent rounded-md text-white text-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Reset Filters
                            </button>
                            <button type="button" onclick="openExportModal()" 
                                    class="inline-flex items-center px-4 h-10 bg-green-600 border border-transparent rounded-md text-white text-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Report Summary
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dynamic Content Container -->
            <div id="employer-shares-content">
                @include('reports.partials.employer-shares-content', compact('shareData', 'grandTotals'))
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
                        Select the format for your employer shares summary export:
                    </p>
                </div>
                <form id="exportForm" method="POST" action="{{ route('reports.employer-shares.generate-summary') }}">
                    @csrf
                    <input type="hidden" name="year" id="export_year">
                    <input type="hidden" name="month" id="export_month">
                    
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Live filtering functionality
            const yearSelect = document.getElementById('year');
            const monthSelect = document.getElementById('month');

            // Update URL and apply filters via AJAX (no page reload)
            function updateFilters() {
                const url = new URL(window.location.origin + window.location.pathname);
                const params = new URLSearchParams();

                // Add filter parameters with null checks
                if (yearSelect && yearSelect.value) params.set('year', yearSelect.value);
                if (monthSelect && monthSelect.value) params.set('month', monthSelect.value);

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
                    // Update content container
                    const container = document.getElementById('employer-shares-content');
                    if (container && data.html) {
                        container.innerHTML = data.html;
                    }
                })
                .catch(error => {
                    console.error('Error filtering employer shares:', error);
                });
            }

            // Add event listeners for live filtering
            const companySelect = document.getElementById('company');
            if (companySelect) {
                companySelect.addEventListener('change', updateFilters);
            }
            if (yearSelect) {
                yearSelect.addEventListener('change', updateFilters);
            }
            if (monthSelect) {
                monthSelect.addEventListener('change', updateFilters);
            }

            // Reset filters functionality
            const resetButton = document.getElementById('reset_filters');
            if (resetButton) {
                resetButton.addEventListener('click', function() {
                    window.location.href = '{{ route("reports.employer-shares") }}';
                });
            }
        });

        // Export modal functions
        function openExportModal() {
            // Copy current filter values to hidden form inputs
            document.getElementById('export_year').value = document.getElementById('year').value;
            document.getElementById('export_month').value = document.getElementById('month').value;
            
            document.getElementById('exportModal').classList.remove('hidden');
        }

        function closeExportModal() {
            document.getElementById('exportModal').classList.add('hidden');
        }

        // Close modal when clicking outside
        document.getElementById('exportModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeExportModal();
            }
        });
    </script>
</x-app-layout>