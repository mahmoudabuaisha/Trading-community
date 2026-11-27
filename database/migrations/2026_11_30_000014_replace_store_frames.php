<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The store's frames become the six illustrated frames (bronze to legend).
 * A built-in frame someone already bought keeps its row and becomes its new
 * counterpart, so members keep what they paid for; every other frame is removed.
 */
return new class extends Migration
{
    public const FRAMES = [
        'bronze' => ['إطار برونزي', 'إطار أساسي مميز', 30],
        'silver' => ['إطار فضي', 'إطار فاخر', 100],
        'gold' => ['إطار ذهبي', 'إطار مميز وفخم', 250],
        'royal' => ['إطار ملكي', 'إطار حصري وفاخر', 500],
        'elite' => ['إطار النخبة', 'إطار الأساطير', 800],
        'legend' => ['إطار الأسطورة', 'إطار نادر وخارق', 1200],
    ];

    public function up(): void
    {
        $renamed = ['diamond' => 'elite'];
        $kept = [];
        foreach (DB::table('store_items')->where('kind', 'frame')->orderBy('id')->get() as $item) {
            $style = $renamed[$item->value] ?? $item->value;
            if (! isset(self::FRAMES[$style]) || isset($kept[$style])) {
                DB::table('store_items')->where('id', $item->id)->delete();

                continue;
            }
            [$name, $description, $price] = self::FRAMES[$style];
            DB::table('store_items')->where('id', $item->id)->update([
                'name' => $name, 'description' => $description, 'value' => $style, 'price' => $price,
                'image_path' => null, 'is_active' => true, 'updated_at' => now(),
            ]);
            $kept[$style] = true;
        }
        foreach (self::FRAMES as $style => [$name, $description, $price]) {
            if (! isset($kept[$style])) {
                DB::table('store_items')->insert([
                    'name' => $name, 'description' => $description, 'kind' => 'frame', 'value' => $style,
                    'price' => $price, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void {}
};
