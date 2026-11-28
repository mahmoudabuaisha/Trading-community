<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** A free top-down hierarchy for skill paths: the first level at the top, the rest branching beneath in order. */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('designs')->where('target', 'skill_tree')->where('layout', 'hierarchy')->exists()) {
            DB::table('designs')->insert([
                'target' => 'skill_tree', 'layout' => 'hierarchy', 'name' => 'التسلسل الهرمي',
                'description' => 'أول مستوى في القمة، وكل مستوى يتفرع تحته بالترتيب.',
                'price' => 0, 'accent' => '#5a94f0', 'node_shape' => 'rounded', 'position' => 0, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('designs')->where('target', 'skill_tree')->where('layout', 'hierarchy')->delete();
    }
};
