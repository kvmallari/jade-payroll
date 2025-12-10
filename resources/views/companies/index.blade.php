<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-900">Company Management</h1>
                <button type="button" onclick="openCreateModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    Add New Company
                </button>
            </div>

            @if(session('success'))
                <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Filters -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex flex-wrap gap-4 items-end">
                        <div class="flex-1 min-w-64">
                            <label for="search" class="block text-sm font-medium text-gray-700">Search</label>
                            <input type="text" name="search" id="search" value="{{ request('search') }}" 
                                   placeholder="Name, Code, Email" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>
                        <div class="flex-1 min-w-40">
                            <label for="status" class="block text-sm font-medium text-gray-700">Status</label>
                            <select name="status" id="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">All Status</option>
                                <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" id="reset_filters" class="inline-flex items-center px-4 h-10 bg-gray-600 border border-transparent rounded-md text-white text-sm hover:bg-gray-700">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($companies->count() > 0)
                <div class="bg-white rounded-lg shadow overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Company</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Users</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employees</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">License</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($companies as $company)
                                <tr class="hover:bg-gray-50 cursor-pointer" 
                                    onclick="window.location.href='{{ route('users.index') }}?company={{ urlencode(strtolower($company->name)) }}';"
                                    oncontextmenu="showCompanyContextMenu(event, {{ $company->id }}, {{ json_encode($company->name) }}, '{{ $company->is_active ? 'true' : 'false' }}', '{{ (!$company->is_active && $company->users_count == 0 && $company->employees_count == 0) ? 'true' : 'false' }}', '{{ $company->license_key ?? '' }}'); return false;">
                                    <td class="px-6 py-4">
                                        <div class="text-sm font-medium text-gray-900">{{ $company->name }}</div>
                                        @if($company->code)
                                            <div class="text-sm text-gray-500">{{ $company->code }}</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $company->users_count }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-900">
                                        {{ $company->employees_count }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($company->license_key)
                                            @if(isset($company->license_expired) && $company->license_expired)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    Expired
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Activated
                                                </span>
                                            @endif
                                        @else
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Not Activated
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            {{ $company->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $company->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    {{ $companies->links() }}
                </div>
            @else
                <div class="bg-white rounded-lg shadow p-6">
                    <p class="text-gray-500 text-center">No companies found.</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Create Company Modal -->
    <div id="createCompanyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Add New Company</h3>
                <button type="button" onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="createCompanyForm" method="POST" action="{{ route('companies.store') }}">
                @csrf
                <div class="mb-4">
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Company Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Enter company name">
                    <span class="text-red-500 text-sm hidden" id="name-error"></span>
                </div>

                <div class="mb-4">
                    <label for="code" class="block text-sm font-medium text-gray-700 mb-2">Company Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" id="code" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Enter company code">
                    {{-- <p class="mt-1 text-xs text-gray-500">Unique identifier for the company</p> --}}
                    <span class="text-red-500 text-sm hidden" id="code-error"></span>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeCreateModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" id="createCompanyBtn"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Create Company
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Company Modal -->
    <div id="editCompanyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Edit Company</h3>
                <button type="button" onclick="closeEditModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form id="editCompanyForm" method="POST" action="">
                @csrf
                @method('PUT')
                <div class="mb-4">
                    <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-2">Company Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="edit_name" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Enter company name">
                    <span class="text-red-500 text-sm hidden" id="edit_name-error"></span>
                </div>

                <div class="mb-4">
                    <label for="edit_code" class="block text-sm font-medium text-gray-700 mb-2">Company Code <span class="text-red-500">*</span></label>
                    <input type="text" name="code" id="edit_code" required
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Enter company code">
                    {{-- <p class="mt-1 text-xs text-gray-500">Unique identifier for the company</p> --}}
                    <span class="text-red-500 text-sm hidden" id="edit_code-error"></span>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeEditModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" id="updateCompanyBtn"
                            class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Update Company
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- License Key Modal -->
    <div id="licenseKeyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-medium text-gray-900">Manage License Key</h3>
                <button type="button" onclick="closeLicenseKeyModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mb-4">
                <div id="license_info" class="hidden bg-blue-50 border border-blue-200 rounded-md p-3 mb-3">
                    <p class="text-sm font-medium text-gray-700 mb-2">Current License Information:</p>
                    <div class="space-y-1 text-sm text-gray-600">
                        <p>Company: <span id="license_company_name_display" class="font-medium text-gray-900"></span></p>
                        <p>Cost: <span id="license_cost" class="font-medium text-gray-900"></span></p>
                        <p>Activated: <span id="license_activated" class="font-medium text-gray-900"></span></p>
                        <p>Expires: <span id="license_expires" class="font-medium text-gray-900"></span></p>
                        <p>Max Employees: <span id="license_users" class="font-medium text-gray-900"></span></p>
                    </div>
                </div>
            </div>

            <form id="licenseKeyForm" onsubmit="return submitLicenseKey(event)">
                @csrf
                <div class="mb-4">
                    <label for="license_key" class="block text-sm font-medium text-gray-700 mb-2">License Key</label>
                    <textarea name="license_key" id="license_key" rows="3"
                           class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                           placeholder="Enter license key or leave empty to remove"></textarea>
                    <p class="mt-1 text-xs text-gray-500">Leave empty to deactivate the license. License key must be from the system license list.</p>
                    <p id="license_key_error" class="mt-1 text-sm text-red-600 hidden"></p>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeLicenseKeyModal()"
                            class="px-4 py-2 bg-gray-300 text-gray-700 rounded-md hover:bg-gray-400">
                        Cancel
                    </button>
                    <button type="submit" id="updateLicenseBtn"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed">
                        Update License
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Context Menu (will be dynamically created) -->

    <script>
        // DEFINE ALL ESSENTIAL FUNCTIONS IMMEDIATELY to prevent ReferenceError
        console.log('Defining company functions...');

        function closeContextMenu() {
            // Remove any context menus that might exist
            const menus = document.querySelectorAll('#contextMenu, [id*="contextMenu"], [class*="context"]');
            menus.forEach(menu => menu.remove());
        }

        window.handleEdit = function(companyId) {
            console.log('Edit clicked for company:', companyId);
            closeContextMenu();
            
            // Fetch company data and show modal
            fetch(`/companies/${companyId}/edit`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Populate the edit form
                    document.getElementById('edit_name').value = data.company.name;
                    document.getElementById('edit_code').value = data.company.code || '';
                    document.getElementById('editCompanyForm').action = `/companies/${companyId}`;
                    
                    // Show the modal
                    document.getElementById('editCompanyModal').classList.remove('hidden');
                    document.getElementById('edit_name').focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error loading company data');
            });
        };

        window.handleToggle = function(companyId) {
            console.log('Toggle clicked for company:', companyId);
            closeContextMenu();
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/companies/${companyId}/toggle`;
            
            const csrfToken = document.createElement('input');
            csrfToken.type = 'hidden';
            csrfToken.name = '_token';
            csrfToken.value = '{{ csrf_token() }}';
            form.appendChild(csrfToken);
            
            document.body.appendChild(form);
            form.submit();
        };

        window.handleDelete = function(companyId, name) {
            console.log('Delete clicked for company:', companyId, 'name:', name);
            if (confirm(`Are you sure you want to delete "${name}"? This action cannot be undone.`)) {
                closeContextMenu();
                
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/companies/${companyId}`;
                
                const csrfToken = document.createElement('input');
                csrfToken.type = 'hidden';
                csrfToken.name = '_token';
                csrfToken.value = '{{ csrf_token() }}';
                form.appendChild(csrfToken);
                
                const methodField = document.createElement('input');
                methodField.type = 'hidden';
                methodField.name = '_method';
                methodField.value = 'DELETE';
                form.appendChild(methodField);
                
                document.body.appendChild(form);
                form.submit();
            } else {
                closeContextMenu();
            }
        };

        // Clear any old context menus immediately when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Remove any existing old context menus
            const oldMenus = document.querySelectorAll('[id*="contextMenu"]');
            oldMenus.forEach(menu => menu.remove());

            // Add submit handlers to prevent double-clicking
            const createForm = document.getElementById('createCompanyForm');
            const createBtn = document.getElementById('createCompanyBtn');
            
            createForm.addEventListener('submit', function(e) {
                createBtn.disabled = true;
            });

            const editForm = document.getElementById('editCompanyForm');
            const updateBtn = document.getElementById('updateCompanyBtn');
            
            // Add license key form handler
            const licenseForm = document.getElementById('licenseKeyForm');
            const licenseBtn = document.getElementById('updateLicenseBtn');
            
            if (licenseForm) {
                licenseForm.addEventListener('submit', function(e) {
                    licenseBtn.disabled = true;
                    licenseBtn.textContent = 'Updating...';
                });
            }
            
            editForm.addEventListener('submit', function(e) {
                updateBtn.disabled = true;
            });
        });

        function showCompanyContextMenu(event, companyId, name, isActive, canDelete, licenseKey) {
            console.log('=== RIGHT CLICK DETECTED ===');
            console.log('Company:', companyId, 'Name:', name, 'Active:', isActive, 'Can Delete:', canDelete, 'License Key:', licenseKey);
            
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();
            
            // FORCE remove ANY existing context menus
            const allMenus = document.querySelectorAll('[id*="contextMenu"], .context-menu, [class*="context"]');
            allMenus.forEach(menu => menu.remove());
            
            // Create the context menu
            createCompanyContextMenu(event, companyId, name, isActive, canDelete, licenseKey);
        }

        function createCompanyContextMenu(event, companyId, name, isActive, canDelete, licenseKey) {
            console.log('Creating context menu for company:', companyId, 'name:', name, 'licenseKey:', licenseKey);
            
            // Create the context menu element
            const contextMenu = document.createElement('div');
            contextMenu.id = 'contextMenu';
            contextMenu.className = 'fixed bg-white rounded-md shadow-xl border border-gray-200 py-1 z-50 min-w-48 backdrop-blur-sm transition-all duration-150 transform opacity-100 scale-100';
            
            // Create header
            const header = document.createElement('div');
            header.className = 'px-3 py-2 border-b border-gray-100 bg-gray-50 rounded-t-md';
            header.innerHTML = `
                <div class="text-sm font-medium text-gray-900">${name}</div>
                <div class="text-xs text-gray-500">Company</div>
            `;
            contextMenu.appendChild(header);
            
            // Create actions container
            const actionsContainer = document.createElement('div');
            actionsContainer.className = 'py-1';
            
            // Create Edit button
            const editButton = document.createElement('a');
            editButton.href = '#';
            editButton.className = 'flex items-center px-3 py-2 text-sm text-gray-700 hover:bg-blue-50 hover:text-blue-700 transition-colors duration-150';
            editButton.innerHTML = `
                <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Edit Company
            `;
            editButton.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Edit button clicked! Company ID:', companyId);
                handleEdit(companyId);
            });
            actionsContainer.appendChild(editButton);
            
            // Create Manage License button
            const licenseButton = document.createElement('a');
            licenseButton.href = '#';
            licenseButton.className = 'flex items-center px-3 py-2 text-sm text-indigo-700 hover:bg-indigo-50 hover:text-indigo-800 transition-colors duration-150';
            licenseButton.innerHTML = `
                <svg class="w-4 h-4 mr-2 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
                Manage License
            `;
            licenseButton.addEventListener('click', function(e) {
                e.preventDefault();
                closeContextMenu();
                openLicenseKeyModal(companyId, name, licenseKey || '');
            });
            actionsContainer.appendChild(licenseButton);
            
            // Create divider
            const divider = document.createElement('div');
            divider.className = 'border-t border-gray-100 my-1';
            actionsContainer.appendChild(divider);
            
            // Create Toggle button
            const toggleButton = document.createElement('a');
            toggleButton.href = '#';
            toggleButton.className = 'flex items-center px-3 py-2 text-sm text-yellow-700 hover:bg-yellow-50 hover:text-yellow-800 transition-colors duration-150';
            toggleButton.innerHTML = `
                <svg class="w-4 h-4 mr-2 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                </svg>
                ${isActive === 'false' ? 'Activate' : 'Deactivate'}
            `;
            toggleButton.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Toggle button clicked! Company ID:', companyId);
                handleToggle(companyId);
            });
            actionsContainer.appendChild(toggleButton);
            
            // Create Delete button (only if no users/employees)
            if (canDelete === 'true') {
                const deleteButton = document.createElement('a');
                deleteButton.href = '#';
                deleteButton.className = 'flex items-center px-3 py-2 text-sm text-red-600 hover:bg-red-50 hover:text-red-700 transition-colors duration-150';
                deleteButton.innerHTML = `
                    <svg class="w-4 h-4 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                    Delete Company
                `;
                deleteButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    console.log('Delete button clicked! Company ID:', companyId, 'Name:', name);
                    handleDelete(companyId, name);
                });
                actionsContainer.appendChild(deleteButton);
            }
            
            contextMenu.appendChild(actionsContainer);
            document.body.appendChild(contextMenu);
            contextMenu.style.left = event.pageX + 'px';
            contextMenu.style.top = event.pageY + 'px';
            
            // Close menu when clicking elsewhere
            document.addEventListener('click', function closeMenu() {
                contextMenu.remove();
                document.removeEventListener('click', closeMenu);
            });
        }

        function openCreateModal() {
            document.getElementById('createCompanyModal').classList.remove('hidden');
            document.getElementById('name').focus();
        }

        function closeCreateModal() {
            document.getElementById('createCompanyModal').classList.add('hidden');
            document.getElementById('createCompanyForm').reset();
            document.getElementById('name-error').classList.add('hidden');
        }

        function closeEditModal() {
            document.getElementById('editCompanyModal').classList.add('hidden');
            document.getElementById('editCompanyForm').reset();
            document.getElementById('edit_name-error').classList.add('hidden');
        }

        function openLicenseKeyModal(companyId, companyName, currentLicenseKey) {
            document.getElementById('license_key').value = currentLicenseKey || '';
            document.getElementById('licenseKeyForm').dataset.companyId = companyId;
            
            // Clear any previous errors
            document.getElementById('license_key_error').classList.add('hidden');
            document.getElementById('license_key_error').textContent = '';
            
            // Hide license info by default
            document.getElementById('license_info').classList.add('hidden');
            
            // If there's a current license key, fetch and display its information
            if (currentLicenseKey) {
                fetch(`/api/license-info/${encodeURIComponent(currentLicenseKey)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.license) {
                            const license = data.license;
                            document.getElementById('license_company_name_display').textContent = companyName;
                            document.getElementById('license_cost').textContent = license.cost || 'N/A';
                            document.getElementById('license_activated').textContent = license.activated_at || 'N/A';
                            document.getElementById('license_expires').textContent = license.expires_at || 'N/A';
                            document.getElementById('license_users').textContent = license.max_employees || 'N/A';
                            document.getElementById('license_info').classList.remove('hidden');
                        }
                    })
                    .catch(error => console.error('Error fetching license info:', error));
            }
            
            document.getElementById('licenseKeyModal').classList.remove('hidden');
            document.getElementById('license_key').focus();
        }

        function closeLicenseKeyModal() {
            document.getElementById('licenseKeyModal').classList.add('hidden');
            document.getElementById('licenseKeyForm').reset();
            document.getElementById('license_key_error').classList.add('hidden');
        }

        function submitLicenseKey(event) {
            event.preventDefault();
            
            const form = event.target;
            const companyId = form.dataset.companyId;
            const licenseKey = document.getElementById('license_key').value;
            const errorElement = document.getElementById('license_key_error');
            const submitBtn = document.getElementById('updateLicenseBtn');
            
            // Clear previous errors
            errorElement.classList.add('hidden');
            errorElement.textContent = '';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Updating...';
            
            // Submit via AJAX
            fetch(`/companies/${companyId}/license-key`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ license_key: licenseKey })
            })
            .then(response => response.json())
            .then(data => {
                if (data.errors && data.errors.license_key) {
                    // Show error in modal without closing
                    errorElement.textContent = data.errors.license_key[0];
                    errorElement.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update License';
                } else {
                    // Success - close modal and reload page
                    closeLicenseKeyModal();
                    window.location.reload();
                }
            })
            .catch(error => {
                errorElement.textContent = 'An error occurred. Please try again.';
                errorElement.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update License';
            });
            
            return false;
        }

        // Close modal on Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCreateModal();
                closeEditModal();
                closeLicenseKeyModal();
            }
        });

        // Close modal when clicking outside
        document.getElementById('createCompanyModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeCreateModal();
            }
        });

        document.getElementById('editCompanyModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeEditModal();
            }
        });

        document.getElementById('licenseKeyModal')?.addEventListener('click', function(event) {
            if (event.target === this) {
                closeLicenseKeyModal();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            const statusSelect = document.getElementById('status');

            function updateFilters() {
                const url = new URL(window.location.origin + window.location.pathname);
                const params = new URLSearchParams();

                if (searchInput.value.trim()) params.set('search', searchInput.value.trim());
                if (statusSelect.value) params.set('status', statusSelect.value);

                url.search = params.toString();
                window.location.href = url.toString();
            }

            if (searchInput) {
                searchInput.addEventListener('input', debounce(updateFilters, 500));
            }
            if (statusSelect) {
                statusSelect.addEventListener('change', updateFilters);
            }

            document.getElementById('reset_filters').addEventListener('click', function() {
                window.location.href = '{{ route("companies.index") }}';
            });

            function debounce(func, wait) {
                let timeout;
                return function(...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func(...args), wait);
                };
            }

            // Auto-hide success message
            const successMessage = document.getElementById('success-message');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.opacity = '0';
                    setTimeout(() => successMessage.remove(), 300);
                }, 3000);
            }
        });
    </script>
</x-app-layout>
