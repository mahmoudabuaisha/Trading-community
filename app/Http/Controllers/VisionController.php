<?php

namespace App\Http\Controllers;

use App\Models\VisionBoard;
use App\Models\VisionItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class VisionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['boards' => $request->user()->visionBoards()->with('items')->orderBy('id')->get()->map(fn (VisionBoard $board): array => self::board($board))]);
    }

    public function storeBoard(Request $request): JsonResponse
    {
        abort_if($request->user()->visionBoards()->count() >= 12, 422, __('validation.custom.vision.boards'));
        $board = $request->user()->visionBoards()->create($this->boardData($request, 'required'));

        return response()->json(['board' => self::board($board->load('items'))], 201);
    }

    public function updateBoard(Request $request, VisionBoard $board): JsonResponse
    {
        $this->own($request, $board);
        $board->update($this->boardData($request, 'sometimes'));
        if ($request->has('order')) {
            // The member arranged the items: `order` lists every item id in its new place.
            $ids = $request->validate(['order' => ['array'], 'order.*' => ['integer']])['order'];
            foreach ($ids as $position => $id) {
                $board->items()->whereKey($id)->update(['position' => $position]);
            }
        }

        return response()->json(['board' => self::board($board->load('items'))]);
    }

    public function destroyBoard(Request $request, VisionBoard $board): Response
    {
        $this->own($request, $board);
        $board->delete();

        return response()->noContent();
    }

    public function storeItem(Request $request, VisionBoard $board): JsonResponse
    {
        $this->own($request, $board);
        abort_if($board->items()->count() >= 60, 422, __('validation.custom.vision.items'));
        $data = $request->validate([
            'kind' => ['required', Rule::in(VisionItem::KINDS)],
            'text' => [Rule::requiredIf($request->input('kind') !== 'image'), 'nullable', 'string', 'max:500'],
            'image' => [Rule::requiredIf($request->input('kind') === 'image'), 'file', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'size' => ['sometimes', Rule::in(VisionItem::SIZES)],
            'goalId' => ['sometimes', 'nullable', 'integer'],
        ]);
        $item = $board->items()->make([
            'kind' => $data['kind'], 'text' => $data['text'] ?? '', 'size' => $data['size'] ?? 'md',
            'goal_id' => $this->goalId($request, $data['goalId'] ?? null),
            'position' => (int) $board->items()->max('position') + 1,
        ]);
        $item->image_path = $request->file('image')?->store(VisionItem::IMAGE_DIRECTORY, 'public');
        $item->save();

        return response()->json(['board' => self::board($board->load('items'))], 201);
    }

    public function updateItem(Request $request, VisionItem $item): JsonResponse
    {
        $board = VisionBoard::findOrFail($item->vision_board_id);
        $this->own($request, $board);
        $data = $request->validate([
            'text' => ['sometimes', 'nullable', 'string', 'max:500'],
            'size' => ['sometimes', Rule::in(VisionItem::SIZES)],
            'goalId' => ['sometimes', 'nullable', 'integer'],
        ]);
        $item->fill(array_filter(['size' => $data['size'] ?? null]));
        if (array_key_exists('text', $data)) {
            $item->text = $data['text'] ?? '';
        }
        if (array_key_exists('goalId', $data)) {
            $item->goal_id = $this->goalId($request, $data['goalId']);
        }
        $item->save();

        return response()->json(['board' => self::board($board->load('items'))]);
    }

    public function destroyItem(Request $request, VisionItem $item): Response
    {
        $this->own($request, VisionBoard::findOrFail($item->vision_board_id));
        $item->delete();

        return response()->noContent();
    }

    private function own(Request $request, VisionBoard $board): void
    {
        abort_unless($board->user_id === $request->user()->id, 404);
    }

    /** A linked goal must be the member's own. */
    private function goalId(Request $request, ?int $id): ?int
    {
        return $id === null ? null : $request->user()->goals()->findOrFail($id)->id;
    }

    private function boardData(Request $request, string $required): array
    {
        return $request->validate([
            'title' => [$required, 'string', 'max:80'],
            'area' => ['sometimes', Rule::in(VisionBoard::AREAS)],
            'color' => ['sometimes', Rule::in(VisionBoard::COLORS)],
        ]);
    }

    private static function board(VisionBoard $board): array
    {
        return [
            'id' => $board->id, 'title' => $board->title, 'area' => $board->area, 'color' => $board->color,
            'items' => $board->items->map(fn (VisionItem $item): array => [
                'id' => $item->id, 'kind' => $item->kind, 'text' => $item->text, 'size' => $item->size, 'goalId' => $item->goal_id,
                'imageUrl' => $item->image_path ? '/media/'.$item->image_path : null,
            ]),
        ];
    }
}
