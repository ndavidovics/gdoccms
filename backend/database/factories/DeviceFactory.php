<?php

namespace Database\Factories;

use App\Models\Device;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DeviceFactory extends Factory
{
    protected $model = Device::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'device_uuid' => (string) Str::uuid(),
            'platform' => $this->faker->randomElement(['ios', 'android']),
            'device_name' => $this->faker->word().' device',
            'app_version' => '1.0.0',
            'is_active' => true,
            'last_seen_at' => now(),
        ];
    }
}
