<?php

namespace App\Jobs;

use App\Services\IndexNowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubmitUrlsToIndexNowJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /** @param array<int, string> $urls */
    public function __construct(public array $urls) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(IndexNowService $service): void
    {
        $service->submit($this->urls);
    }
}
