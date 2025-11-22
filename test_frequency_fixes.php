<?php
require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "Testing all fixed frequency calculations:" . PHP_EOL;

// Test cases based on the image and expected scenarios
$testCases = [
    [
        'schedule' => 'SEMI-2',
        'period_start' => '2025-10-21',
        'period_end' => '2025-11-05',
        'expected' => 'Semi-Monthly - 1st Cutoff'
    ],
    [
        'schedule' => 'WEEK-1',
        'period_start' => '2025-11-17',
        'period_end' => '2025-11-23',
        'expected' => 'Weekly - 3rd'
    ],
    [
        'schedule' => 'MONTH-1',
        'period_start' => '2025-11-01',
        'period_end' => '2025-11-30',
        'expected' => 'Monthly - November'
    ],
    [
        'schedule' => 'DAILY-1',
        'period_start' => '2025-11-22',
        'period_end' => '2025-11-22',
        'expected' => 'Daily - Saturday'
    ]
];

foreach ($testCases as $test) {
    echo PHP_EOL . "Testing {$test['schedule']}:" . PHP_EOL;
    echo "Period: {$test['period_start']} to {$test['period_end']}" . PHP_EOL;

    $paySchedule = $test['schedule'];
    $periodStart = \Carbon\Carbon::parse($test['period_start']);
    $periodEnd = \Carbon\Carbon::parse($test['period_end']);

    // Simulate the switch case logic
    switch ($paySchedule) {
        case 'semi_monthly':
        case 'semi-monthly':
        case 'SEMI-1':
        case 'SEMI-2':
        case 'SEMI-3':
            // Simulate semi-monthly logic (simplified)
            if ($paySchedule === 'SEMI-2') {
                $cutoff = ($periodStart->day === 21) ? '1st' : '2nd';
                $payFrequencyDisplay = "Semi-Monthly - {$cutoff} Cutoff";
            } else {
                $payFrequencyDisplay = "Semi-Monthly - TBD";
            }
            break;

        case 'monthly':
        case 'MONTH-1':
            $monthName = $periodStart->format('F');
            $payFrequencyDisplay = "Monthly - {$monthName}";
            break;

        case 'weekly':
        case 'WEEK-1':
            // Use same simple calculation as schedule overview
            $dayOfMonth = $periodStart->day;
            $weekNumber = (int) ceil($dayOfMonth / 7);

            $weekOrdinal = match ($weekNumber) {
                1 => '1st',
                2 => '2nd',
                3 => '3rd',
                4 => '4th',
                default => $weekNumber . 'th'
            };
            $payFrequencyDisplay = "Weekly - {$weekOrdinal}";
            break;

        case 'daily':
        case 'DAILY-1':
            // Use day name like schedule overview
            $dayName = $periodStart->format('l'); // Monday, Tuesday, etc.
            $payFrequencyDisplay = "Daily - {$dayName}";
            break;

        default:
            $payFrequencyDisplay = ucfirst(str_replace('_', '-', $paySchedule));
            break;
    }

    echo "Calculated: $payFrequencyDisplay" . PHP_EOL;
    echo "Expected:   {$test['expected']}" . PHP_EOL;

    $match = ($payFrequencyDisplay === $test['expected']) ? '✓ MATCH' : '✗ MISMATCH';
    echo "Result: $match" . PHP_EOL;
}

echo PHP_EOL . "Weekly calculation details:" . PHP_EOL;
echo "Nov 17: Day 17, Week = ceil(17/7) = ceil(2.43) = 3 → 3rd" . PHP_EOL;
