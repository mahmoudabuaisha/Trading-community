<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Twelve illustrated titles replace the old ones (a title someone bought
 * keeps its row and becomes its new counterpart), and six illustrated
 * profile pictures join the store. Pictures can be meant for men, for women
 * or for everyone.
 */
return new class extends Migration
{
    /** style => [title text, store name, description, price] */
    public const TITLES = [
        'committed' => ['الملتزم', 'لقب الملتزم', 'لمن يلتزم بمهامه يوماً بعد يوم', 50],
        'persistent' => ['المثابر', 'لقب المثابر', 'لمن لا يتوقف حتى يصل', 80],
        'achiever' => ['المنجز', 'لقب المنجز', 'لمن يحوّل خططه إلى إنجاز', 120],
        'ambitious' => ['الطموح', 'لقب الطموح', 'لمن يرى القمة دائماً', 150],
        'creative' => ['المبدع', 'لقب المبدع', 'لصاحب الأفكار الجديدة', 200],
        'excellent' => ['المتفوق', 'لقب المتفوق', 'لمن يتعلم ويتفوق', 250],
        'challenger' => ['المتحدي', 'لقب المتحدي', 'لمن يواجه الصعب بشجاعة', 300],
        'professional' => ['المحترف', 'لقب المحترف', 'لمن يتقن ما يفعل', 400],
        'wise' => ['الحكيم', 'لقب الحكيم', 'لصاحب القرار الهادئ', 500],
        'strategist' => ['الاستراتيجي', 'لقب الاستراتيجي', 'لمن يخطط بعيد المدى', 650],
        'leader' => ['القائد', 'لقب القائد', 'لمن يقود ويُلهم', 800],
        'legend' => ['الأسطورة', 'لقب الأسطورة', 'لقب نادر للأساطير', 1200],
    ];

    /** key => [store name, description, price] */
    public const PORTRAITS = [
        'bronze' => ['الصورة البرونزية', 'صورة شخصية متحركة بلمسة برونزية', 60],
        'silver' => ['الصورة الفضية', 'صورة شخصية متحركة بلمسة فضية', 150],
        'gold' => ['الصورة الذهبية', 'صورة شخصية متحركة بلمسة ذهبية', 300],
        'royal' => ['الصورة الملكية', 'صورة شخصية ملكية متحركة', 550],
        'elite' => ['صورة النخبة', 'صورة شخصية متحركة للنخبة', 850],
        'legend' => ['الصورة الأسطورية', 'صورة شخصية أسطورية متحركة', 1300],
    ];

    public function up(): void
    {
        Schema::table('store_items', function (Blueprint $table) {
            // A profile picture meant for men or for women; null means everyone.
            $table->string('audience', 10)->nullable();
            // The illustrated look of a title (icon and colour); null draws a plain gold title.
            $table->string('style', 24)->nullable();
        });

        $byText = collect(self::TITLES)->mapWithKeys(fn (array $title, string $style): array => [$title[0] => $style]);
        $kept = [];
        foreach (DB::table('store_items')->where('kind', 'title')->orderBy('id')->get() as $item) {
            $style = $byText[trim((string) $item->value)] ?? null;
            if (! $style || isset($kept[$style])) {
                DB::table('store_items')->where('id', $item->id)->delete();

                continue;
            }
            [$text, $name, $description, $price] = self::TITLES[$style];
            DB::table('store_items')->where('id', $item->id)->update(['name' => $name, 'description' => $description, 'value' => $text, 'price' => $price, 'style' => $style, 'is_active' => true, 'updated_at' => now()]);
            $kept[$style] = true;
        }
        foreach (self::TITLES as $style => [$text, $name, $description, $price]) {
            if (! isset($kept[$style])) {
                DB::table('store_items')->insert(['name' => $name, 'description' => $description, 'kind' => 'title', 'value' => $text, 'style' => $style, 'price' => $price, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        foreach (self::PORTRAITS as $key => [$name, $description, $price]) {
            $path = "builtin:avatars/{$key}.webp";
            if (! DB::table('store_items')->where('image_path', $path)->exists()) {
                DB::table('store_items')->insert(['name' => $name, 'description' => $description, 'kind' => 'avatar', 'value' => null, 'image_path' => $path, 'audience' => 'male', 'price' => $price, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('store_items', function (Blueprint $table) {
            $table->dropColumn(['audience', 'style']);
        });
    }
};
