<?php

namespace Tests\Feature;

use App\Models\SiteCopy;
use App\Models\User;
use App\Services\Translation\AutoTranslator;
use App\Services\Translation\TextTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_rewrite_a_phrase_once_and_visitors_read_it_in_their_language(): void
    {
        config(['assistant.api_key' => 'test-key', 'assistant.auto_translate' => true]);
        $this->app->instance(TextTranslator::class, new class implements TextTranslator
        {
            public function translate(array $texts, array $locales): array
            {
                return collect($locales)->mapWithKeys(fn ($locale) => [$locale => array_map(fn ($text) => "[{$locale}] {$text}", $texts)])->all();
            }
        });
        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();

        $this->actingAs($member)->putJson('/api/admin/site-copy', ['key' => 'auth.tagline', 'text' => 'x'])->assertForbidden();
        $this->actingAs($admin)->putJson('/api/admin/site-copy', ['key' => 'users.secret', 'text' => 'x'])->assertUnprocessable();
        $saved = $this->putJson('/api/admin/site-copy', ['key' => 'auth.tagline', 'text' => 'خطّط، نفّذ، انتصر'])->assertOk()->assertJsonPath('autoTranslate', true);
        $this->assertSame('خطّط، نفّذ، انتصر', $saved->json('copy')['auth.tagline']);
        app(AutoTranslator::class)->translate(SiteCopy::first());

        $french = $this->withHeader('Accept-Language', 'fr')->getJson('/api/site-copy')->assertOk()->json('copy');
        $this->assertSame('[fr] خطّط، نفّذ، انتصر', $french['auth.tagline']);

        // Clearing it brings the built-in phrase back.
        $this->actingAs($admin)->putJson('/api/admin/site-copy', ['key' => 'auth.tagline', 'text' => ''])->assertOk();
        $this->assertSame(0, SiteCopy::count());
    }
}
