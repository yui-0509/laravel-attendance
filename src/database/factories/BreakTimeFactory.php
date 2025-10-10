<?php

namespace Database\Factories;

use App\Models\BreakTime;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class BreakTimeFactory extends Factory
{
    protected $model = BreakTime::class;

    public function definition(): array
    {
        return [
            'break_start' => null,
            'break_end' => null,
        ];
    }

    public function onBreak(): self
    {
        return $this->state(function () {
            $breakStartTime = Carbon::today()->setTime(12, 0, 0);

            return [
                'break_start' => $breakStartTime->format('H:i:s'),
                'break_end' => null,
            ];
        });
    }
}
