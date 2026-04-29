<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserStats;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $stats = UserStats::firstOrCreate(['user_id' => $request->user()->id]);

        return response()->json(['stats' => $stats]);
    }
}
