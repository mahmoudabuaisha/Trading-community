<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetLocaleFromRequest;
use App\Models\Badge;
use App\Models\Broadcast;
use App\Models\BroadcastSchedule;
use App\Models\ContentTranslation;
use App\Models\DailyTip;
use App\Models\Lesson;
use App\Models\Motivation;
use App\Models\SkillLevel;
use App\Models\SkillPath;
use App\Models\StoreCategory;
use App\Models\StoreItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Other-language versions of what the administrators wrote. Each kind of
 * content is edited by whoever holds its admin area.
 */
class TranslationController extends Controller
{
    /** URL name → model and the admin area that owns it. */
    private const TYPES = [
        'motivation' => [Motivation::class, 'growth'],
        'broadcast' => [Broadcast::class, 'growth'],
        'broadcast-schedule' => [BroadcastSchedule::class, 'growth'],
        'daily-tip' => [DailyTip::class, 'growth'],
        'lesson' => [Lesson::class, 'education'],
        'skill-path' => [SkillPath::class, 'education'],
        'skill-level' => [SkillLevel::class, 'education'],
        'badge' => [Badge::class, 'badges'],
        'store-item' => [StoreItem::class, 'store'],
        'store-category' => [StoreCategory::class, 'store'],
    ];

    public function show(string $type, int $id): JsonResponse
    {
        return response()->json($this->payload($this->find($type, $id)));
    }

    /**
     * Save the translations of one item: {field: {locale: text}}. An empty text
     * removes that translation, so members fall back to the original.
     */
    public function update(Request $request, string $type, int $id): JsonResponse
    {
        $model = $this->find($type, $id);
        $data = $request->validate([
            'translations' => ['required', 'array'],
            'translations.*' => ['array'],
            'translations.*.*' => ['nullable', 'string', 'max:20000'],
        ])['translations'];

        DB::transaction(function () use ($model, $data): void {
            foreach ($this->fields($model) as $field) {
                $original = (string) $model->getAttribute($field);
                foreach (SetLocaleFromRequest::SUPPORTED as $locale) {
                    if (! array_key_exists($locale, $data[$field] ?? [])) {
                        continue;
                    }
                    $text = trim((string) $data[$field][$locale]);
                    $row = $model->translations()->firstOrNew(['field' => $field, 'locale' => $locale]);
                    if ($text === '' || $original === '') {
                        $row->exists && $row->delete();
                    } else {
                        $row->fill(['text' => $text, 'source_hash' => sha1($original)])->save();
                    }
                }
            }
        });

        return response()->json($this->payload($model->load('translations')));
    }

    private function find(string $type, int $id): Model
    {
        abort_unless(isset(self::TYPES[$type]), 404);
        [$class, $area] = self::TYPES[$type];
        Gate::authorize('area', $area);

        return $class::findOrFail($id);
    }

    /** @return list<string> the fields of this item that hold language */
    private function fields(Model $model): array
    {
        // A store item's `value` is text only when the item is a title.
        return array_values(array_filter($model::TRANSLATABLE, fn (string $field): bool => ! ($model instanceof StoreItem && $field === 'value' && $model->kind !== 'title')));
    }

    private function payload(Model $model): array
    {
        return [
            'locales' => SetLocaleFromRequest::SUPPORTED,
            'fields' => array_map(function (string $field) use ($model): array {
                $original = (string) $model->getAttribute($field);
                $rows = $model->translations->where('field', $field)->keyBy('locale');

                return [
                    'field' => $field,
                    'original' => $original,
                    'translations' => collect(SetLocaleFromRequest::SUPPORTED)->mapWithKeys(fn (string $locale): array => [$locale => $rows->get($locale)?->text ?? ''])->all(),
                    // Written for an earlier wording of the original: shown to the admin, not to members.
                    'stale' => $rows->filter(fn (ContentTranslation $row): bool => $row->source_hash !== sha1($original))->keys()->values()->all(),
                ];
            }, array_values(array_filter($this->fields($model), fn (string $field): bool => (string) $model->getAttribute($field) !== ''))),
        ];
    }
}
