<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DeviceService
{
    /**
     * Register or re-activate a device for a user. Enforces "one active device
     * per user" by revoking other active devices belonging to the same user.
     *
     * @param  array{device_uuid:string, platform:string, device_name?:?string, app_version?:?string, push_token?:?string}  $data
     */
    public function registerForUser(User $user, array $data): Device
    {
        return DB::transaction(function () use ($user, $data) {
            // Revoke any other active device for this user.
            $user->devices()
                ->where('is_active', true)
                ->where('device_uuid', '!=', $data['device_uuid'])
                ->update([
                    'is_active' => false,
                    'revoked_at' => now(),
                ]);

            // If a device with this UUID exists for *another* user, refuse.
            $foreign = Device::where('device_uuid', $data['device_uuid'])
                ->where('user_id', '!=', $user->id)
                ->first();
            if ($foreign) {
                abort(409, 'Device UUID already bound to another account.');
            }

            $device = Device::updateOrCreate(
                ['user_id' => $user->id, 'device_uuid' => $data['device_uuid']],
                [
                    'platform' => $data['platform'],
                    'device_name' => $data['device_name'] ?? null,
                    'app_version' => $data['app_version'] ?? null,
                    'push_token' => $data['push_token'] ?? null,
                    'is_active' => true,
                    'revoked_at' => null,
                    'last_seen_at' => now(),
                ]
            );

            return $device->fresh();
        });
    }

    public function revokeCurrent(User $user): ?Device
    {
        $device = $user->activeDevice;
        if (! $device) {
            return null;
        }
        $device->fill(['is_active' => false, 'revoked_at' => now()])->save();

        return $device;
    }

    /**
     * Resolve the current active device for a user and assert that the supplied
     * device_uuid matches. Returns the device or aborts 403.
     */
    public function assertActiveDevice(User $user, string $deviceUuid): Device
    {
        $device = $user->activeDevice()->where('device_uuid', $deviceUuid)->first();
        abort_if(! $device, 403, 'Device is not the active device for this user.');

        return $device;
    }
}
