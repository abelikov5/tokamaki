<?php

namespace App\Jobs;

use App\Models\Poster;
use App\Services\AiPosterContentService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class GeneratePosterContentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 450;

    public int $uniqueFor = 600;

    public function __construct(public int $posterId, public bool $updateSlug = false) {}

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function uniqueId(): string
    {
        return (string) $this->posterId;
    }

    public function handle(AiPosterContentService $service): void
    {
        $poster = Poster::findOrFail($this->posterId);
        $poster->update([
            'ai_generation_status' => 'processing',
            'ai_generation_error' => null,
        ]);

        $content = $service->generate($poster);

        if ($this->updateSlug) {
            $content['slug'] = Str::slug((string) $content['title']).'-'.$poster->nm_id;
        }

        $poster->update([
            ...$content,
            'ai_generation_status' => 'generated',
            'ai_generation_error' => null,
            'ai_input_hash' => $poster->content_hash,
            'ai_generated_at' => now(),
        ]);

        $poster->images()->update(['alt_text' => $poster->fresh()->title]);
    }

    public function failed(?Throwable $exception): void
    {
        Poster::whereKey($this->posterId)->update([
            'ai_generation_status' => 'failed',
            'ai_generation_error' => Str::limit($exception?->getMessage() ?? 'Неизвестная ошибка генерации', 2000),
        ]);
    }
}
