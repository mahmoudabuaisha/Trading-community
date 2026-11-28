<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** The six illustrated portraits for women, beside the six for men. */
return new class extends Migration
{
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
        foreach (self::PORTRAITS as $key => [$name, $description, $price]) {
            $path = "builtin:avatars/female-{$key}.webp";
            if (! DB::table('store_items')->where('image_path', $path)->exists()) {
                DB::table('store_items')->insert(['name' => $name, 'description' => $description, 'kind' => 'avatar', 'value' => null, 'image_path' => $path, 'audience' => 'female', 'price' => $price, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        DB::table('store_items')->where('image_path', 'like', 'builtin:avatars/female-%')->delete();
    }
};
