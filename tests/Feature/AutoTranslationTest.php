<?php

namespace Tests\Feature;

use App\Models\Motivation;
use App\Models\User;
use App\Services\Translation\AutoTranslator;
use App\Services\Translation\TextTranslator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoTranslationTest extends TestCase
{
    use RefreshDatabase;

    private int $calls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.api_key' => 'test-key', 'assistant.auto_translate' => true]);
        $test = $this;
        $this->app->instance(TextTranslator::class, new class($test) implements TextTranslator
        {
            public function __construct(private readonly AutoTranslationTest $test) {}

            public function translate(array $texts, array $locales): array
            {
                $this->test->counted();
                $out = [];
                foreach ($locales as $locale) {
                    foreach ($texts as $field => $text) {
                        $out[$locale][$field] = "[{$locale}] {$text}";
                    }
                }

                return $out;
            }
        });
    }

    public function counted(): void
    {
        $this->calls++;
    }

    private function motivation(): Motivation
    {
        return Motivation::create(['title' => 'ابدأ الآن', 'body' => 'لا تنتظر', 'category' => 'motivation', 'is_active' => true]);
    }

    public function test_members_read_admin_text_in_their_language(): void
    {
        $motivation = $this->motivation();
        app(AutoTranslator::class)->translate($motivation);

        $this->assertSame('[en] ابدأ الآن', $motivation->fresh()->translations->where('locale', 'en')->firstWhere('field', 'title')->text);
        app()->setLocale('fr');
        $this->assertSame('[fr] لا تنتظر', $motivation->fresh()->tr('body'));
    }

    public function test_nothing_is_retranslated_until_the_text_changes(): void
    {
        $motivation = $this->motivation();
        $translator = app(AutoTranslator::class);
        $translator->translate($motivation);
        $this->assertSame(0, $translator->translate($motivation->fresh()));
        $this->assertSame(1, $this->calls);

        $motivation->update(['body' => 'ابدأ بخمس دقائق']);
        $this->assertSame(3, $translator->translate($motivation->fresh()));
        app()->setLocale('en');
        $this->assertSame('[en] ابدأ بخمس دقائق', $motivation->fresh()->tr('body'));
    }

    public function test_hand_written_translations_are_kept(): void
    {
        $motivation = $this->motivation();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->putJson("/api/admin/translations/motivation/{$motivation->id}", [
            'translations' => ['title' => ['en' => 'Start now']],
        ])->assertOk();

        app(AutoTranslator::class)->translate($motivation->fresh());
        $english = $motivation->fresh()->translations->where('locale', 'en')->keyBy('field');
        $this->assertSame('Start now', $english['title']->text);
        $this->assertFalse($english['title']->is_auto);
        $this->assertSame('[en] لا تنتظر', $english['body']->text);
        $this->assertTrue($english['body']->is_auto);
    }

    public function test_the_command_does_nothing_without_a_key(): void
    {
        config(['assistant.api_key' => null]);
        $this->motivation();
        $this->artisan('translations:auto')->expectsOutputToContain('off')->assertSuccessful();
        $this->assertSame(0, $this->calls);
    }
}
