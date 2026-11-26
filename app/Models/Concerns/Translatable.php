<?php

namespace App\Models\Concerns;

use App\Models\ContentTranslation;
use App\Services\Translation\AutoTranslator;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Throwable;

/**
 * Text written by the administrators that members read in their own language.
 *
 * A model lists its fields in a TRANSLATABLE constant. `tr('title')` returns
 * the translation for the request's language when there is a current one, and
 * the original text otherwise. Administrators always get the original, since
 * that is what they edit.
 */
trait Translatable
{
    public function initializeTranslatable(): void
    {
        // Lists read several rows; loading translations with them avoids a query per row.
        $this->with[] = 'translations';
    }

    public static function bootTranslatable(): void
    {
        static::deleted(fn ($model) => $model->translations()->delete());

        // Translate new or reworded text once the administrator has their answer.
        static::saved(function ($model): void {
            if (! AutoTranslator::enabled() || ! $model->isSharedContent() || (! $model->wasRecentlyCreated && ! $model->wasChanged($model->translatableFields()))) {
                return;
            }
            $class = $model::class;
            $id = $model->getKey();
            dispatch(function () use ($class, $id): void {
                $fresh = $class::find($id);
                try {
                    $fresh && app(AutoTranslator::class)->translate($fresh);
                } catch (Throwable $error) {
                    report($error);
                }
            })->afterResponse();
        });
    }

    /** Written by the administrators for everyone (members' own notes are never sent to a translator). */
    public function isSharedContent(): bool
    {
        return true;
    }

    /** @return list<string> the fields of this row that hold language */
    public function translatableFields(): array
    {
        return static::TRANSLATABLE;
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(ContentTranslation::class, 'translatable');
    }

    public function tr(string $field): ?string
    {
        $original = $this->getAttribute($field);
        if ($original === null || $original === '' || request()->is('api/admin/*')) {
            return $original;
        }
        $translation = $this->translations->first(
            fn (ContentTranslation $row): bool => $row->field === $field && $row->locale === app()->getLocale() && $row->source_hash === sha1($original),
        );

        return $translation?->text ?? $original;
    }
}
