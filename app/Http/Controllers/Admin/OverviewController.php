<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditResource;
use App\Models\AdminAudit;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OverviewController extends Controller
{
    public function show(): JsonResponse
    {
        Gate::authorize('staff');

        return response()->json([
            'stats' => [
                'users' => User::count(),
                'tasks' => Task::count(),
                'completed' => Task::where('status', Task::STATUS_COMPLETED)->count(),
                'activeToday' => DB::table('sessions')
                    ->whereNotNull('user_id')
                    ->where('last_activity', '>=', now()->subDay()->getTimestamp())
                    ->distinct()
                    ->count('user_id'),
            ],
            // The log of administrative actions is its own area.
            'audit' => Gate::allows('area', 'audit')
                ? AuditResource::collection(AdminAudit::with('admin')->latest('created_at')->latest('id')->limit(30)->get())
                : [],
        ]);
    }
}
