<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('admin');

        $search = trim((string) $request->query('search', ''));

        $page = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
                $query->where(fn ($q) => $q
                    ->where('display_name', 'like', $like)
                    ->orWhere('surname', 'like', $like)
                    ->orWhere('email', 'like', $like));
            })
            ->latest()
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => AdminUserResource::collection($page->items()),
            'meta' => ['currentPage' => $page->currentPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, AdminService $admin): JsonResponse
    {
        $user = $admin->updateUser($request->user(), $user, $request->attributesForModel());

        return response()->json(['user' => new AdminUserResource($user)]);
    }

    public function revokeSessions(Request $request, User $user, AdminService $admin): Response
    {
        Gate::authorize('admin');

        $admin->revokeSessions($request->user(), $user);

        return response()->noContent();
    }
}
