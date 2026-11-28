<?php

namespace App\Services;

use App\Models\DayEntry;
use App\Models\PortfolioProject;
use App\Models\User;
use App\Models\VisionItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Removes a member's account and everything in it: tasks, goals, habits,
 * journey, purchases, messages, sessions and uploaded pictures. The email is
 * free again, so the person can sign up afresh.
 */
class AccountDeletion
{
    public function delete(User $user): void
    {
        // Pictures live on disk; the database cascade does not reach them.
        $files = array_merge(
            VisionItem::whereIn('vision_board_id', $user->visionBoards()->select('id'))->whereNotNull('image_path')->pluck('image_path')->all(),
            PortfolioProject::where('user_id', $user->id)->whereNotNull('image_path')->pluck('image_path')->all(),
            DayEntry::where('user_id', $user->id)->whereNotNull('image_path')->pluck('image_path')->all(),
        );

        DB::transaction(function () use ($user): void {
            DB::table('sessions')->where('user_id', $user->id)->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            // Every other row that belongs to the member goes with the account (foreign keys cascade).
            $user->delete();
        });

        if ($files) {
            Storage::disk('public')->delete($files);
        }
    }
}
