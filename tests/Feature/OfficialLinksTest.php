<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_visitors_see_the_official_links_and_admins_edit_them(): void
    {
        $this->getJson('/api/official-links')->assertOk()->assertJsonPath('links.0.url', 'https://www.instagram.com/sntradex');

        $admin = User::factory()->create(['role' => 'admin']);
        $member = User::factory()->create();
        $links = ['links' => [['kind' => 'telegram', 'label' => 'Channel', 'url' => 'https://t.me/sntradex']]];
        $this->actingAs($member)->putJson('/api/admin/official-links', $links)->assertForbidden();
        $this->actingAs($admin)->putJson('/api/admin/official-links', ['links' => [['kind' => 'other', 'label' => 'x', 'url' => 'javascript:alert(1)']]])->assertUnprocessable();
        $this->putJson('/api/admin/official-links', $links)->assertOk();
        $this->getJson('/api/official-links')->assertJsonCount(1, 'links')->assertJsonPath('links.0.kind', 'telegram');
    }
}
