<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\TranslationController;
use App\Services\Translation\AutoTranslator;
use Illuminate\Console\Command;
use Throwable;

/** Translates everything the administrators wrote that lacks a current translation. */
class AutoTranslateContent extends Command
{
    protected $signature = 'translations:auto';

    protected $description = 'Machine-translate admin-written content into every supported language';

    public function handle(AutoTranslator $translator): int
    {
        if (! AutoTranslator::enabled()) {
            $this->info('Automatic translation is off (set ANTHROPIC_API_KEY in .env).');

            return self::SUCCESS;
        }
        $written = 0;
        $failed = 0;
        foreach (TranslationController::models() as $class) {
            $class::query()->orderBy('id')->each(function ($model) use ($translator, &$written, &$failed): void {
                try {
                    $written += $translator->translate($model);
                } catch (Throwable $error) {
                    $failed++;
                    report($error);
                }
            });
        }
        $this->info("Translated {$written} texts".($failed ? ", {$failed} items failed (see the log)" : '').'.');

        return self::SUCCESS;
    }
}
