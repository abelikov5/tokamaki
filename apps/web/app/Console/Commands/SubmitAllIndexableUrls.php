<?php

namespace App\Console\Commands;

use App\Models\Poster;
use App\Models\PosterCategory;
use App\Services\IndexNowService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

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

        $submittedCount = $service->submit($urls);
        $this->info("Отправлено URL в IndexNow: {$submittedCount}");

        return self::SUCCESS;
    }
}
