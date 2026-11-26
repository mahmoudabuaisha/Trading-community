<?php

namespace Database\Seeders;

use App\Models\Motivation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Idempotent: safe to run on every deploy. */
    public function run(): void
    {
        $admin = $this->seedAdmin();
        $this->seedMotivations($admin);
    }

    private function seedAdmin(): ?User
    {
        ['name' => $name, 'email' => $email, 'password' => $password] = config('workspace.admin');

        if (! $email || ! $password) {
            $this->command?->warn('ADMIN_EMAIL / ADMIN_PASSWORD are not set — no administrator was created.');

            return User::where('role', User::ROLE_ADMIN)->first();
        }

        $admin = User::firstOrNew(['email' => $email]);
        if (! $admin->exists) {
            $admin->fill(['display_name' => $name ?: 'Admin', 'password' => $password]);
        }
        // Role is not mass assignable by design.
        $admin->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => $admin->email_verified_at ?? now()])->save();

        return $admin;
    }

    private function seedMotivations(?User $admin): void
    {
        if (Motivation::exists()) {
            return;
        }

        $samples = [
            ['category' => 'growth', 'title' => 'عقلية الاستمرارية', 'body' => 'النتائج الكبيرة لا تأتي من يوم استثنائي واحد، بل من أيام عادية تلتزم فيها بما وعدت به نفسك. اختر مهمة واحدة مهمة كل صباح وأنجزها قبل أي شيء آخر.'],
            ['category' => 'motivation', 'title' => 'ابدأ قبل أن تكون جاهزاً', 'body' => 'لا تنتظر اللحظة المثالية. خمس دقائق من البدء الفعلي أفضل من ساعة من التخطيط. الحركة تولّد الوضوح، والوضوح يولّد الحافز.'],
            ['category' => 'announcement', 'title' => 'مرحباً بك في مركز قيادة يومك', 'body' => 'نظّم مهامك اليومية والأسبوعية، سجّل جلسات تركيزك، وراجع يومك كل مساء. كل ما تحتاجه لتتابع تقدّمك موجود في مكان واحد.'],
        ];

        foreach ($samples as $sample) {
            $motivation = new Motivation($sample + ['is_active' => true]);
            $motivation->created_by = $admin?->id;
            $motivation->save();
        }
        // Their English and French versions.
        (require database_path('migrations/2026_11_30_000012_translate_starter_messages.php'))->up();
    }
}
