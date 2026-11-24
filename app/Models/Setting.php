<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Setting extends Model
{
    /** Interface copy an administrator may override; null means "use the built-in text". */
    public const CONTENT_KEYS = ['tagline_ar', 'tagline_en', 'welcome_sub_ar', 'welcome_sub_en'];

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    /**
     * Every content key in wire (camelCase) form, null when not overridden.
     *
     * @return array<string, string|null>
     */
    public static function content(): array
    {
        $stored = self::whereIn('key', self::CONTENT_KEYS)->pluck('value', 'key');
        $content = [];
        foreach (self::CONTENT_KEYS as $key) {
            $content[Str::camel($key)] = $stored[$key] ?? null;
        }

        return $content;
    }
}
