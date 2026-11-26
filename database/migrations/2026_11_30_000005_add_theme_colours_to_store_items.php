<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The colours of the themes that used to be written in the stylesheet. */
    private const BUILT_IN = ['emerald' => '#3fc48a', 'sapphire' => '#5a94f0', 'ruby' => '#e8566f', 'amethyst' => '#9a6bf0', 'silver' => '#a9aebb'];

    public function up(): void
    {
        // A theme is data now: an accent colour the app derives its shades from,
        // an optional base colour for the background, and an optional picture
        // that replaces the mountain behind the pages.
        Schema::table('store_items', function (Blueprint $table): void {
            $table->string('theme_accent', 7)->nullable();
            $table->string('theme_background', 7)->nullable();
            $table->string('theme_image_path')->nullable();
        });
        foreach (DB::table('store_items')->where('kind', 'theme')->get(['id', 'value']) as $item) {
            DB::table('store_items')->where('id', $item->id)->update(['theme_accent' => self::BUILT_IN[$item->value] ?? '#d6ad62', 'value' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('store_items', fn (Blueprint $table) => $table->dropColumn(['theme_accent', 'theme_background', 'theme_image_path']));
    }
};
