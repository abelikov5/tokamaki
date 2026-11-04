<?php

namespace App\Console\Commands;

use App\Jobs\ImportPostersJob;
use App\Models\PosterImport;
use App\Models\PosterImportSchedule;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('posters:dispatch-scheduled-import')]
#[Description('Dispatch the configured daily poster import when it is due')]
class DispatchScheduledPosterImport extends Command
{
    public function handle(): int
    {
        PosterImportSchedule::current();

        $posterImport = DB::transaction(function (): ?PosterImport {
            $schedule = PosterImportSchedule::query()->lockForUpdate()->findOrFail(1);
            $now = now($schedule->timezone);

            if (! $schedule->enabled || $now->format('H:i') !== substr($schedule->scheduled_time, 0, 5)) {
                return null;
            }

            if ($schedule->last_run_at?->timezone($schedule->timezone)->isSameDay($now)) {
                return null;
            }

            $posterImport = PosterImport::create([
                'source_user_id' => $schedule->source_user_id,
                'source' => 'scheduled',
                'selection_mode' => 'random',
                'random_count' => $schedule->random_count,
                'only_new' => $schedule->only_new,
                'image_limit' => $schedule->image_limit,
                'generate_ai' => $schedule->generate_ai,
                'status' => 'pending',
            ]);

            ImportPostersJob::dispatch($posterImport->id);
            $schedule->update(['last_run_at' => now()]);

            return $posterImport;
        });

        if ($posterImport !== null) {
            $this->info("Scheduled poster import #{$posterImport->id} dispatched.");
        }

        return self::SUCCESS;
    }
}
