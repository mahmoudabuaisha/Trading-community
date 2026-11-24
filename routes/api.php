<?php

use App\Http\Controllers\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Admin\MotivationController as AdminMotivationController;
use App\Http\Controllers\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\ThreadController as AdminThreadController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FocusSessionController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

/*
| Session-authenticated JSON API for the SPA (Sanctum stateful cookies).
*/

Route::prefix('auth')->group(function (): void {
    Route::middleware('guest:web')->group(function (): void {
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:6,1');
        Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'show']);

    Route::post('tasks', [TaskController::class, 'store']);
    Route::patch('tasks/{task}', [TaskController::class, 'update']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);

    Route::post('goals', [GoalController::class, 'store']);
    Route::patch('goals/{goal}', [GoalController::class, 'update']);
    Route::delete('goals/{goal}', [GoalController::class, 'destroy']);

    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('focus-sessions', [FocusSessionController::class, 'store']);
    Route::put('reviews/{date}', [ReviewController::class, 'update'])->where('date', '\d{4}-\d{2}-\d{2}');

    Route::get('lessons', [LessonController::class, 'index']);
    Route::get('messages', [MessageController::class, 'index']);
    Route::get('messages/unread', [MessageController::class, 'unread']);
    Route::post('messages', [MessageController::class, 'store'])->middleware('throttle:20,1');

    Route::middleware('role:admin')->prefix('admin')->group(function (): void {
        Route::get('overview', [AdminOverviewController::class, 'show']);
        Route::get('users', [AdminUserController::class, 'index']);
        Route::patch('users/{user}', [AdminUserController::class, 'update']);
        Route::post('users/{user}/revoke-sessions', [AdminUserController::class, 'revokeSessions']);
        Route::get('motivations', [AdminMotivationController::class, 'index']);
        Route::post('motivations', [AdminMotivationController::class, 'store']);
        Route::patch('motivations/{motivation}', [AdminMotivationController::class, 'update']);
        Route::delete('motivations/{motivation}', [AdminMotivationController::class, 'destroy']);
        Route::get('settings', [AdminSettingsController::class, 'show']);
        Route::put('settings', [AdminSettingsController::class, 'update']);
        Route::get('threads', [AdminThreadController::class, 'index']);
        Route::get('threads/{user}', [AdminThreadController::class, 'show']);
        Route::post('threads/{user}', [AdminThreadController::class, 'store']);
        Route::get('lessons', [AdminLessonController::class, 'index']);
        Route::post('lessons', [AdminLessonController::class, 'store']);
        // POST, not PATCH: the update carries an optional image as multipart form data.
        Route::post('lessons/{lesson}', [AdminLessonController::class, 'update']);
        Route::delete('lessons/{lesson}', [AdminLessonController::class, 'destroy']);
    });
});
