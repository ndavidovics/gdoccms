<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function __construct(private readonly DeviceService $devices) {}

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'device_uuid' => ['required', 'string', 'min:8', 'max:80'],
            'platform' => ['required', 'in:ios,android'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'push_token' => ['nullable', 'string', 'max:255'],
        ]);
        $device = $this->devices->registerForUser($request->user(), $data);

        return response()->json(['device' => $device], 201);
    }

    public function current(Request $request): JsonResponse
    {
        $device = $request->user()->activeDevice;

        return response()->json(['device' => $device]);
    }

    public function revoke(Request $request): JsonResponse
    {
        $device = $this->devices->revokeCurrent($request->user());

        return response()->json(['device' => $device]);
    }
}
