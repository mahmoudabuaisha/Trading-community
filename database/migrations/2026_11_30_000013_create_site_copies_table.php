<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_copies', function (Blueprint $table) {
            $table->id();
            // The interface phrase it replaces, by its translation key (e.g. `auth.tagline`).
            $table->string('key', 80)->unique();
            // Written by an administrator in any language; translated for the others.
            $table->text('text');
            $table->timestamps();
        });

        // Carry over the home phrases written in the old Arabic/English fields.
        $old = DB::table('settings')->whereIn('key', ['tagline_ar', 'tagline_en', 'welcome_sub_ar', 'welcome_sub_en'])->pluck('value', 'key');
        foreach (['dash.tagline' => 'tagline', 'dash.welcomeSub' => 'welcome_sub'] as $copyKey => $prefix) {
            $text = trim((string) ($old["{$prefix}_ar"] ?? '')) ?: trim((string) ($old["{$prefix}_en"] ?? ''));
            if ($text !== '') {
                DB::table('site_copies')->insert(['key' => $copyKey, 'text' => $text, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('site_copies');
    }
};
