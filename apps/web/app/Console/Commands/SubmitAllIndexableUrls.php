<?php

namespace App\Console\Commands;

use App\Models\IndexNowSubmission;
use App\Models\Poster;
use App\Models\PosterCategory;
use App\Services\IndexNowService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

#[Signature('indexnow:submit-all')]
#[Description('Submit every public sitemap URL to Yandex IndexNow')]
class SubmitAllIndexableUrls extends Command
{
    public function handle(IndexNowService $service): int
    {
        $urls = collect([
            route('home'),
            route('posters.index'),
            route('posters.custom'),
        ])->merge(
            PosterCategory::query()
                ->active()
                ->whereHas('posters', fn (Builder $query) => $query->published())
                ->orderBy('id')
                ->get()
                ->map(fn (PosterCategory $category): string => route('posters.show', $category)),
        )->merge(
            Poster::query()
                ->published()
                ->orderBy('id')
                ->get()
                ->map(fn (Poster $poster): string => route('posters.show', $poster)),
        );

        try {
            $submittedCount = $service->submit($urls);
        } catch (Throwable $exception) {
            IndexNowSubmission::create([
                'source' => 'full',
                'status' => 'failed',
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        IndexNowSubmission::create([
            'source' => 'full',
            'status' => 'completed',
            'urls_count' => $submittedCount,
            'submitted_at' => now(),
        ]);

        $this->info("Отправлено URL в IndexNow: {$submittedCount}");

        return self::SUCCESS;
    }
}
