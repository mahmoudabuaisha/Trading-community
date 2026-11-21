<?php

namespace Tests\Feature;

use App\Models\AdminAudit;
use App\Models\Motivation;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public static function adminRoutes(): array
    {
        return [
            ['GET', '/api/admin/overview'],
            ['GET', '/api/admin/users'],
            ['PATCH', '/api/admin/users/{user}'],
            ['POST', '/api/admin/users/{user}/revoke-sessions'],
            ['GET', '/api/admin/motivations'],
            ['POST', '/api/admin/motivations'],
            ['PATCH', '/api/admin/motivations/{motivation}'],
            ['DELETE', '/api/admin/motivations/{motivation}'],
        ];
    }

    #[DataProvider('adminRoutes')]
    public function test_non_admins_are_refused_on_every_admin_route(string $method, string $uri): void
    {
        $member = User::factory()->create();
        $motivation = Motivation::create(['title' => 't', 'body' => 'b', 'category' => 'growth']);
        $uri = str_replace(['{user}', '{motivation}'], [$member->id, $motivation->id], $uri);
        $payload = ['role' => 'admin', 'title' => 'x', 'body' => 'y', 'category' => 'growth'];

        $this->json($method, $uri, $payload)->assertUnauthorized();
        $this->actingAs($member)->json($method, $uri, $payload)->assertForbidden();

        $this->assertSame('user', $member->fresh()->role);
        $this->assertSame(1, Motivation::count());
        $this->assertSame(0, AdminAudit::count());
    }

    public function test_overview_reports_platform_stats_and_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        Task::factory()->for($member)->count(2)->create();
        Task::factory()->for($member)->completed()->create();
        DB::table('sessions')->insert([
            ['id' => 'recent', 'user_id' => $member->id, 'payload' => '', 'last_activity' => now()->subHour()->getTimestamp()],
            ['id' => 'recent-2', 'user_id' => $member->id, 'payload' => '', 'last_activity' => now()->getTimestamp()],
            ['id' => 'stale', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => now()->subDays(3)->getTimestamp()],
            ['id' => 'guest', 'user_id' => null, 'payload' => '', 'last_activity' => now()->getTimestamp()],
        ]);

        $this->actingAs($admin)->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJson(['stats' => ['users' => 2, 'tasks' => 3, 'completed' => 1, 'activeToday' => 1]])
            ->assertJsonCount(0, 'audit');
    }

    public function test_users_list_is_paginated_and_searchable(): void
    {
        $admin = User::factory()->admin()->create(['display_name' => 'Root', 'email' => 'root@example.com']);
        User::factory()->count(24)->create();
        User::factory()->create(['display_name' => 'نور الدين', 'email' => 'nour@example.com']);

        $this->actingAs($admin)->getJson('/api/admin/users?search=&page=1')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta', ['currentPage' => 1, 'lastPage' => 2, 'total' => 26])
            ->assertJsonStructure(['data' => [['id', 'displayName', 'email', 'role', 'createdAt']]]);

        $this->getJson('/api/admin/users?search='.urlencode('نور'))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'nour@example.com');
        $this->getJson('/api/admin/users?search=NOUR@')->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/users?search=%25')->assertJsonCount(0, 'data');
    }

    public function test_admin_updates_account_details_and_role_with_audit(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create(['display_name' => 'Old', 'email' => 'old@example.com']);
        $this->actingAs($admin);

        $this->patchJson("/api/admin/users/{$member->id}", ['displayName' => 'New', 'email' => 'new@example.com'])
            ->assertOk()->assertJsonPath('user.displayName', 'New')->assertJsonPath('user.email', 'new@example.com');

        $this->patchJson("/api/admin/users/{$member->id}", ['role' => 'admin'])
            ->assertOk()->assertJsonPath('user.role', 'admin');

        $this->patchJson("/api/admin/users/{$member->id}", ['email' => $admin->email])
            ->assertStatus(422)->assertJsonValidationErrors('email');

        $audits = AdminAudit::orderBy('id')->get();
        $this->assertSame(['user_updated', 'role_changed'], $audits->pluck('action')->all());
        $this->assertSame(['name' => 'New', 'emailChanged' => true], $audits[0]->details);
        $this->assertSame(['name' => 'New', 'from' => 'user', 'to' => 'admin'], $audits[1]->details);
        $this->assertSame($member->id, $audits[1]->target_user_id);

        $this->getJson('/api/admin/overview')
            ->assertJsonPath('audit.0.action', 'role_changed')
            ->assertJsonPath('audit.0.adminName', $admin->display_name)
            ->assertJsonPath('audit.0.targetUserId', $member->id);
    }

    public function test_the_last_admin_and_self_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $this->patchJson("/api/admin/users/{$admin->id}", ['role' => 'user'])
            ->assertStatus(422)->assertJsonValidationErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);

        // With a second admin, self-demotion is still refused, but demoting the other works.
        $second = User::factory()->admin()->create();
        $this->patchJson("/api/admin/users/{$admin->id}", ['role' => 'user'])->assertStatus(422);
        $this->patchJson("/api/admin/users/{$second->id}", ['role' => 'user'])->assertOk();
        $this->assertSame('user', $second->fresh()->role);

        // The demoted admin immediately loses access.
        $this->actingAs($second->fresh())->getJson('/api/admin/overview')->assertForbidden();
    }

    public function test_revoking_sessions_deletes_them_and_writes_an_audit_row(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        DB::table('sessions')->insert([
            ['id' => 'a', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'b', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()],
            ['id' => 'c', 'user_id' => $other->id, 'payload' => '', 'last_activity' => time()],
        ]);

        $this->actingAs($admin)->postJson("/api/admin/users/{$member->id}/revoke-sessions")->assertNoContent();

        $this->assertSame(0, DB::table('sessions')->where('user_id', $member->id)->count());
        $this->assertSame(1, DB::table('sessions')->where('user_id', $other->id)->count());
        $this->assertNull($member->fresh()->remember_token);
        $this->assertDatabaseHas('admin_audits', ['action' => 'sessions_revoked', 'admin_user_id' => $admin->id, 'target_user_id' => $member->id]);
    }

    public function test_motivation_lifecycle_is_audited_and_drafts_stay_hidden_from_users(): void
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();
        $this->actingAs($admin);

        $id = $this->postJson('/api/admin/motivations', ['title' => 'عقلية', 'body' => 'نص', 'category' => 'motivation', 'isActive' => true])
            ->assertCreated()
            ->assertJson(['motivation' => ['title' => 'عقلية', 'body' => 'نص', 'category' => 'motivation', 'isActive' => true]])
            ->json('motivation.id');

        $this->postJson('/api/admin/motivations', ['title' => 'x', 'body' => 'y', 'category' => 'spam'])
            ->assertStatus(422)->assertJsonValidationErrors('category');

        $this->actingAs($member)->getJson('/api/dashboard')->assertJsonCount(1, 'motivations');

        $this->actingAs($admin)->patchJson("/api/admin/motivations/{$id}", ['isActive' => false])
            ->assertOk()->assertJsonPath('motivation.isActive', false)->assertJsonPath('motivation.title', 'عقلية');

        $this->actingAs($member)->getJson('/api/dashboard')->assertJsonCount(0, 'motivations');
        $this->actingAs($admin)->getJson('/api/admin/motivations')->assertJsonCount(1, 'motivations');

        $this->deleteJson("/api/admin/motivations/{$id}")->assertNoContent();
        $this->assertSame(0, Motivation::count());
        $this->assertSame(
            ['motivation_created', 'motivation_updated', 'motivation_deleted'],
            AdminAudit::orderBy('id')->pluck('action')->all(),
        );
    }
}
