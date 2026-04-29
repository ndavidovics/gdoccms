<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $email = $this->faker->unique()->safeEmail();
        $phone = '+1'.$this->faker->numerify('##########');

        return [
            'name' => $this->faker->name(),
            'username' => Str::slug($this->faker->unique()->userName()),
            'email' => $email,
            'phone_e164' => $phone,
            'phone_hash' => hash('sha256', $phone),
            'email_hash' => hash('sha256', strtolower($email)),
            'password' => Hash::make('password'),
        ];
    }
}
