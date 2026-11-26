<?php

use App\Models\ContentTranslation;
use App\Models\Motivation;
use Illuminate\Database\Migrations\Migration;

/**
 * The three starter messages every platform begins with, in English and
 * French, so they follow the member's language without machine translation.
 * A message an administrator has reworded is left alone.
 */
return new class extends Migration
{
    public const STARTERS = [
        'عقلية الاستمرارية' => [
            'en' => ['title' => 'The consistency mindset', 'body' => 'Big results do not come from one extraordinary day, but from ordinary days when you keep the promises you made to yourself. Pick one important task every morning and finish it before anything else.'],
            'fr' => ['title' => "L'état d'esprit de la régularité", 'body' => "Les grands résultats ne viennent pas d'une journée exceptionnelle, mais de journées ordinaires où vous tenez vos promesses envers vous-même. Choisissez chaque matin une tâche importante et terminez-la avant tout le reste."],
        ],
        'ابدأ قبل أن تكون جاهزاً' => [
            'en' => ['title' => 'Start before you feel ready', 'body' => 'Do not wait for the perfect moment. Five minutes of actually starting beat an hour of planning. Action brings clarity, and clarity brings motivation.'],
            'fr' => ['title' => 'Commencez avant de vous sentir prêt', 'body' => "N'attendez pas le moment parfait. Cinq minutes d'action valent mieux qu'une heure de planification. L'action apporte la clarté, et la clarté apporte la motivation."],
        ],
        'مرحباً بك في مركز قيادة يومك' => [
            'en' => ['title' => 'Welcome to your daily command center', 'body' => 'Organise your daily and weekly tasks, log your focus sessions and review your day every evening. Everything you need to follow your progress is in one place.'],
            'fr' => ['title' => 'Bienvenue dans votre centre de commande quotidien', 'body' => 'Organisez vos tâches quotidiennes et hebdomadaires, enregistrez vos séances de concentration et faites le bilan chaque soir. Tout ce dont vous avez besoin pour suivre vos progrès est au même endroit.'],
        ],
    ];

    public function up(): void
    {
        foreach (self::STARTERS as $title => $locales) {
            foreach (Motivation::where('title', $title)->get() as $message) {
                foreach ($locales as $locale => $fields) {
                    foreach ($fields as $field => $text) {
                        $original = (string) $message->getAttribute($field);
                        $row = ContentTranslation::firstOrNew([
                            'translatable_type' => $message->getMorphClass(),
                            'translatable_id' => $message->id,
                            'field' => $field,
                            'locale' => $locale,
                        ]);
                        // Keep a current translation someone already wrote.
                        if ($row->exists && $row->source_hash === sha1($original)) {
                            continue;
                        }
                        $row->fill(['text' => $text, 'source_hash' => sha1($original)])->save();
                    }
                }
            }
        }
    }

    public function down(): void {}
};
