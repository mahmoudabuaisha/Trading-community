<?php

namespace App\Services;

use App\Models\AdminAudit;
use App\Models\Motivation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every administrative mutation goes through here so that the role
 * invariants hold and an audit row is written on every path.
 */
class AdminService
{
    /**
     * @param  array{display_name?: string, email?: string, role?: string}  $changes
     */
    public function updateUser(User $admin, User $target, array $changes): User
    {
        return DB::transaction(function () use ($admin, $target, $changes): User {
            if (isset($changes['role']) && $changes['role'] !== $target->role) {
                $this->changeRole($admin, $target, $changes['role']);
            }

            $profile = array_intersect_key($changes, array_flip(['display_name', 'email']));
            $target->fill($profile);
            if ($target->isDirty()) {
                $emailChanged = $target->isDirty('email');
                $target->save();
                $this->audit($admin, 'user_updated', $target, ['name' => $target->display_name, 'emailChanged' => $emailChanged]);
            }

            return $target;
        });
    }

    public function revokeSessions(User $admin, User $target): void
    {
        DB::transaction(function () use ($admin, $target): void {
            DB::table('sessions')->where('user_id', $target->id)->delete();
            // Invalidate "remember me" cookies as well, or the browser signs straight back in.
            $target->forceFill(['remember_token' => null])->save();
            $this->audit($admin, 'sessions_revoked', $target, ['name' => $target->display_name]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveMotivation(User $admin, ?Motivation $motivation, array $data): Motivation
    {
        return DB::transaction(function () use ($admin, $motivation, $data): Motivation {
            $creating = $motivation === null;
            $motivation ??= new Motivation;
            $motivation->fill($data);
            if ($creating) {
                $motivation->created_by = $admin->id;
            }
            $motivation->save();
            $this->audit($admin, $creating ? 'motivation_created' : 'motivation_updated', null, ['name' => $motivation->title]);

            return $motivation;
        });
    }

    public function deleteMotivation(User $admin, Motivation $motivation): void
    {
        DB::transaction(function () use ($admin, $motivation): void {
            $motivation->delete();
            $this->audit($admin, 'motivation_deleted', null, ['name' => $motivation->title]);
        });
    }

    private function changeRole(User $admin, User $target, string $role): void
    {
        $from = $target->role;

        if ($from === User::ROLE_ADMIN && $role !== User::ROLE_ADMIN) {
            if ($target->is($admin)) {
                throw ValidationException::withMessages(['role' => __('admin.cannot_demote_self')]);
            }
            // Lock the admin rows so two concurrent demotions cannot both pass the count.
            $admins = User::where('role', User::ROLE_ADMIN)->lockForUpdate()->count();
            if ($admins <= 1) {
                throw ValidationException::withMessages(['role' => __('admin.last_admin')]);
            }
        }

        $target->forceFill(['role' => $role])->save();
        $this->audit($admin, 'role_changed', $target, ['name' => $target->display_name, 'from' => $from, 'to' => $role]);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function audit(User $admin, string $action, ?User $target, array $details): void
    {
        AdminAudit::create([
            'admin_user_id' => $admin->id,
            'action' => $action,
            'target_user_id' => $target?->id,
            'details' => $details,
        ]);
    }
}
