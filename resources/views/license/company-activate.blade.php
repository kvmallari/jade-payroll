<x-guest-layout>
    <div class="mb-6 text-center">
        <h1 class="text-2xl font-bold text-gray-900">Activate Company License</h1>
        <p class="mt-2 text-sm text-gray-600">{{ $activationMessage ?? 'Enter your license key to activate the system' }}</p>
        @if($company)
            <p class="mt-1 text-sm font-medium text-gray-900">Company: {{ $company->name }}</p>
        @endif
    </div>

    @if($company && $company->license_key && !isset($isExpired))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-green-800">License Already Activated!</h3>
                    <div class="mt-2 text-sm text-green-700">
                        <p>Your company license has been activated. You can now access the system.</p>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('dashboard') }}" class="text-sm text-green-800 underline">
                            Go to Dashboard →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    @elseif(isset($isExpired) && $isExpired)
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">License Expired!</h3>
                    <div class="mt-2 text-sm text-red-700">
                        <p>{{ $activationMessage ?? 'Your company license has expired. Please enter a new license key below.' }}</p>
                    </div>
                </div>
            </div>
        </div>
    @elseif(isset($canActivate) && !$canActivate)
        <div class="mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-yellow-800">License Activation Required</h3>
                    <div class="mt-2 text-sm text-yellow-700">
                        <p>{{ $activationMessage ?? 'Your company needs a license to access the system.' }}</p>
                        <p class="mt-2">Only system administrators can activate the license. Please contact your system administrator.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4 text-center">
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="text-sm text-gray-600 underline">
                    Logout
                </button>
            </form>
        </div>
    @endif
    
    @if(isset($canActivate) && $canActivate)
        <form method="POST" action="{{ route('license.activate.store') }}">
            @csrf
            
            <div class="mb-4">
                <label for="license_key" class="block text-sm font-medium text-gray-700">
                    License Key
                </label>
                <textarea id="license_key" 
                        name="license_key" 
                        rows="3"
                        class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm placeholder-gray-400 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                        placeholder="Paste your license key here..."
                        required>{{ old('license_key', $expiredLicenseKey ?? '') }}</textarea>
                @error('license_key')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" 
                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                Activate License
            </button>
        </form>

        <div class="mt-6">
            <div class="text-sm text-gray-600">
                <p class="font-medium">Need a License Key?</p>
                <div class="mt-2 mb-2 p-3 bg-gray-50 rounded">
                    <p class="text-xs">Contact the super admin or developer to obtain a valid license key for your company.</p>
                </div>
            </div>
        </div>
    @endif

    @if(session('success'))
        <div class="mt-4 p-4 bg-green-50 border border-green-200 rounded-md">
            <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
    @endif
    
    {{-- @if(session('warning'))
        <div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-md">
            <p class="text-sm text-yellow-800">{{ session('warning') }}</p>
        </div>
    @endif --}}

    @if(!isset($canActivate) || $canActivate !== false)
    <div class="mt-4 text-center">
        <form method="POST" action="{{ route('logout') }}" class="inline">
            @csrf
            <button type="submit" class="text-sm text-gray-600 underline">
                Logout
            </button>
        </form>
    </div>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('license_key');
            const form = textarea?.closest('form');
            
            if (textarea && form) {
                textarea.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        form.submit();
                    }
                });
            }
        });
    </script>
</x-guest-layout>
