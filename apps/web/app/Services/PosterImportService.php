<?php

namespace App\Services;

use App\Models\Poster;
use App\Models\PosterImage;
use App\Models\PosterImport;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PosterImportService
{
    public function __construct(private readonly PosterContentBuilder $contentBuilder) {}

    public function run(PosterImport $posterImport): void
    {
        $posterImport->update(['status' => 'running', 'started_at' => now(), 'errors' => null]);
        $errors = [];
        $latestByDesignAndSize = [];

        foreach ($this->sourceRecords($posterImport) as $source) {
            $sourceKey = isset($source['group_id']) && $source['group_id'] !== null
                ? 'group:'.$source['group_id']
                : 'item:'.$source['source_id'];
            $latestByDesignAndSize[$sourceKey.'|'.$this->detectSize($source)] = $source;
        }

        foreach ($latestByDesignAndSize as $source) {
            try {
                $wasCreated = $this->syncPoster($posterImport->source_user_id, $source);
                $posterImport->increment('processed_count');
                $posterImport->increment($wasCreated ? 'created_count' : 'updated_count');
            } catch (Throwable $exception) {
                $posterImport->increment('processed_count');
                $posterImport->increment('failed_count');
                $errors[] = [
                    'nm_id' => $source['nm_id'] ?? null,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $posterImport->update([
            'status' => $errors === [] ? 'completed' : 'completed_with_errors',
            'errors' => $errors === [] ? null : $errors,
            'finished_at' => now(),
        ]);
    }

    /**
     * @return iterable<int, array<string, mixed>>
     */
    private function sourceRecords(PosterImport $posterImport): iterable
    {
        $client = $this->sourceClient();
        $query = ['user_id' => $posterImport->source_user_id, 'limit' => 500];

        if ($posterImport->requested_nm_ids !== null) {
            foreach ($posterImport->requested_nm_ids as $nmId) {
                yield $client->get('/api/internal/v1/posters/'.$nmId, [
                    'user_id' => $posterImport->source_user_id,
                ])->throw()->json('data');
            }

            return;
        }

        do {
            $response = $client->get('/api/internal/v1/posters', $query)->throw();
            $payload = $response->json();

            foreach ($payload['data'] ?? [] as $source) {
                yield $source;
            }

            $cursor = data_get($payload, 'meta.next_cursor');
            $query['cursor'] = $cursor;
        } while ($cursor !== null);
    }

    private function sourceClient(): PendingRequest
    {
        return Http::baseUrl((string) config('services.mockup_catalog.url'))
            ->withToken((string) config('services.mockup_catalog.token'))
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 500);
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function syncPoster(int $sourceUserId, array $source): bool
    {
        $sourceKey = isset($source['group_id']) && $source['group_id'] !== null
            ? 'group:'.$source['group_id']
            : 'item:'.$source['source_id'];
        $contentHash = hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        [$poster, $wasCreated] = DB::transaction(function () use ($sourceUserId, $sourceKey, $contentHash, $source): array {
            $poster = Poster::firstOrNew([
                'source_user_id' => $sourceUserId,
                'source_key' => $sourceKey,
            ]);
            $wasCreated = ! $poster->exists;

            if ($wasCreated || $poster->status === 'draft') {
                $content = $this->contentBuilder->build($source);
                $poster->fill($content);
                $poster->fill([
                    'slug' => Str::slug($content['title']).'-'.$source['nm_id'],
                ]);

                if ($wasCreated) {
                    $poster->status = 'draft';
                }
            }

            $poster->fill([
                'source_id' => $poster->source_id ?? $source['source_id'],
                'source_group_id' => $source['group_id'] ?? null,
                'nm_id' => (string) $source['nm_id'],
                'vendor_code' => $source['vendor_code'] ?? null,
                'source_payload' => $source,
                'content_hash' => $contentHash,
                'source_updated_at' => $source['updated_at'] ?? null,
                'imported_at' => now(),
            ])->save();

            $size = $this->detectSize($source);
            $poster->variants()->updateOrCreate(
                ['size_code' => $size],
                [
                    'source_id' => $source['source_id'],
                    'nm_id' => (string) $source['nm_id'],
                    'vendor_code' => $source['vendor_code'] ?? null,
                    'width_cm' => (int) Str::before($size, 'x'),
                    'height_cm' => (int) Str::after($size, 'x'),
                    'price' => config('catalog.prices.'.$size),
                    'is_active' => true,
                ],
            );

            return [$poster, $wasCreated];
        });

        $this->syncImages($poster, $source['photos'] ?? []);

        if ($poster->status === 'draft' && $poster->images()->exists()) {
            $poster->update(['status' => 'published']);
        }

        return $wasCreated;
    }

    /**
     * @param  array<string, mixed>  $source
     */
    private function detectSize(array $source): string
    {
        $text = mb_strtolower(json_encode([
            $source['title'] ?? null,
            $source['characteristics'] ?? [],
            $source['dimensions'] ?? [],
            $source['sizes'] ?? [],
        ], JSON_UNESCAPED_UNICODE));

        if (preg_match('/(?:30\s*[xх×]\s*40|40\s*[xх×]\s*30)/u', $text) === 1) {
            return '30x40';
        }

        $length = (int) data_get($source, 'dimensions.length', 0);
        $width = (int) data_get($source, 'dimensions.width', 0);
        $longSide = max($length, $width);
        $shortSide = min($length, $width);

        if ($longSide >= 38 && $longSide <= 45 && $shortSide >= 28 && $shortSide <= 35) {
            return '30x40';
        }

        return '40x50';
    }

    /**
     * @param  array<int, array<string, mixed>>  $photos
     */
    private function syncImages(Poster $poster, array $photos): void
    {
        foreach ($photos as $photo) {
            $image = PosterImage::firstOrNew([
                'poster_id' => $poster->id,
                'source_id' => $photo['source_id'],
            ]);

            $image->fill([
                'role' => $photo['role'] ?? 'slide',
                'sort_order' => (int) ($photo['sort_order'] ?? 0),
                'remote_url' => $photo['url'] ?? null,
                'mime_type' => $photo['mime_type'] ?? null,
                'alt_text' => $poster->title,
            ]);

            if (! $image->path && filled($image->remote_url)) {
                $this->mirrorImage($image, (string) ($photo['file_name'] ?? 'poster.jpg'));
            }

            $image->save();
        }
    }

    private function mirrorImage(PosterImage $image, string $fileName): void
    {
        try {
            $response = Http::timeout(45)->retry(2, 500)->get((string) $image->remote_url)->throw();
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';
            $contents = $response->body();
            $checksum = hash('sha256', $contents);
            $path = 'posters/'.$image->poster_id.'/'.$image->source_id.'-'.$checksum.'.'.$extension;

            Storage::disk('public')->put($path, $contents);
            $image->path = $path;
            $image->checksum = $checksum;
        } catch (Throwable) {
            // The remote URL remains available as a fallback; the next import retries mirroring.
        }
    }
}
