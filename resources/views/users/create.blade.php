@extends('layouts.app')

@section('content')
<div class="py-6">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('users.store') }}" class="space-y-6">
            @csrf
            @if(request('company'))
                <input type="hidden" name="company" value="{{ request('company') }}">
            @endif

            <!-- Account Information -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('name') border-red-500 @enderror">
                            @error('name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email Address <span class="text-red-500">*</span></label>
                            @php
                                // Get email domain from system settings
                                $emailDomain = \App\Models\Setting::get('email_domain', 'gmail.com');
                            @endphp
                            <input type="text" name="email" id="email" value="&#64;{{ $emailDomain }}" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('email') border-red-500 @enderror">
                            @error('email')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        @if(auth()->user()->isSuperAdmin())
                        <div class="md:col-span-2">
                            <label for="company_id" class="block text-sm font-medium text-gray-700">Company <span class="text-red-500">*</span></label>
                            @php
                                $preselectedCompany = null;
                                if (request('company')) {
                                    $preselectedCompany = $companies->first(function($c) {
                                        return strtolower($c->name) === strtolower(request('company'));
                                    });
                                }
                                $isPreselected = $preselectedCompany !== null;
                            @endphp
                            <select name="company_id" id="company_id" required
                                    {{ $isPreselected ? 'disabled' : '' }}
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm {{ $isPreselected ? 'bg-gray-100' : '' }} @error('company_id') border-red-500 @enderror">
                                <option value="">Select a company</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" 
                                        {{ ($isPreselected && $company->id === $preselectedCompany->id) || old('company_id') == $company->id ? 'selected' : '' }}>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($isPreselected)
                                <input type="hidden" name="company_id" value="{{ $preselectedCompany->id }}">
                                <p class="mt-1 text-xs text-gray-500">Company is preselected based on your filter</p>
                            @endif
                            @error('company_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                        @else
                        <!-- System Admin: Company auto-assigned -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Company</label>
                            <input type="text" value="{{ $companies->first()->name ?? 'N/A' }}" disabled
                                   class="mt-1 block w-full rounded-md border-gray-300 bg-gray-100 shadow-sm sm:text-sm">
                            <p class="mt-1 text-xs text-gray-500">Users will be created for your assigned company</p>
                            <input type="hidden" name="company_id" value="{{ $companies->first()->id ?? '' }}">
                        </div>
                        @endif

                        <div class="md:col-span-2">
                            <label for="role" class="block text-sm font-medium text-gray-700">Role <span class="text-red-500">*</span></label>
                            <select name="role" id="role" required
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('role') border-red-500 @enderror">
                                <option value="">Select a role</option>
                                @foreach($roles as $role)
                                    @if($role->name !== 'Employee')
                                        <option value="{{ $role->name }}" {{ old('role') == $role->name ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('role')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium text-gray-700">Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="password" id="password" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-10 @error('password') border-red-500 @enderror">
                                <button type="button" onclick="togglePassword('password')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg id="password-eye" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <svg id="password-eye-slash" class="h-5 w-5 text-gray-400 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                                    </svg>
                                </button>
                            </div>
                            @error('password')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirm Password <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input type="password" name="password_confirmation" id="password_confirmation" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm pr-10">
                                <button type="button" onclick="togglePassword('password_confirmation')" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg id="password_confirmation-eye" class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <svg id="password_confirmation-eye-slash" class="h-5 w-5 text-gray-400 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex items-center justify-end space-x-4">
                        <a href="{{ route('users.index') }}" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded">
                            Cancel
                        </a>
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            Create User
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const emailDomain = '@' + '{{ $emailDomain }}';
const emailInput = document.getElementById('email');

// Initialize the email field with domain
if (!emailInput.value || emailInput.value === emailDomain) {
    emailInput.value = emailDomain;
}

// Set initial value if there's an old value
@if(old('email'))
    emailInput.value = '{{ old('email') }}';
@endif

// Handle input to prevent domain deletion
emailInput.addEventListener('input', function(e) {
    const value = this.value;
    
    // Always ensure the domain is present
    if (!value.includes(emailDomain)) {
        this.value = emailDomain;
        // Move cursor to the beginning (before @)
        this.setSelectionRange(0, 0);
    } else if (!value.endsWith(emailDomain)) {
        // If domain is not at the end, fix it
        const atIndex = value.indexOf('@');
        if (atIndex > -1) {
            const username = value.substring(0, atIndex);
            this.value = username + emailDomain;
        } else {
            this.value = emailDomain;
        }
    }
});

// Handle keydown to prevent deleting the @ and domain
emailInput.addEventListener('keydown', function(e) {
    const value = this.value;
    const cursorPos = this.selectionStart;
    const atIndex = value.indexOf('@');
    
    // Prevent deletion of @ and anything after it
    if (atIndex > -1) {
        // Backspace
        if (e.key === 'Backspace' && cursorPos <= atIndex + 1) {
            if (cursorPos === atIndex + 1) {
                e.preventDefault();
                this.setSelectionRange(atIndex, atIndex);
            }
        }
        // Delete
        if (e.key === 'Delete' && cursorPos >= atIndex) {
            e.preventDefault();
        }
        // Arrow right - don't allow cursor past @
        if (e.key === 'ArrowRight' && cursorPos >= atIndex) {
            e.preventDefault();
        }
        // Prevent selection of domain part
        if (cursorPos > atIndex && this.selectionEnd > atIndex) {
            if (e.key !== 'ArrowLeft' && e.key !== 'Home') {
                this.setSelectionRange(atIndex, atIndex);
            }
        }
    }
});

// Handle click to prevent cursor placement after @
emailInput.addEventListener('click', function(e) {
    const atIndex = this.value.indexOf('@');
    if (atIndex > -1 && this.selectionStart > atIndex) {
        this.setSelectionRange(atIndex, atIndex);
    }
});

// Handle selection to prevent selecting domain part
emailInput.addEventListener('select', function(e) {
    const atIndex = this.value.indexOf('@');
    if (atIndex > -1 && this.selectionEnd > atIndex) {
        this.setSelectionRange(Math.min(this.selectionStart, atIndex), atIndex);
    }
});

// Handle paste to preserve domain
emailInput.addEventListener('paste', function(e) {
    e.preventDefault();
    const pastedText = (e.clipboardData || window.clipboardData).getData('text');
    const atIndex = this.value.indexOf('@');
    const cursorPos = this.selectionStart;
    
    if (atIndex > -1 && cursorPos <= atIndex) {
        const before = this.value.substring(0, cursorPos);
        const after = this.value.substring(this.selectionEnd, atIndex);
        const username = before + pastedText + after;
        this.value = username + emailDomain;
        const newCursorPos = (before + pastedText).length;
        this.setSelectionRange(newCursorPos, newCursorPos);
    }
});

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const eyeIcon = document.getElementById(fieldId + '-eye');
    const eyeSlashIcon = document.getElementById(fieldId + '-eye-slash');
    
    if (field.type === 'password') {
        field.type = 'text';
        eyeIcon.classList.add('hidden');
        eyeSlashIcon.classList.remove('hidden');
    } else {
        field.type = 'password';
        eyeIcon.classList.remove('hidden');
        eyeSlashIcon.classList.add('hidden');
    }
}
</script>
@endsection