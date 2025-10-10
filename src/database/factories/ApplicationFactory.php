<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'admin_id' => null,
            'remark' => '遅延のため',
            'status' => 'pending',
            'approved_at' => null,
        ];
    }
}
