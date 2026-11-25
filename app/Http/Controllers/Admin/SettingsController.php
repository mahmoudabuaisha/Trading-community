<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SettingsRequest;
use App\Models\Setting;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        Gate::authorize('area', 'content');

        return response()->json(['content' => Setting::content()]);
    }

    public function update(SettingsRequest $request, AdminService $admin): JsonResponse
    {
        $admin->saveContent($request->user(), $request->attributesForModel());

        return response()->json(['content' => Setting::content()]);
    }
}
