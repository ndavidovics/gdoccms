<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'username' => ['nullable', 'string', 'max:40', 'alpha_dash', 'unique:users,username'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_e164' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'] ?? null,
            'email' => $data['email'],
            'phone_e164' => $data['phone_e164'] ?? null,
            'phone_hash' => isset($data['phone_e164']) ? hash('sha256', $data['phone_e164']) : null,
            'email_hash' => hash('sha256', strtolower($data['email'])),
            'password' => $data['password'],
        ]);
        UserStats::create(['user_id' => $user->id]);

        $token = $user->createToken('mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);
        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }
        $token = $user->createToken($data['device_name'] ?? 'mobile')->plainTextToken;

        return response()->json(['user' => $user, 'token' => $token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = UserStats::firstOrCreate(['user_id' => $user->id]);

        return response()->json(['user' => $user, 'stats' => $stats]);
    }

    public function updatePrivacy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'share_successes' => ['sometimes', 'boolean'],
            'share_failures' => ['sometimes', 'boolean'],
            'share_streaks' => ['sometimes', 'boolean'],
            'discoverable_by_contacts' => ['sometimes', 'boolean'],
        ]);
        $request->user()->update($data);

        return response()->json(['user' => $request->user()->fresh()]);
    }
}
