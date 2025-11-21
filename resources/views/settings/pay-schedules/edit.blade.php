<x-app-layout>
<div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configure {{ $paySchedule->name }} Schedule</h1>
            <a href="{{ route('settings.pay-schedules.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md">
                Back to Pay Schedules
            </a>
        </div>

    <div class="bg-white rounded-lg shadow p-6">
        <form method="POST" action="{{ route('settings.pay-schedules.update', $paySchedule) }}">
            @csrf
            @method('PUT')

            <!-- Schedule Information -->
            <div class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Schedule Name</label>
                        <input type="text" name="name" id="name" required
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               value="{{ old('name', $paySchedule->name) }}">
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Schedule Type</label>
                        <div class="p-3 bg-gray-50 rounded-md">
                            <span class="text-sm font-medium text-gray-900">
                                {{ ucfirst(str_replace('_', ' ', $paySchedule->type)) }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                    <textarea name="description" id="description" rows="2"
                              class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $paySchedule->description) }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <!-- Schedule Type Information -->
            <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                <h3 class="text-lg font-medium text-blue-900 mb-2">Configure {{ ucfirst(str_replace('_', ' ', $paySchedule->type)) }} Schedule</h3>
                <p class="text-sm text-blue-700">Set up the cutoff periods and pay dates for this schedule</p>
            </div>

            @if($paySchedule->type === 'weekly')
                <!-- Weekly Schedule Configuration -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label for="cutoff_start_day" class="block text-sm font-medium text-gray-700 mb-2">Cut-off Start Day</label>
                        <select name="cutoff_start_day" id="cutoff_start_day" 
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Select Start Day</option>
                            @php
                                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                $currentStart = old('cutoff_start_day', $paySchedule->cutoff_periods[0]['start_day'] ?? '');
                            @endphp
                            @foreach($days as $day)
                                <option value="{{ $day }}" {{ $currentStart === $day ? 'selected' : '' }}>
                                    {{ ucfirst($day) }}
                                </option>
                            @endforeach
                        </select>
                        @error('cutoff_start_day')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="cutoff_end_day" class="block text-sm font-medium text-gray-700 mb-2">Cut-off End Day</label>
                        <select name="cutoff_end_day" id="cutoff_end_day" 
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Select End Day</option>
                            @php
                                $currentEnd = old('cutoff_end_day', $paySchedule->cutoff_periods[0]['end_day'] ?? '');
                            @endphp
                            @foreach($days as $day)
                                <option value="{{ $day }}" {{ $currentEnd === $day ? 'selected' : '' }}>
                                    {{ ucfirst($day) }}
                                </option>
                            @endforeach
                        </select>
                        @error('cutoff_end_day')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="pay_day" class="block text-sm font-medium text-gray-700 mb-2">Pay Day</label>
                        <select name="pay_day" id="pay_day" 
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Select Pay Day</option>
                            @php
                                $currentPayDay = old('pay_day', $paySchedule->cutoff_periods[0]['pay_day'] ?? '');
                            @endphp
                            @foreach($days as $day)
                                <option value="{{ $day }}" {{ $currentPayDay === $day ? 'selected' : '' }}>
                                    {{ ucfirst($day) }}
                                </option>
                            @endforeach
                        </select>
                        @error('pay_day')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

            @elseif($paySchedule->type === 'semi_monthly')
                <!-- Semi-Monthly Schedule Configuration -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                    <!-- First Cutoff Period -->
                    <div class="border rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">1st Cut-off Period</h4>
                        <div class="space-y-4">
                            <div>
                                <label for="cutoff_1_start" class="block text-sm font-medium text-gray-700 mb-2">Start Day</label>
                                <input type="text" name="cutoff_1_start" id="cutoff_1_start" placeholder="Day (1-31) or EOM"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       value="{{ old('cutoff_1_start', $paySchedule->cutoff_periods[0]['start_day'] ?? '') }}" required>
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                @error('cutoff_1_start')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="cutoff_1_end" class="block text-sm font-medium text-gray-700 mb-2">End Day</label>
                                <input type="text" name="cutoff_1_end" id="cutoff_1_end" placeholder="Day (1-31) or EOM"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       value="{{ old('cutoff_1_end', $paySchedule->cutoff_periods[0]['end_day'] ?? '') }}" required>
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                @error('cutoff_1_end')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="pay_date_1" class="block text-sm font-medium text-gray-700 mb-2">Pay Date</label>
                                <input type="text" name="pay_date_1" id="pay_date_1" placeholder="Day (1-31) or EOM"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       value="{{ old('pay_date_1', $paySchedule->cutoff_periods[0]['pay_date'] ?? '') }}" required>
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                @error('pay_date_1')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <!-- Second Cutoff Period -->
                    <div class="border rounded-lg p-4">
                        <h4 class="text-md font-medium text-gray-900 mb-4">2nd Cut-off Period</h4>
                        <div class="space-y-4">
                            <div>
                                <label for="cutoff_2_start" class="block text-sm font-medium text-gray-700 mb-2">Start Day</label>
                                <input type="text" name="cutoff_2_start" id="cutoff_2_start" placeholder="Day (1-31) or EOM"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       value="{{ old('cutoff_2_start', $paySchedule->cutoff_periods[1]['start_day'] ?? '') }}" required>
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                @error('cutoff_2_start')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="cutoff_2_end" class="block text-sm font-medium text-gray-700 mb-2">End Day</label>
                                <input type="text" name="cutoff_2_end" id="cutoff_2_end" placeholder="Day (1-31) or EOM"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       value="{{ old('cutoff_2_end', $paySchedule->cutoff_periods[1]['end_day'] ?? '') }}" required>
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                @error('cutoff_2_end')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="pay_date_2" class="block text-sm font-medium text-gray-700 mb-2">Pay Date</label>
                                <input type="text" name="pay_date_2" id="pay_date_2" placeholder="Day (1-31) or EOM"
                                       class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                                       value="{{ old('pay_date_2', $paySchedule->cutoff_periods[1]['pay_date'] ?? '') }}" required>
                                <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                                @error('pay_date_2')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

            @elseif($paySchedule->type === 'monthly')
                <!-- Monthly Schedule Configuration -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label for="cutoff_start_day" class="block text-sm font-medium text-gray-700 mb-2">Cut-off Start Day</label>
                        <input type="text" name="cutoff_start_day" id="cutoff_start_day" placeholder="Day (1-31) or EOM"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               value="{{ old('cutoff_start_day', $paySchedule->cutoff_periods[0]['start_day'] ?? '') }}" required>
                        <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                        @error('cutoff_start_day')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="cutoff_end_day" class="block text-sm font-medium text-gray-700 mb-2">Cut-off End Day</label>
                        <input type="text" name="cutoff_end_day" id="cutoff_end_day" placeholder="Day (1-31) or EOM"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               value="{{ old('cutoff_end_day', $paySchedule->cutoff_periods[0]['end_day'] ?? '') }}" required>
                        <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                        @error('cutoff_end_day')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="pay_date" class="block text-sm font-medium text-gray-700 mb-2">Pay Date</label>
                        <input type="text" name="pay_date" id="pay_date" placeholder="Day (1-31) or EOM"
                               class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" 
                               value="{{ old('pay_date', $paySchedule->cutoff_periods[0]['pay_date'] ?? '') }}" required>
                        <div class="text-xs text-gray-500 mt-1">Enter 1-31 or 'EOM' for end of month</div>
                        @error('pay_date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            @endif

            <!-- Holiday/Weekend Settings -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                <h4 class="text-md font-medium text-gray-900 mb-4">Holiday & Weekend Settings</h4>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="flex items-center">
                        <input type="checkbox" name="move_if_holiday" id="move_if_holiday" value="1" 
                               {{ old('move_if_holiday', $paySchedule->move_if_holiday) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <label for="move_if_holiday" class="ml-2 block text-sm text-gray-700">Move if Holiday</label>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" name="move_if_weekend" id="move_if_weekend" value="1" 
                               {{ old('move_if_weekend', $paySchedule->move_if_weekend) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        <label for="move_if_weekend" class="ml-2 block text-sm text-gray-700">Move if Weekend</label>
                    </div>

                    <div>
                        <label for="move_direction" class="block text-sm font-medium text-gray-700 mb-2">Move Direction</label>
                        <select name="move_direction" id="move_direction" 
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="before" {{ old('move_direction', $paySchedule->move_direction) === 'before' ? 'selected' : '' }}>Before</option>
                            <option value="after" {{ old('move_direction', $paySchedule->move_direction) === 'after' ? 'selected' : '' }}>After</option>
                        </select>
                        @error('move_direction')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            <!-- Status -->
            <div class="mb-6">
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" id="is_active" value="1" 
                           {{ old('is_active', $paySchedule->is_active) ? 'checked' : '' }}
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <label for="is_active" class="ml-2 block text-sm text-gray-700">Active</label>
                </div>
            </div>

            <div class="mt-8 flex justify-end space-x-3">
                <a href="{{ route('settings.pay-schedules.index') }}" 
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                    Cancel
                </a>
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md">
                    Update Schedule
                </button>
            </div>
        </form>
    </div>
    </div>
</div>
</x-app-layout>
