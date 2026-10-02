<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\ReferenceController;
use App\Http\Controllers\Api\TicketController;
use Illuminate\Support\Facades\Route;

// Public, rate limited: ten attempts per minute and per IP address.
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/categories', [ReferenceController::class, 'categories']);
    Route::get('/agents', [ReferenceController::class, 'agents']);
    Route::get('/stats', [ReferenceController::class, 'stats']);

    Route::apiResource('tickets', TicketController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/tickets/{ticket}/comments', [CommentController::class, 'store']);
});
