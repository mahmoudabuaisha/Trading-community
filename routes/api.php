<?php

use App\Http\Controllers\Admin\BadgeController as AdminBadgeController;
use App\Http\Controllers\Admin\BookController as AdminBookController;
use App\Http\Controllers\Admin\BroadcastController as AdminBroadcastController;
use App\Http\Controllers\Admin\BroadcastScheduleController as AdminBroadcastScheduleController;
use App\Http\Controllers\Admin\DailyTipController as AdminDailyTipController;
use App\Http\Controllers\Admin\DesignController as AdminDesignController;
use App\Http\Controllers\Admin\LessonController as AdminLessonController;
use App\Http\Controllers\Admin\MotivationController as AdminMotivationController;
use App\Http\Controllers\Admin\OverviewController as AdminOverviewController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\StoreCategoryController as AdminStoreCategoryController;
use App\Http\Controllers\Admin\StoreItemController as AdminStoreItemController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Admin\ThreadController as AdminThreadController;
use App\Http\Controllers\Admin\TranslationController as AdminTranslationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DailyTipController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\FocusSessionController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HabitController;
use App\Http\Controllers\JourneyController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MySkillController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SiteCopyController;
use App\Http\Controllers\SkillController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\VisionController;
use App\Http\Controllers\WorldController;
use Illuminate\Support\Facades\Route;

/*
| Session-authenticated JSON API for the SPA (Sanctum stateful cookies).
*/

// Phrases the administrators rewrote, in the visitor's language (the login page needs them too).
Route::get('site-copy', [SiteCopyController::class, 'index']);

Route::prefix('auth')->group(function (): void {
    Route::get('providers', [GoogleController::class, 'providers']);
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

// A shared portfolio link works without an account when its owner made it public.
Route::get('public/portfolio/{slug}', [PortfolioController::class, 'show'])->where('slug', '[a-z0-9]{20}')->middleware('throttle:60,1');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('dashboard', [DashboardController::class, 'show']);

    Route::post('tasks', [TaskController::class, 'store']);
    Route::patch('tasks/{task}', [TaskController::class, 'update']);
    Route::delete('tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('tasks/{task}/dismiss-reminder', [TaskController::class, 'dismissReminder']);

    Route::post('goals', [GoalController::class, 'store']);
    Route::patch('goals/{goal}', [GoalController::class, 'update']);
    Route::delete('goals/{goal}', [GoalController::class, 'destroy']);

    Route::put('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'password'])->middleware('throttle:6,1');
    Route::post('profile/sign-out-others', [ProfileController::class, 'signOutOthers']);
    Route::get('profile/sessions', [ProfileController::class, 'sessions']);
    Route::put('profile/preferences', [ProfileController::class, 'preferences']);
    Route::put('profile/privacy', [ProfileController::class, 'privacy']);
    Route::get('profile/points', [ProfileController::class, 'points']);
    Route::get('store', [StoreController::class, 'index']);
    Route::post('store/{item}/buy', [StoreController::class, 'buy'])->middleware('throttle:30,1');
    Route::put('store/equip', [StoreController::class, 'equip']);
    Route::get('store/{item}/download', [StoreController::class, 'download']);
    Route::get('leaderboard', [LeaderboardController::class, 'show']);
    Route::get('designs', [DesignController::class, 'index']);
    Route::post('designs/{design}/buy', [DesignController::class, 'buy'])->middleware('throttle:30,1');
    Route::put('designs/select', [DesignController::class, 'select']);

    Route::post('categories', [CategoryController::class, 'store']);
    Route::patch('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
    Route::get('daily-tip', [DailyTipController::class, 'show']);
    Route::get('world', [WorldController::class, 'show']);
    Route::get('assistant', [AssistantController::class, 'status']);
    Route::post('assistant', [AssistantController::class, 'ask'])->middleware('throttle:10,1');
    Route::post('daily-tip', [DailyTipController::class, 'draw'])->middleware('throttle:20,1');

    Route::get('insights', [JourneyController::class, 'insights']);
    Route::get('timeline', [JourneyController::class, 'timeline']);
    Route::put('timeline/notes/{key}', [JourneyController::class, 'note'])->where('key', '[a-z]+(:[A-Za-z0-9_]+)?');
    Route::post('timeline', [JourneyController::class, 'addMilestone']);
    Route::patch('timeline/{entry}', [JourneyController::class, 'updateMilestone']);
    Route::delete('timeline/{entry}', [JourneyController::class, 'deleteMilestone']);
    Route::get('goals/{goal}/steps', [JourneyController::class, 'roadmap']);
    Route::post('goals/{goal}/steps', [JourneyController::class, 'addStep']);
    Route::patch('goal-steps/{step}', [JourneyController::class, 'updateStep']);
    Route::delete('goal-steps/{step}', [JourneyController::class, 'deleteStep']);
    Route::get('profile/export', [JourneyController::class, 'export'])->middleware('throttle:10,1');
    Route::get('profile/backup-status', [JourneyController::class, 'backupStatus']);

    Route::get('vision-boards', [VisionController::class, 'index']);
    Route::post('vision-boards', [VisionController::class, 'storeBoard']);
    Route::patch('vision-boards/{board}', [VisionController::class, 'updateBoard']);
    Route::delete('vision-boards/{board}', [VisionController::class, 'destroyBoard']);
    Route::post('vision-boards/{board}/items', [VisionController::class, 'storeItem']);
    Route::patch('vision-items/{item}', [VisionController::class, 'updateItem']);
    Route::delete('vision-items/{item}', [VisionController::class, 'destroyItem']);

    Route::get('skills', [SkillController::class, 'index']);
    Route::get('my-skills', [MySkillController::class, 'index']);
    Route::post('my-skills', [MySkillController::class, 'savePath']);
    Route::patch('my-skills/{path}', [MySkillController::class, 'savePath']);
    Route::delete('my-skills/{path}', [MySkillController::class, 'destroyPath']);
    Route::post('my-skills/{path}/levels', [MySkillController::class, 'saveLevel']);
    Route::patch('my-skills/{path}/levels/{level}', [MySkillController::class, 'saveLevel']);
    Route::delete('my-skills/{path}/levels/{level}', [MySkillController::class, 'destroyLevel']);
    Route::post('my-skills/{path}/levels/{level}/toggle', [MySkillController::class, 'toggle']);
    Route::post('skill-levels/{level}/attempt', [SkillController::class, 'attempt'])->middleware('throttle:30,1');

    Route::get('portfolio', [PortfolioController::class, 'index']);
    Route::post('portfolio', [PortfolioController::class, 'store']);
    Route::post('portfolio/{project}', [PortfolioController::class, 'update'])->whereNumber('project');
    Route::delete('portfolio/{project}', [PortfolioController::class, 'destroy']);
    Route::put('portfolio-settings', [PortfolioController::class, 'settings']);
    Route::post('focus-sessions', [FocusSessionController::class, 'store']);
    Route::put('reviews/{date}', [ReviewController::class, 'update'])->where('date', '\d{4}-\d{2}-\d{2}');

    Route::get('habits', [HabitController::class, 'index']);
    Route::post('habits', [HabitController::class, 'store']);
    Route::patch('habits/{habit}', [HabitController::class, 'update']);
    Route::delete('habits/{habit}', [HabitController::class, 'destroy']);
    Route::put('habits/{habit}/checks/{day}', [HabitController::class, 'check'])->where('day', '\d{4}-\d{2}-\d{2}');
    // POST, not PUT: the day's picture travels as multipart form data.
    Route::get('habit-days', [HabitController::class, 'days']);
    Route::post('habit-days/{day}', [HabitController::class, 'day'])->where('day', '\\d{4}-\\d{2}-\\d{2}');
    Route::put('moods/{day}', [HabitController::class, 'mood'])->where('day', '\d{4}-\d{2}-\d{2}');
    Route::put('habit-notes/{month}', [HabitController::class, 'note'])->where('month', '\d{4}-(0[1-9]|1[0-2])');

    Route::get('lessons', [LessonController::class, 'index']);
    Route::get('books', [BookController::class, 'index']);
    Route::get('books/{book}/read', [BookController::class, 'read']);
    Route::get('messages', [MessageController::class, 'index']);
    Route::get('messages/unread', [MessageController::class, 'unread']);
    Route::post('messages', [MessageController::class, 'store'])->middleware('throttle:20,1');

    Route::middleware('staff')->prefix('admin')->group(function (): void {
        Route::get('overview', [AdminOverviewController::class, 'show']);
        Route::get('users', [AdminUserController::class, 'index'])->middleware('area:users');
        Route::get('users/{user}', [AdminUserController::class, 'show'])->middleware('area:users');
        Route::get('users/{user}/subscription', [AdminSubscriptionController::class, 'show'])->middleware('area:users');
        Route::put('users/{user}/subscription', [AdminSubscriptionController::class, 'update'])->middleware('area:users');
        Route::get('subscription-notice', [AdminSubscriptionController::class, 'notice'])->middleware('area:messages');
        Route::put('subscription-notice', [AdminSubscriptionController::class, 'saveNotice'])->middleware('area:messages');
        Route::get('books', [AdminBookController::class, 'index'])->middleware('area:education');
        Route::post('books', [AdminBookController::class, 'store'])->middleware('area:education');
        // POST, not PATCH: the update may carry a cover or a file as multipart form data.
        Route::post('books/{book}', [AdminBookController::class, 'update'])->middleware('area:education');
        Route::delete('books/{book}', [AdminBookController::class, 'destroy'])->middleware('area:education');
        Route::patch('users/{user}', [AdminUserController::class, 'update'])->middleware('area:users');
        Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->middleware('area:users');
        Route::post('members/purge', [AdminUserController::class, 'purgeMembers'])->middleware('area:users');
        Route::post('users/{user}/revoke-sessions', [AdminUserController::class, 'revokeSessions'])->middleware('area:users');
        Route::get('motivations', [AdminMotivationController::class, 'index'])->middleware('area:growth');
        Route::post('motivations', [AdminMotivationController::class, 'store'])->middleware('area:growth');
        Route::patch('motivations/{motivation}', [AdminMotivationController::class, 'update'])->middleware('area:growth');
        Route::delete('motivations/{motivation}', [AdminMotivationController::class, 'destroy'])->middleware('area:growth');
        Route::get('badges', [AdminBadgeController::class, 'index'])->middleware('area:badges');
        Route::post('badges', [AdminBadgeController::class, 'store'])->middleware('area:badges');
        Route::patch('badges/{badge}', [AdminBadgeController::class, 'update'])->middleware('area:badges');
        Route::delete('badges/{badge}', [AdminBadgeController::class, 'destroy'])->middleware('area:badges');
        Route::get('store-items', [AdminStoreItemController::class, 'index'])->middleware('area:store');
        Route::post('store-items', [AdminStoreItemController::class, 'store'])->middleware('area:store');
        // POST, not PATCH: the update carries an optional image as multipart form data.
        Route::post('store-items/{item}', [AdminStoreItemController::class, 'update'])->middleware('area:store');
        Route::delete('store-items/{item}', [AdminStoreItemController::class, 'destroy'])->middleware('area:store');
        Route::get('designs', [AdminDesignController::class, 'index'])->middleware('area:store');
        Route::post('designs', [AdminDesignController::class, 'store'])->middleware('area:store');
        // POST, not PATCH: the update may carry a background picture as multipart form data.
        Route::post('designs/{design}', [AdminDesignController::class, 'update'])->middleware('area:store');
        Route::delete('designs/{design}', [AdminDesignController::class, 'destroy'])->middleware('area:store');
        Route::get('store-categories', [AdminStoreCategoryController::class, 'index'])->middleware('area:store');
        Route::post('store-categories', [AdminStoreCategoryController::class, 'store'])->middleware('area:store');
        Route::patch('store-categories/{category}', [AdminStoreCategoryController::class, 'update'])->middleware('area:store');
        Route::delete('store-categories/{category}', [AdminStoreCategoryController::class, 'destroy'])->middleware('area:store');
        // Each kind of content is guarded by its own area inside the controller.
        Route::get('site-copy', [SiteCopyController::class, 'adminIndex']);
        Route::put('site-copy', [SiteCopyController::class, 'save']);
        Route::get('translations/{type}/{id}', [AdminTranslationController::class, 'show'])->whereNumber('id');
        Route::put('translations/{type}/{id}', [AdminTranslationController::class, 'update'])->whereNumber('id');
        Route::get('broadcasts', [AdminBroadcastController::class, 'index'])->middleware('area:growth');
        Route::post('broadcasts', [AdminBroadcastController::class, 'send'])->middleware('area:growth');
        Route::put('broadcasts/schedule/{day}', [AdminBroadcastController::class, 'schedule'])->middleware('area:growth')->where('day', '\\d{4}-\\d{2}-\\d{2}');
        Route::get('daily-tips', [AdminDailyTipController::class, 'index'])->middleware('area:growth');
        Route::post('daily-tips', [AdminDailyTipController::class, 'store'])->middleware('area:growth');
        Route::patch('daily-tips/{tip}', [AdminDailyTipController::class, 'update'])->middleware('area:growth');
        Route::delete('daily-tips/{tip}', [AdminDailyTipController::class, 'destroy'])->middleware('area:growth');
        Route::get('broadcast-schedules', [AdminBroadcastScheduleController::class, 'index'])->middleware('area:growth');
        Route::post('broadcast-schedules', [AdminBroadcastScheduleController::class, 'store'])->middleware('area:growth');
        Route::patch('broadcast-schedules/{schedule}', [AdminBroadcastScheduleController::class, 'update'])->middleware('area:growth');
        Route::delete('broadcast-schedules/{schedule}', [AdminBroadcastScheduleController::class, 'destroy'])->middleware('area:growth');
        Route::delete('broadcasts/{broadcast}', [AdminBroadcastController::class, 'destroy'])->middleware('area:growth');
        Route::get('skill-paths', [SkillController::class, 'adminIndex'])->middleware('area:education');
        Route::post('skill-paths', [SkillController::class, 'savePath'])->middleware('area:education');
        Route::patch('skill-paths/{path}', [SkillController::class, 'savePath'])->middleware('area:education');
        Route::delete('skill-paths/{path}', [SkillController::class, 'destroyPath'])->middleware('area:education');
        Route::post('skill-paths/{path}/levels', [SkillController::class, 'saveLevel'])->middleware('area:education');
        Route::patch('skill-paths/{path}/levels/{level}', [SkillController::class, 'saveLevel'])->middleware('area:education');
        Route::delete('skill-paths/{path}/levels/{level}', [SkillController::class, 'destroyLevel'])->middleware('area:education');
        Route::get('settings', [AdminSettingsController::class, 'show'])->middleware('area:content');
        Route::put('settings', [AdminSettingsController::class, 'update'])->middleware('area:content');
        Route::get('threads', [AdminThreadController::class, 'index'])->middleware('area:messages');
        Route::get('threads/{user}', [AdminThreadController::class, 'show'])->middleware('area:messages');
        Route::post('threads/{user}', [AdminThreadController::class, 'store'])->middleware('area:messages');
        Route::get('lessons', [AdminLessonController::class, 'index'])->middleware('area:education');
        Route::post('lessons', [AdminLessonController::class, 'store'])->middleware('area:education');
        // POST, not PATCH: the update carries an optional image as multipart form data.
        Route::post('lessons/{lesson}', [AdminLessonController::class, 'update'])->middleware('area:education');
        Route::delete('lessons/{lesson}', [AdminLessonController::class, 'destroy'])->middleware('area:education');
    });
});
