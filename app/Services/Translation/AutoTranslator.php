<?php

namespace App\Services\Translation;

use App\Http\Middleware\SetLocaleFromRequest;
use App\Models\ContentTranslation;
use Illuminate\Database\Eloquent\Model;

/**
 * Fills in the languages an administrator did not translate by hand, so a
 * member switching language reads everything in that language.
 */
class AutoTranslator
{
    public function __construct(private readonly TextTranslator $translator) {}

    public static function enabled(): bool
    {
        return (bool) config('assistant.api_key') && (bool) config('assistant.auto_translate');
    }

    /** Translate what is missing or out of date. Returns how many texts were written. */
    public function translate(Model $model): int
    {
        $model->load('translations');
        $texts = [];
        $locales = [];
        foreach ($model->translatableFields() as $field) {
            $original = (string) $model->getAttribute($field);
            if (trim($original) === '') {
                continue;
            }
            foreach (SetLocaleFromRequest::SUPPORTED as $locale) {
                $row = $model->translations->first(fn (ContentTranslation $row): bool => $row->field === $field && $row->locale === $locale);
                if ($row === null || $row->source_hash !== sha1($original)) {
                    $texts[$field] = $original;
                    $locales[$locale] = true;
                }
            }
        }
        if ($texts === []) {
            return 0;
        }

        $translated = $this->translator->translate($texts, array_keys($locales));
        $written = 0;
        foreach ($translated as $locale => $fields) {
            foreach ($fields as $field => $text) {
                $original = $texts[$field];
                $row = $model->translations()->firstOrNew(['field' => $field, 'locale' => $locale]);
                // A current hand-written translation wins.
                if ($row->exists && ! $row->is_auto && $row->source_hash === sha1($original)) {
                    continue;
                }
                $row->fill(['text' => $text, 'source_hash' => sha1($original)]);
                $row->is_auto = true;
                $row->save();
                $written++;
            }
        }

        return $written;
    }
}
