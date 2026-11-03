<?php

namespace App\Jobs;

use App\Models\PosterImport;
use App\Services\PosterImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ImportPostersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 900;

    public function __construct(public int $posterImportId) {}

    public function handle(PosterImportService $service): void
    {
        $posterImport = PosterImport::findOrFail($this->posterImportId);
        $service->run($posterImport);
    }

    public function failed(?Throwable $exception): void
    {
        PosterImport::whereKey($this->posterImportId)->update([
            'status' => 'failed',
            'errors' => [['message' => $exception?->getMessage() ?? 'Import failed.']],
            'finished_at' => now(),
        ]);
    }
}
