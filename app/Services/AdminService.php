<?php

namespace App\Services;

use App\Models\AdminAudit;
use App\Models\Badge;
use App\Models\Broadcast;
use App\Models\Lesson;
use App\Models\Motivation;
use App\Models\Setting;
use App\Models\StoreItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

            if (array_key_exists('permissions', $changes)) {
                $permissions = array_values(array_intersect(User::GRANTABLE, $changes['permissions'] ?? []));
                if ($permissions !== ($target->permissions ?? [])) {
                    $target->forceFill(['permissions' => $permissions])->save();
                    $this->audit($admin, 'permissions_changed', $target, ['name' => $target->display_name, 'permissions' => $permissions]);
                }
            }

            if (array_key_exists('is_premium', $changes) && (bool) $changes['is_premium'] !== (bool) $target->is_premium) {
                $target->forceFill(['is_premium' => (bool) $changes['is_premium']])->save();
                $this->audit($admin, $target->is_premium ? 'premium_granted' : 'premium_revoked', $target, ['name' => $target->display_name]);
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
    /**
     * @param  array<string, string|null>  $content  Content keys in snake_case; null or '' clears an override.
     */
    public function saveContent(User $admin, array $content): void
    {
        DB::transaction(function () use ($admin, $content): void {
            foreach (Setting::CONTENT_KEYS as $key) {
                if (! array_key_exists($key, $content)) {
                    continue;
                }
                $value = trim((string) $content[$key]);
                Setting::updateOrCreate(['key' => $key], ['value' => $value === '' ? null : $value]);
            }
            $this->audit($admin, 'settings_updated', null, []);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveLesson(User $admin, ?Lesson $lesson, array $data, ?UploadedFile $image, bool $removeImage): Lesson
    {
        // Store the new file first so a failed write never leaves the row pointing at nothing.
        $path = $image?->store(Lesson::IMAGE_DIRECTORY, 'public');

        return DB::transaction(function () use ($admin, $lesson, $data, $path, $removeImage): Lesson {
            $creating = $lesson === null;
            $lesson ??= new Lesson;
            $lesson->fill($data);
            if ($path) {
                $lesson->image_path = $path;
            } elseif ($removeImage) {
                $lesson->image_path = null;
            }
            if ($creating) {
                $lesson->created_by = $admin->id;
                $lesson->position = $data['position'] ?? ((int) Lesson::max('position') + 1);
            }
            $lesson->save();
            $this->audit($admin, $creating ? 'lesson_created' : 'lesson_updated', null, ['name' => $lesson->title]);

            return $lesson;
        });
    }

    public function deleteLesson(User $admin, Lesson $lesson): void
    {
        DB::transaction(function () use ($admin, $lesson): void {
            $lesson->delete();
            $this->audit($admin, 'lesson_deleted', null, ['name' => $lesson->title]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveBadge(User $admin, ?Badge $badge, array $data): Badge
    {
        return DB::transaction(function () use ($admin, $badge, $data): Badge {
            $creating = $badge === null;
            $badge ??= new Badge;
            $badge->fill($data);
            if ($creating) {
                $badge->created_by = $admin->id;
            }
            $badge->save();
            $this->audit($admin, $creating ? 'badge_created' : 'badge_updated', null, ['name' => $badge->title]);

            return $badge;
        });
    }

    public function deleteBadge(User $admin, Badge $badge): void
    {
        DB::transaction(function () use ($admin, $badge): void {
            $badge->delete();
            $this->audit($admin, 'badge_deleted', null, ['name' => $badge->title]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveStoreItem(User $admin, ?StoreItem $item, array $data, ?UploadedFile $image, bool $removeImage, ?UploadedFile $file = null, bool $removeFile = false): StoreItem
    {
        $path = $image?->store(StoreItem::IMAGE_DIRECTORY, 'public');
        // The product's file goes to the private disk: only buyers may download it.
        $filePath = $file?->store(StoreItem::FILE_DIRECTORY, 'local');
        $oldFile = $item?->file_path;

        return DB::transaction(function () use ($admin, $item, $data, $path, $removeImage, $file, $filePath, $removeFile, $oldFile): StoreItem {
            $creating = $item === null;
            $item ??= new StoreItem;
            $item->fill($data);
            if ($path) {
                $item->image_path = $path;
            } elseif ($removeImage) {
                $item->image_path = null;
            }
            if ($filePath) {
                $item->file_path = $filePath;
                $item->file_name = mb_substr($file->getClientOriginalName(), 0, 160);
            } elseif ($removeFile) {
                $item->file_path = null;
                $item->file_name = null;
            }
            if ($creating) {
                $item->created_by = $admin->id;
            }
            $item->save();
            if ($oldFile && $oldFile !== $item->file_path) {
                Storage::disk('local')->delete($oldFile);
            }
            $this->audit($admin, $creating ? 'store_item_created' : 'store_item_updated', null, ['name' => $item->name]);

            return $item;
        });
    }

    /** Deleting an item removes it from everyone who bought it; their points come back, since spending is the sum of purchases. */
    public function deleteStoreItem(User $admin, StoreItem $item): void
    {
        DB::transaction(function () use ($admin, $item): void {
            $item->delete();
            $this->audit($admin, 'store_item_deleted', null, ['name' => $item->name]);
        });
    }

    public function scheduleBroadcast(User $admin, string $day, string $body): void
    {
        DB::transaction(function () use ($admin, $day, $body): void {
            Broadcast::where('kind', Broadcast::KIND_SCHEDULED)->whereDate('day', $day)->delete();
            if ($body !== '') {
                Broadcast::create(['kind' => Broadcast::KIND_SCHEDULED, 'day' => $day, 'body' => $body, 'created_by' => $admin->id]);
            }
            $this->audit($admin, 'broadcast_scheduled', null, ['name' => $day]);
        });
    }

    public function sendBroadcast(User $admin, string $day, string $body): Broadcast
    {
        return DB::transaction(function () use ($admin, $day, $body): Broadcast {
            $broadcast = Broadcast::create(['kind' => Broadcast::KIND_INSTANT, 'day' => $day, 'body' => $body, 'created_by' => $admin->id]);
            $this->audit($admin, 'broadcast_sent', null, ['name' => mb_strimwidth($body, 0, 60, '…')]);

            return $broadcast;
        });
    }

    public function deleteBroadcast(User $admin, Broadcast $broadcast): void
    {
        DB::transaction(function () use ($admin, $broadcast): void {
            $broadcast->delete();
            $this->audit($admin, 'broadcast_deleted', null, ['name' => mb_strimwidth($broadcast->body, 0, 60, '…')]);
        });
    }

    /** Write an audit row for an action that needs no other bookkeeping. */
    public function record(User $admin, string $action, string $name): void
    {
        $this->audit($admin, $action, null, ['name' => $name]);
    }

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
