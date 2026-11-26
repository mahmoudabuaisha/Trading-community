<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Short lines written by the administrators; each member draws one a day.
        Schema::create('daily_tips', function (Blueprint $table): void {
            $table->id();
            $table->string('body', 300);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // The draw of each member for each of their local days. The unique index
        // is what limits a member to one draw a day.
        Schema::create('daily_tip_draws', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_tip_id')->nullable()->constrained()->nullOnDelete();
            $table->date('day');
            // Kept so the member still sees today's line if the admin edits or deletes it.
            $table->string('body', 300);
            $table->timestamps();
            $table->unique(['user_id', 'day']);
        });

        // A starting set, so the button works from day one. Editable and removable in the admin center.
        $now = now();
        $tips = [
            'ابدأ يومك بمهمة واحدة صغيرة، والإنجاز سيجرّ الإنجاز.',
            'الانضباط هو أن تفعل ما يجب حتى عندما لا تشعر بالرغبة.',
            'لا تقارن بدايتك بمنتصف طريق غيرك.',
            'خطوة صغيرة كل يوم أفضل من قفزة كبيرة مرة في السنة.',
            'الصبر في السوق ليس انتظاراً، بل التزام بالخطة.',
            'اكتب أهدافك؛ ما يُكتب يُنجز.',
            'الخسارة الصغيرة المدروسة خير من الربح الكبير العشوائي.',
            'ركّز على ما تستطيع التحكم به، واترك الباقي.',
            'يومك الجيد يبدأ من ليلتك: نم مبكراً.',
            'كل مهمة تنجزها اليوم هي وعد تفي به لنفسك.',
            'التعلم المستمر هو أفضل استثمار لا يخسر.',
            'لا تنتظر الحافز، ابدأ والحافز سيلحق بك.',
            'راجع يومك قبل النوم: ماذا تعلمت؟ وماذا ستحسّن غداً؟',
            'العادات الصغيرة تصنع النتائج الكبيرة.',
            'الوضوح قوة: اعرف ماذا تريد من يومك قبل أن يبدأ.',
            'الراحة جزء من الإنجاز، لا عكسه.',
            'قلّل المشتتات، وستتضاعف إنتاجيتك.',
            'إدارة المخاطر أولاً، ثم الأرباح.',
            'النجاح هو مجموع جهود صغيرة تتكرر يوماً بعد يوم.',
            'افعل اليوم ما سيشكرك عليه نفسك غداً.',
            'لا بأس بالبطء، المهم ألا تتوقف.',
            'خطتك الواضحة تحميك من قراراتك المتسرعة.',
            'ابدأ بالأصعب، وسيبدو باقي اليوم أسهل.',
            'الامتنان يفتح باب الهدوء: اذكر ثلاثة أشياء جيدة في يومك.',
            'الثقة تأتي من الاستعداد، لا من الحظ.',
            'اجعل هدفك أكبر من أعذارك.',
            'كل خطأ درس، إذا كتبته وتعلمت منه.',
            'التزامك اليوم هو حريتك غداً.',
            'احمِ وقتك كما تحمي مالك.',
            'أنت أقرب لهدفك مما كنت عليه أمس.',
        ];
        DB::table('daily_tips')->insert(array_map(fn (string $body): array => ['body' => $body, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now], $tips));
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_tip_draws');
        Schema::dropIfExists('daily_tips');
    }
};
