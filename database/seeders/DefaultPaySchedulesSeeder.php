<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PaySchedule;

class DefaultPaySchedulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing schedules
        PaySchedule::truncate();

        // Create default schedules
        $defaultSchedules = [
            [
                'name' => 'Standard Weekly Schedule',
                'type' => 'weekly',
                'description' => 'Monday to Friday weekly schedule with Friday payday',
                'cutoff_periods' => [
                    [
                        'start_day' => 'monday',
                        'end_day' => 'friday',
                        'pay_day' => 'friday'
                    ]
                ],
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Standard Semi-Monthly Schedule',
                'type' => 'semi_monthly',
                'description' => '1st-15th and 16th-end of month with standard pay dates',
                'cutoff_periods' => [
                    [
                        'start_day' => 1,
                        'end_day' => 15,
                        'pay_date' => 20
                    ],
                    [
                        'start_day' => 16,
                        'end_day' => 31,
                        'pay_date' => 5 // 5th of next month
                    ]
                ],
                'is_default' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Alternative Semi-Monthly Schedule',
                'type' => 'semi_monthly',
                'description' => 'Different cutoff periods for special departments',
                'cutoff_periods' => [
                    [
                        'start_day' => 21,
                        'end_day' => 5,
                        'pay_date' => 10
                    ],
                    [
                        'start_day' => 6,
                        'end_day' => 20,
                        'pay_date' => 25
                    ]
                ],
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'name' => 'Standard Monthly Schedule',
                'type' => 'monthly',
                'description' => 'Full month schedule with end-of-month payday',
                'cutoff_periods' => [
                    [
                        'start_day' => 1,
                        'end_day' => 31,
                        'pay_date' => 31
                    ]
                ],
                'is_default' => true,
                'sort_order' => 1,
            ],
        ];

        foreach ($defaultSchedules as $schedule) {
            PaySchedule::create(array_merge($schedule, [
                'move_if_holiday' => true,
                'move_if_weekend' => true,
                'move_direction' => 'before',
                'is_active' => true,
                'created_by' => 1, // Assuming admin user ID is 1
            ]));
        }
    }
}
