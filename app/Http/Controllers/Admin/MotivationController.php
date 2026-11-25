<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MotivationRequest;
use App\Http\Resources\MotivationResource;
use App\Models\Motivation;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class MotivationController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('area', 'growth');

        return response()->json(['motivations' => MotivationResource::collection(Motivation::latest()->latest('id')->get())]);
    }

    public function store(MotivationRequest $request, AdminService $admin): JsonResponse
    {
        $motivation = $admin->saveMotivation($request->user(), null, $request->attributesForModel());

        return response()->json(['motivation' => new MotivationResource($motivation->refresh())], 201);
    }

    public function update(MotivationRequest $request, Motivation $motivation, AdminService $admin): JsonResponse
    {
        $motivation = $admin->saveMotivation($request->user(), $motivation, $request->attributesForModel());

        return response()->json(['motivation' => new MotivationResource($motivation)]);
    }

    public function destroy(Request $request, Motivation $motivation, AdminService $admin): Response
    {
        Gate::authorize('area', 'growth');

        $admin->deleteMotivation($request->user(), $motivation);

        return response()->noContent();
    }
}
