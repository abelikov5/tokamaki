<?php

namespace App\Jobs;

use App\Models\IndexNowSubmission;
use App\Services\IndexNowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SubmitUrlsToIndexNowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @param array<int, string> $urls */
    public function __construct(public array $urls, public ?int $posterImportId = null) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(IndexNowService $service): void
    {
        $submittedCount = $service->submit($this->urls);

        IndexNowSubmission::create([
            'poster_import_id' => $this->posterImportId,
            'source' => $this->posterImportId === null ? 'ai_generation' : 'import',
            'status' => 'completed',
            'urls_count' => $submittedCount,
            'submitted_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        IndexNowSubmission::create([
            'poster_import_id' => $this->posterImportId,
            'source' => $this->posterImportId === null ? 'ai_generation' : 'import',
            'status' => 'failed',
            'error' => $exception?->getMessage() ?? 'IndexNow submission failed.',
        ]);
    }
}
