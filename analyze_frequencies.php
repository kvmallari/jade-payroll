<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Analyzing all PaySchedule configurations:" . PHP_EOL;

$schedules = App\Models\PaySchedule::all();
foreach ($schedules as $schedule) {
    echo PHP_EOL . "Schedule: {$schedule->name} (Type: {$schedule->type})" . PHP_EOL;

    if ($schedule->cutoff_periods) {
        echo "Cutoff Periods: " . json_encode($schedule->cutoff_periods, JSON_PRETTY_PRINT) . PHP_EOL;
    }

    if ($schedule->name === 'WEEK-1') {
        echo "*** This is the weekly schedule from the image ***" . PHP_EOL;
    }
}

echo PHP_EOL . "Current date context: November 22, 2025 (Saturday)" . PHP_EOL;
echo "Period shown in image: Nov 17 - 23, 2025" . PHP_EOL;

// Test the current weekly logic for this period
$periodStart = \Carbon\Carbon::parse('2025-11-17');
$periodEnd = \Carbon\Carbon::parse('2025-11-23');

echo PHP_EOL . "Testing current weekly logic:" . PHP_EOL;
echo "Period: {$periodStart->format('Y-m-d')} to {$periodEnd->format('Y-m-d')}" . PHP_EOL;
echo "Period Start Day: {$periodStart->day}" . PHP_EOL;

// Simulate current blade template logic for weekly
$saturdayEnd = $periodEnd->copy();
while ($saturdayEnd->dayOfWeek !== 6) { // 6 = Saturday
    $saturdayEnd->addDay();
}
echo "Saturday End: {$saturdayEnd->format('Y-m-d')}" . PHP_EOL;

// Find all Saturdays in this month to determine week number
$monthStart = $saturdayEnd->copy()->startOfMonth();
echo "Month Start: {$monthStart->format('Y-m-d')}" . PHP_EOL;

$weekNumber = 0;
$currentSaturday = $monthStart->copy();

// Find first Saturday of the month
while ($currentSaturday->dayOfWeek !== 6) {
    $currentSaturday->addDay();
}
echo "First Saturday of month: {$currentSaturday->format('Y-m-d')}" . PHP_EOL;

// Count Saturdays until we reach our target Saturday
while ($currentSaturday->lte($saturdayEnd)) {
    $weekNumber++;
    echo "Saturday #{$weekNumber}: {$currentSaturday->format('Y-m-d')}" . PHP_EOL;
    if ($currentSaturday->format('Y-m-d') === $saturdayEnd->format('Y-m-d')) {
        break;
    }
    $currentSaturday->addWeek();
}

$weekOrdinal = match ($weekNumber) {
    1 => '1st',
    2 => '2nd',
    3 => '3rd',
    4 => '4th',
    default => '5th'
};

echo PHP_EOL . "Current blade logic result: Weekly - {$weekOrdinal}" . PHP_EOL;
echo "Schedule overview shows: 3rd Week" . PHP_EOL;
echo "Mismatch detected!" . PHP_EOL;
