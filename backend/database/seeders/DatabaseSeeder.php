<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\User;
use App\Models\UserStats;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $alex = User::firstOrCreate(
            ['email' => 'alex@example.com'],
            [
                'name' => 'Alex Demo',
                'username' => 'alex',
                'phone_e164' => '+15551110001',
                'phone_hash' => hash('sha256', '+15551110001'),
                'email_hash' => hash('sha256', 'alex@example.com'),
                'password' => Hash::make('password'),
            ]
        );
        UserStats::firstOrCreate(['user_id' => $alex->id]);
        Device::firstOrCreate(
            ['user_id' => $alex->id, 'device_uuid' => 'demo-ios-alex'],
            [
                'platform' => 'ios',
                'device_name' => "Alex's iPhone",
                'app_version' => '1.0.0',
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        $jamie = User::firstOrCreate(
            ['email' => 'jamie@example.com'],
            [
                'name' => 'Jamie Demo',
                'username' => 'jamie',
                'phone_e164' => '+15551110002',
                'phone_hash' => hash('sha256', '+15551110002'),
                'email_hash' => hash('sha256', 'jamie@example.com'),
                'password' => Hash::make('password'),
            ]
        );
        UserStats::firstOrCreate(['user_id' => $jamie->id]);
        Device::firstOrCreate(
            ['user_id' => $jamie->id, 'device_uuid' => 'demo-android-jamie'],
            [
                'platform' => 'android',
                'device_name' => "Jamie's Pixel",
                'app_version' => '1.0.0',
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        $this->command?->info("Seeded users alex@example.com / jamie@example.com (password: 'password').");
    }
}
