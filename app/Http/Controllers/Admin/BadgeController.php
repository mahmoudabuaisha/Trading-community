<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BadgeRequest;
use App\Http\Resources\BadgeResource;
use App\Models\Badge;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class BadgeController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('area', 'badges');

        return response()->json(['badges' => BadgeResource::collection(Badge::orderBy('id')->get())]);
    }

    public function store(BadgeRequest $request, AdminService $admin): JsonResponse
    {
        $badge = $admin->saveBadge($request->user(), null, $request->attributesForModel());

        return response()->json(['badge' => new BadgeResource($badge->refresh())], 201);
    }

    public function update(BadgeRequest $request, Badge $badge, AdminService $admin): JsonResponse
    {
        return response()->json(['badge' => new BadgeResource($admin->saveBadge($request->user(), $badge, $request->attributesForModel()))]);
    }

    public function destroy(Request $request, Badge $badge, AdminService $admin): Response
    {
        Gate::authorize('area', 'badges');
        $admin->deleteBadge($request->user(), $badge);

        return response()->noContent();
    }
}
