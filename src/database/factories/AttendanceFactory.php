<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $date = Carbon::today();

        return [
            'user_id' => User::factory(),
            'date' => $date->toDateString(),
            'clock_in' => null,
            'clock_out' => null,
        ];
    }

    public function working(): self
    {
        return $this->state(function () {
            $clockInTime = Carbon::today()->setTime(9, 0, 0);

            return ['clock_in' => $clockInTime->format('H:i:s'), 'clock_out' => null];
        });
    }

    public function finished(): self
    {
        return $this->state(function () {
            $clockInTime = Carbon::today()->setTime(9, 0, 0);
            $clockOutTime = Carbon::today()->setTime(17, 0, 0);

            return ['clock_in' => $clockInTime->format('H:i:s'),
                'clock_out' => $clockOutTime->format('H:i:s')];
        });
    }
}
