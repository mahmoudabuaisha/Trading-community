<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\Design;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Designs of the goal map and the skill tree: free ones for all, others bought with points. */
class DesignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->respond($request->user());
    }

    public function buy(Request $request, Design $design): JsonResponse
    {
        $user = $request->user();
        DB::transaction(function () use ($user, $design): void {
            User::whereKey($user->id)->lockForUpdate()->first();
            if (! $design->is_active) {
                throw ValidationException::withMessages(['design' => __('validation.custom.store.unavailable')]);
            }
            if ($design->price === 0 || $user->designPurchases()->where('design_id', $design->id)->exists()) {
                throw ValidationException::withMessages(['design' => __('validation.custom.store.owned')]);
            }
            if (app(PointsService::class)->balance($user) < $design->price) {
                throw ValidationException::withMessages(['design' => __('validation.custom.store.points')]);
            }
            $user->designPurchases()->create(['design_id' => $design->id, 'price' => $design->price]);
            // A design bought is a design wanted: put it to use straight away.
            $user->forceFill(['designs' => [...($user->designs ?? []), $design->target => $design->id]])->save();
        });

        return $this->respond($user->refresh());
    }

    public function select(Request $request): JsonResponse
    {
        $data = $request->validate(['designId' => ['required', 'integer', Rule::exists('designs', 'id')]]);
        $user = $request->user();
        $design = Design::findOrFail($data['designId']);
        if (! $this->usable($user, $design)) {
            throw ValidationException::withMessages(['designId' => __('validation.custom.store.notOwned')]);
        }
        $user->forceFill(['designs' => [...($user->designs ?? []), $design->target => $design->id]])->save();

        return $this->respond($user->refresh());
    }

    public function background(string $file): BinaryFileResponse
    {
        $path = Design::IMAGE_DIRECTORY.'/'.$file;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    private function usable(User $user, Design $design): bool
    {
        return $design->is_active && ($design->price === 0 || $user->designPurchases()->where('design_id', $design->id)->exists());
    }

    /**
     * Every design on offer, plus any the member bought that was later hidden,
     * and the one in use per target (the first free one when none was chosen
     * or the chosen one is gone).
     */
    private function respond(User $user): JsonResponse
    {
        $owned = $user->designPurchases()->pluck('design_id')->flip();
        $designs = Design::where('is_active', true)->orWhereIn('id', $owned->keys())->orderBy('target')->orderBy('position')->orderBy('id')->get();
        $selected = [];
        foreach (Design::TARGETS as $target) {
            $chosen = $designs->firstWhere('id', $user->designs[$target] ?? null);
            $usable = $chosen && ($chosen->price === 0 || $owned->has($chosen->id));
            $selected[$target] = $usable ? $chosen->id : $designs->where('target', $target)->where('price', 0)->where('is_active', true)->first()?->id;
        }

        return response()->json([
            'designs' => $designs->map(fn (Design $design): array => [...$design->toApi(), 'owned' => $design->price === 0 || $owned->has($design->id)]),
            'selected' => $selected,
            'user' => new UserResource($user),
        ]);
    }
}
