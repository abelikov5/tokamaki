<?php

namespace Tests\Feature;

use App\Jobs\ImportPostersJob;
use App\Models\PosterImportSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledPosterImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_schedule_dispatches_one_configured_import_per_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 00:30:00', 'UTC'));
        $schedule = PosterImportSchedule::current();
        $schedule->update([
            'enabled' => true,
            'scheduled_time' => '03:30',
            'timezone' => 'Europe/Moscow',
            'random_count' => 20,
            'image_limit' => 3,
            'only_new' => true,
            'generate_ai' => true,
        ]);
        Queue::fake([ImportPostersJob::class]);

        $this->artisan('posters:dispatch-scheduled-import')->assertSuccessful();
        $this->artisan('posters:dispatch-scheduled-import')->assertSuccessful();

        $this->assertDatabaseHas('poster_imports', [
            'source' => 'scheduled',
            'selection_mode' => 'random',
            'random_count' => 20,
            'image_limit' => 3,
            'only_new' => true,
            'generate_ai' => true,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('poster_imports', 1);
        $this->assertNotNull($schedule->fresh()->last_run_at);
        Queue::assertPushed(ImportPostersJob::class, 1);
    }

    public function test_disabled_schedule_does_not_dispatch_an_import(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 00:30:00', 'UTC'));
        PosterImportSchedule::current()->update([
            'enabled' => false,
            'scheduled_time' => '03:30',
            'timezone' => 'Europe/Moscow',
        ]);
        Queue::fake([ImportPostersJob::class]);

        $this->artisan('posters:dispatch-scheduled-import')->assertSuccessful();

        $this->assertDatabaseCount('poster_imports', 0);
        Queue::assertNothingPushed();
    }
}
