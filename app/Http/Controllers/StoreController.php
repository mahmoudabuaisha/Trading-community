<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoreItemResource;
use App\Http\Resources\UserResource;
use App\Models\StoreItem;
use App\Models\StorePurchase;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StoreController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return $this->respond($request->user());
    }

    public function buy(Request $request, StoreItem $item, PointsService $points): JsonResponse
    {
        $points->buy($request->user(), $item);

        return $this->respond($request->user());
    }

    /** Use a purchased item, or stop using that kind when `itemId` is null. */
    public function equip(Request $request, PointsService $points): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(StoreItem::KINDS)],
            'itemId' => ['present', 'nullable', 'integer', 'exists:store_items,id'],
        ]);
        $points->equip($request->user(), $data['kind'], $data['itemId'] ? StoreItem::find($data['itemId']) : null);

        return $this->respond($request->user());
    }

    public function image(string $file): BinaryFileResponse
    {
        $path = StoreItem::IMAGE_DIRECTORY.'/'.$file;
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), ['Cache-Control' => 'public, max-age=31536000, immutable']);
    }

    /** Items on sale plus anything the member already owns (even if later taken off sale). */
    private function respond(User $user): JsonResponse
    {
        $user->refresh();
        $owned = StorePurchase::where('user_id', $user->id)->pluck('store_item_id')->flip();
        $equipped = collect($user->equipped ?? [])->filter()->flip();
        $items = StoreItem::where('is_active', true)->orWhereIn('id', $owned->keys())->orderBy('price')->orderBy('id')->get()
            ->each(function (StoreItem $item) use ($owned, $equipped, $user): void {
                $item->owned = $owned->has($item->id);
                $item->equipped = $item->kind === 'avatar'
                    ? $item->owned && $user->avatar_url === $item->imageUrl()
                    : $equipped->has($item->id);
            });

        return response()->json(['items' => StoreItemResource::collection($items), 'user' => new UserResource($user)]);
    }
}
