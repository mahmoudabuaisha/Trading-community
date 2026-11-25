<?php

namespace App\Models\Concerns;

use App\Models\ContentTranslation;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
