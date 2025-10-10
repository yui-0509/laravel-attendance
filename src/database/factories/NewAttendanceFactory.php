<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Attendance;
use App\Models\NewAttendance;
use Illuminate\Database\Eloquent\Factories\Factory;

class NewAttendanceFactory extends Factory
{
    protected $model = NewAttendance::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'attendance_id' => Attendance::factory(),
            'new_clock_in' => '10:00:00',
            'new_clock_out' => '18:00:00',
        ];
    }
}
