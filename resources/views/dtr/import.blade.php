<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Import DTR (Daily Time Record)
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('dtr.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Back to DTR
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            
            <!-- Success/Error Messages -->
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if (session('warning'))
                <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-yellow-800">{{ session('warning') }}</p>
                            @if (session('import_errors'))
                                <div class="mt-2">
                                    <ul class="list-disc list-inside text-sm text-yellow-700 space-y-1">
                                        @foreach (array_slice(session('import_errors'), 0, 10) as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                        @if (count(session('import_errors')) > 10)
                                            <li><em>... and {{ count(session('import_errors')) - 10 }} more errors</em></li>
                                        @endif
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 rounded-md p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Template Download Card -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">Download Template</h3>
                            <p class="mt-1 max-w-2xl text-sm text-gray-500">
                                Download a simple Excel template with the required columns: Employee Number, Date, Time In, Time Out, Break In, Break Out.
                                Includes sample data showing both 12-hour (AM/PM) and 24-hour time formats.
                            </p>
                        </div>
                        <div class="flex-shrink-0">
                            <a href="{{ route('dtr.export-template') }}"
                               class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Download Template
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Form Card -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Upload DTR File</h3>
                    
                    <form action="{{ route('dtr.import.process') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                        @csrf
                        
                        <!-- File Upload -->
                        <div>
                            <label for="dtr_file" class="block text-sm font-medium text-gray-700">DTR File</label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-md" id="drop-zone">
                                <div class="space-y-1 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="dtr_file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500">
                                            <span>Upload a file</span>
                                            <input id="dtr_file" name="dtr_file" type="file" class="sr-only" accept=".xlsx,.xls,.csv" required>
                                        </label>
                                        <p class="pl-1">or drag and drop</p>
                                    </div>
                                    <p class="text-xs text-gray-500">Excel or CSV files up to 2MB</p>
                                    <div id="file-info" class="hidden mt-2 text-sm text-green-600"></div>
                                </div>
                            </div>
                            @error('dtr_file')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Options -->
                        <div class="space-y-4">
                            <div class="flex items-center">
                                <input id="overwrite_existing" name="overwrite_existing" type="checkbox" value="1" class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="overwrite_existing" class="ml-2 block text-sm text-gray-900">
                                    Overwrite existing time logs for the same date and employee
                                </label>
                            </div>
                            <p class="text-xs text-gray-500 ml-6">
                                If unchecked, existing time logs will be skipped and not updated.
                            </p>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('dtr.index') }}"
                               class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </a>
                            <button type="submit"
                                    class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                </svg>
                                Import DTR Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sample Format Card -->
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Expected File Format</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Employee Number</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time In</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time Out</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Break In</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Break Out</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">EMP-2025-0001</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2024-11-10</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">8:00 AM</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">5:00 PM</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">12:00 PM</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">1:00 PM</td>
                                </tr>
                                <tr class="bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">EMP-2025-0002</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2024-11-10</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">09:00</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">18:30</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">12:30</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">13:30</td>
                                </tr>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">EMP-2025-0003</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">2024-11-10</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">7:30AM</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">4:30PM</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-4 text-sm text-gray-600">
                        <p><strong>Notes:</strong></p>
                        <ul class="list-disc list-inside space-y-1 mt-2">
                            <li><strong>Employee Number is required</strong> to identify the employee (must match existing active employees)</li>
                            <li><strong>Time formats supported:</strong> Both 12-hour (8:00 AM, 5:00 PM) and 24-hour (09:00, 17:00) formats</li>
                            <li><strong>Date format:</strong> YYYY-MM-DD (e.g., 2024-11-10) or other standard date formats</li>
                            <li><strong>Break times are optional</strong> - leave empty if no specific breaks were taken</li>
                            <li><strong>Flexible time input:</strong> Supports "8AM", "8:00AM", "08:00", "8:00 AM" formats</li>
                            <li>The system will automatically calculate hours, overtime, and handle holidays/rest days</li>
                        </ul>
                    </div>
                </div>
            }        </div>
    </div>

    <!-- File Upload JavaScript -->
    <script>
        // File upload drag and drop functionality
        const fileInput = document.getElementById('dtr_file');
        const dropZone = document.getElementById('drop-zone');
        const fileInfo = document.getElementById('file-info');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            dropZone.classList.add('border-blue-400', 'bg-blue-50');
        }
        
        function unhighlight(e) {
            dropZone.classList.remove('border-blue-400', 'bg-blue-50');
        }
        
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                updateFileName(files[0]);
            }
        }
        
        fileInput.addEventListener('change', function(e) {
            if (e.target.files.length > 0) {
                updateFileName(e.target.files[0]);
            }
        });
        
        function updateFileName(file) {
            // Show file info
            fileInfo.classList.remove('hidden');
            fileInfo.innerHTML = `✓ Selected: <strong>${file.name}</strong> (${formatFileSize(file.size)})`;
            
            // Update drop zone to show success
            dropZone.classList.add('border-green-400', 'bg-green-50');
            dropZone.classList.remove('border-gray-300');
            
            console.log('File selected:', file.name, file.size); // Debug log
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Add form validation before submit
        const form = document.querySelector('form');
        form.addEventListener('submit', function(e) {
            if (!fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select a file to import.');
                return false;
            }
            
            console.log('Form submitting with file:', fileInput.files[0].name); // Debug log
        });
    </script>
</x-app-layout>
