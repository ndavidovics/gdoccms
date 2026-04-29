<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['app' => 'mutual-offline-session', 'docs' => '/docs/API.md']));
