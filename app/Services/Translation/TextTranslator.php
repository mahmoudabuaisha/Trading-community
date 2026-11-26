<?php

namespace App\Services\Translation;

interface TextTranslator
{
    /**
     * Put each text into every requested language.
     *
     * @param  array<string, string>  $texts  field => original text, in any language
     * @param  list<string>  $locales
     * @return array<string, array<string, string>> locale => field => text
     */
    public function translate(array $texts, array $locales): array;
}
