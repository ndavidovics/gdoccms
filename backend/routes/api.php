<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SocialController;
use App\Http\Controllers\Api\StatsController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/profile/privacy', [AuthController::class, 'updatePrivacy']);
    Route::get('/profile/stats', [StatsController::class, 'show']);

    // Devices
    Route::post('/devices/register', [DeviceController::class, 'register']);
    Route::get('/devices/current', [DeviceController::class, 'current']);
    Route::post('/devices/revoke', [DeviceController::class, 'revoke']);

    // Sessions
    Route::post('/sessions', [SessionController::class, 'create']);
    Route::post('/sessions/join', [SessionController::class, 'join']);
    Route::get('/sessions/{uuid}', [SessionController::class, 'show']);
    Route::post('/sessions/{uuid}/confirm-lock', [SessionController::class, 'confirmLock']);
    Route::post('/sessions/{uuid}/heartbeat', [SessionController::class, 'heartbeat']);
    Route::post('/sessions/{uuid}/request-end', [SessionController::class, 'requestEnd']);
    Route::post('/sessions/{uuid}/confirm-end', [SessionController::class, 'confirmEnd']);
    Route::post('/sessions/{uuid}/emergency-exit', [SessionController::class, 'emergencyExit']);

    // Social
    Route::post('/contacts/sync', [SocialController::class, 'syncContacts']);
    Route::delete('/contacts', [SocialController::class, 'deleteContacts']);
    Route::get('/contacts/matches', [SocialController::class, 'matches']);

    Route::post('/friends/request', [SocialController::class, 'requestFriend']);
    Route::post('/friends/{id}/accept', [SocialController::class, 'acceptFriend']);
    Route::post('/friends/{id}/reject', [SocialController::class, 'rejectFriend']);
    Route::delete('/friends/{id}', [SocialController::class, 'removeFriend']);
    Route::get('/friends', [SocialController::class, 'listFriends']);

    Route::get('/feed', [SocialController::class, 'feed']);
});
