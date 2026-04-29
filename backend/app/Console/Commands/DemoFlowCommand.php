<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Session;
use App\Models\User;
use App\Models\UserStats;
use App\Services\DeviceService;
use App\Services\SessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * End-to-end simulation: creates two users, runs both happy-path and
 * protection-disabled flows, prints state at each step.
 *
 *   php artisan demo:flow            # happy path
 *   php artisan demo:flow --fail     # protection_disabled fail path
 */
class DemoFlowCommand extends Command
{
    protected $signature = 'demo:flow {--fail : simulate a failure mid-session}';

    protected $description = 'End-to-end simulation of the offline-session lifecycle.';

    public function handle(SessionService $sessions, DeviceService $devices): int
    {
        DB::transaction(function () use ($sessions, $devices) {
            [$alex, $alexDevice] = $this->ensureUser('Alex Demo', 'demo-alex@example.com', 'demo-ios-alex', 'ios');
            [$jamie, $jamieDevice] = $this->ensureUser('Jamie Demo', 'demo-jamie@example.com', 'demo-android-jamie', 'android');

            $created = $sessions->createForHost($alex, $alexDevice);
            $session = $created['session'];
            $token = $created['join_token'];
            $this->line("Created session {$session->uuid} (state={$session->state})");

            $session = $sessions->joinByToken($jamie, $jamieDevice, $token);
            $this->line("Jamie joined (state={$session->state})");

            $sessions->confirmLock($alex, $session);
            $session = $sessions->confirmLock($jamie, $session);
            $this->line("Both locked, session ".$session->state);

            $hb = fn ($device, $vpn = true) => [
                'device_uuid' => $device->device_uuid,
                'vpn_active' => $vpn,
                'dnd_active' => true,
                'notification_suppression_active' => true,
                'network_block_active' => $vpn,
                'app_version' => '1.0.0',
                'platform' => $device->platform,
                'client_timestamp' => now()->toIso8601String(),
            ];

            $sessions->recordHeartbeat($alex, $session, $alexDevice, $hb($alexDevice));
            $session = $sessions->recordHeartbeat($jamie, $session, $jamieDevice, $hb($jamieDevice));
            $this->line("After heartbeat (state={$session->state})");

            if ($this->option('fail')) {
                $session = $sessions->recordHeartbeat($jamie, $session, $jamieDevice, $hb($jamieDevice, vpn: false));
                $this->error("Failed: state={$session->state}, reason={$session->failure_reason}");

                return;
            }

            $sessions->requestEnd($alex, $session);
            $sessions->confirmEnd($alex, $session);
            $session = $sessions->confirmEnd($jamie, $session);
            $this->info("Final state={$session->state}, duration_seconds={$session->durationSeconds()}");
        });

        return self::SUCCESS;
    }

    /**
     * @return array{0: User, 1: Device}
     */
    private function ensureUser(string $name, string $email, string $deviceUuid, string $platform): array
    {
        $user = User::firstOrCreate(['email' => $email], [
            'name' => $name,
            'username' => Str::slug($name),
            'phone_e164' => '+1555000'.random_int(1000, 9999),
            'phone_hash' => hash('sha256', $email),
            'email_hash' => hash('sha256', $email),
            'password' => Hash::make('password'),
        ]);
        UserStats::firstOrCreate(['user_id' => $user->id]);
        $device = Device::firstOrCreate(
            ['user_id' => $user->id, 'device_uuid' => $deviceUuid],
            [
                'platform' => $platform,
                'is_active' => true,
                'last_seen_at' => now(),
            ]
        );

        return [$user, $device];
    }
}
