<x-app-layout>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Pay Schedule Details</h1>
            <div class="flex space-x-2">
                <a href="{{ route('settings.pay-schedules.edit', $paySchedule) }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    dsa
                </a>
                <a href="{{ route('settings.pay-schedules.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                    Back to List
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">{{ $paySchedule->name }}</h3>
            <p class="text-sm text-gray-500">{{ $paySchedule->code }}</p>
        </div>
        
        <div class="px-6 py-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Basic Information</h4>
                    <dl class="space-y-2">
                        <div>
                            <dt class="text-sm text-gray-500">Description</dt>
                            <dd class="text-sm text-gray-900">{{ $paySchedule->description ?: 'No description' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Status</dt>
                            <dd>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    {{ $paySchedule->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $paySchedule->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                @if($paySchedule->is_system_default)
                                    <span class="ml-2 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                        System Default
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Frequency</dt>
                            <dd class="text-sm text-gray-900 capitalize">{{ str_replace('_', ' ', $paySchedule->frequency) }}</dd>
                        </div>
                        @if($paySchedule->cutoff_start_day)
                            <div>
                                <dt class="text-sm text-gray-500">Cutoff Start Day</dt>
                                <dd class="text-sm text-gray-900">{{ $paySchedule->cutoff_start_day }}</dd>
                            </div>
                        @endif
                        @if($paySchedule->cutoff_end_day)
                            <div>
                                <dt class="text-sm text-gray-500">Cutoff End Day</dt>
                                <dd class="text-sm text-gray-900">{{ $paySchedule->cutoff_end_day }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Pay Date Settings</h4>
                    <dl class="space-y-2">
                        <div>
                            <dt class="text-sm text-gray-500">Pay Date</dt>
                            <dd class="text-sm text-gray-900">{{ $paySchedule->pay_date }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Weekend Adjustment</dt>
                            <dd class="text-sm text-gray-900 capitalize">{{ str_replace('_', ' ', $paySchedule->weekend_adjustment ?? 'none') }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-gray-500">Holiday Adjustment</dt>
                            <dd class="text-sm text-gray-900 capitalize">{{ str_replace('_', ' ', $paySchedule->holiday_adjustment ?? 'none') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            @if($paySchedule->notes)
                <div>
                    <h4 class="text-sm font-medium text-gray-900 mb-2">Notes</h4>
                    <p class="text-sm text-gray-700">{{ $paySchedule->notes }}</p>
                </div>
            @endif

            <div>
                <h4 class="text-sm font-medium text-gray-900 mb-2">Timestamps</h4>
                <dl class="space-y-1 text-xs text-gray-500">
                    <div>
                        <dt class="inline">Created:</dt>
                        <dd class="inline">{{ $paySchedule->created_at->format('M j, Y g:i A') }}</dd>
                    </div>
                    <div>
                        <dt class="inline">Updated:</dt>
                        <dd class="inline">{{ $paySchedule->updated_at->format('M j, Y g:i A') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
    </div>
</div>
</x-app-layout>
