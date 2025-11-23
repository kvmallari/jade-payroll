@extends('layouts.app')

@section('content')
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
                               placeholder="Name, Email" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                    </div>
                    @if(Auth::user()->isSuperAdmin())
                    <div class="flex-1 min-w-40">
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
                        <label for="role" class="block text-sm font-medium text-gray-700">Role</label>
                        <select name="role" id="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <option value="">All Roles</option>
                            <option value="System Administrator" {{ request('role') == 'System Administrator' ? 'selected' : '' }}>System Administrator</option>
                            <option value="HR Head" {{ request('role') == 'HR Head' ? 'selected' : '' }}>HR Head</option>
                            <option value="HR Staff" {{ request('role') == 'HR Staff' ? 'selected' : '' }}>HR Staff</option>
                            <option value="Employee" {{ request('role') == 'Employee' ? 'selected' : '' }}>Employee</option>
                        </select>
                    </div>
                    <div class="flex items-center space-x-2">
                        <button type="button" id="reset_filters" class="inline-flex items-center px-4 h-10 bg-gray-600 border border-transparent rounded-md text-white text-sm hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors">
                            <svg class="w-4 h-4 " fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                        </button>
                        <button type="button" onclick="openExportModal()" 
                           class="inline-flex items-center px-4 h-10 bg-green-600 border border-transparent rounded-md text-white text-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Users Summary
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4 mb-6">
            <!-- System Administrator -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <div class="flex items-center mb-2">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-red-500 rounded-md flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900">System Administrator</h3>
                            <p class="text-2xl font-bold text-red-600">{{ $userStats['system_administrator'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HR Head -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <div class="flex items-center mb-2">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-purple-500 rounded-md flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900">HR Head</h3>
                            <p class="text-2xl font-bold text-purple-600">{{ $userStats['hr_head'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- HR Staff -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <div class="flex items-center mb-2">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-indigo-500 rounded-md flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900">HR Staff</h3>
                            <p class="text-2xl font-bold text-indigo-600">{{ $userStats['hr_staff'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-4">
                    <div class="flex items-center mb-2">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-md flex items-center justify-center">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900">Employee</h3>
                            <p class="text-2xl font-bold text-green-600">{{ $userStats['employee'] ?? 0 }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <!-- Users Table -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div class="p-6">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900">Users</h3>
                 
                        <div class="text-sm text-gray-500">
                            Tip: Click on any user row to view details | Right-click for Edit, Delete and other actions
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">USER</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EMAIL</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ROLE</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">STATUS</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CREATED</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="users-tbody">
                            @include('users.partials.user-list')
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="pagination-container">
                    @include('users.partials.pagination')
                </div>
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
                    Select the format for your users summary export:
                </p>
            </div>
            <form id="exportForm" method="POST" action="{{ route('users.generate-summary') }}">
                @csrf
                <input type="hidden" name="search" id="export_search">
                <input type="hidden" name="company" id="export_company">
                <input type="hidden" name="role" id="export_role">
                
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
        <div class="text-xs text-gray-500" id="contextMenuUserId"></div>
    </div>
    <div class="py-1">
        <a href="#" id="contextView" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
            </svg>
            View Details
        </a>
        <a href="#" id="contextEdit" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Edit User
        </a>
        <hr class="my-1">
        <a href="#" id="contextDelete" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
            </svg>
            Delete User
        </a>
    </div>
</div>

<script>
    let selectedUserId = null;
    
    // Export modal functions
    function openExportModal() {
        // Set current filter values
        document.getElementById('export_search').value = document.getElementById('search').value || '';
        const companySelectForExport = document.getElementById('company');
        if (companySelectForExport) {
            document.getElementById('export_company').value = companySelectForExport.value || '';
        }
        document.getElementById('export_role').value = document.getElementById('role').value || '';
        
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
    
    // Context Menu functionality
    function showContextMenu(event, userId) {
        event.preventDefault();
        selectedUserId = userId;
        
        const contextMenu = document.getElementById('contextMenu');
        const userRow = event.target.closest('tr');
        const userName = userRow.querySelector('.text-sm.font-medium.text-gray-900').textContent;
        const userRole = userRow.querySelector('.inline-flex.px-2.py-1.text-xs.font-semibold.rounded-full').textContent.trim();
        
        // Update context menu header
        document.getElementById('contextMenuName').textContent = userName;
        document.getElementById('contextMenuUserId').textContent = userRole;
        
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
        
        // Update context menu links
        document.getElementById('contextView').href = '/users/' + userId;
        document.getElementById('contextEdit').href = '/users/' + userId + '/edit';
        
        // Handle delete action
        document.getElementById('contextDelete').onclick = function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this user?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/users/' + userId;
                
                const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'DELETE';
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = csrfToken;
                
                form.appendChild(methodInput);
                form.appendChild(csrfInput);
                document.body.appendChild(form);
                form.submit();
            }
            hideContextMenu();
        };
    }
    
    // Helper function to hide context menu with animation
    function hideContextMenu() {
        const contextMenu = document.getElementById('contextMenu');
        contextMenu.classList.remove('opacity-100', 'scale-100');
        contextMenu.classList.add('opacity-0', 'scale-95');
        setTimeout(() => {
            contextMenu.classList.add('hidden');
        }, 200);
    }
    
    // Hide context menu when clicking elsewhere or pressing Escape
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#contextMenu')) {
            hideContextMenu();
        }
    });
    
    // Hide context menu on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideContextMenu();
        }
    });
    
    // Hide context menu on scroll
    window.addEventListener('scroll', hideContextMenu);
    
    // Hide context menu on window resize
    window.addEventListener('resize', hideContextMenu);

    // Auto-submit form when filters change
    document.addEventListener('DOMContentLoaded', function() {
        // Live filtering functionality
        const searchInput = document.getElementById('search');
        const companySelect = document.getElementById('company');
        const roleSelect = document.getElementById('role');

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

        // Update URL and apply filters via AJAX
        function updateFilters() {
            const url = new URL(window.location.origin + window.location.pathname);
            const params = new URLSearchParams();

            // Add filter parameters
            if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
            if (companySelect && companySelect.value) params.set('company', companySelect.value);
            if (roleSelect.value) params.set('role', roleSelect.value);
            
            // Add per_page parameter if not default
            const perPageSelect = document.getElementById('per_page');
            if (perPageSelect && perPageSelect.value && perPageSelect.value !== '10') {
                params.set('per_page', perPageSelect.value);
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
                // Update users table
                document.getElementById('users-tbody').innerHTML = data.html;
                
                // Update pagination
                document.getElementById('pagination-container').innerHTML = data.pagination;
                
                // Update export form hidden inputs
                document.getElementById('export_search').value = searchInput.value.trim();
                if (companySelect) {
                    document.getElementById('export_company').value = companySelect.value;
                }
                document.getElementById('export_role').value = roleSelect.value;
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Add event listeners for live filtering
        searchInput.addEventListener('input', debounce(updateFilters, 500));
        if (companySelect) {
            companySelect.addEventListener('change', updateFilters);
        }
        roleSelect.addEventListener('change', updateFilters);
        
        // Add event listener for per_page changes
        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'per_page') {
                updateFilters();
            }
        });

        // Reset filter button functionality
        const resetButton = document.getElementById('reset_filters');
        if (resetButton) {
            resetButton.addEventListener('click', function() {
                // Reset to clean URL without any parameters (like BIR 2316)
                window.location.href = '{{ route("users.index") }}';
            });
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
    });
</script>
@endsection