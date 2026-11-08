<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

class IndexNowService
{
    public const MAX_URLS_PER_REQUEST = 10_000;

    /** @param iterable<int, string> $urls */
    public function submit(iterable $urls): int
    {
        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST);
        $key = (string) config('services.indexnow.key');

        if (! is_string($host) || $host === '') {
            throw new RuntimeException('APP_URL must contain a valid host for IndexNow.');
        }

        if (preg_match('/^[A-Za-z0-9-]{8,128}$/', $key) !== 1) {
            throw new RuntimeException('INDEXNOW_KEY must contain 8-128 letters, digits, or hyphens.');
        }

        $urlCollection = Collection::make($urls)
            ->map(fn (string $url): string => trim($url))
            ->filter()
            ->unique()
            ->values();

        foreach ($urlCollection as $url) {
            if (parse_url($url, PHP_URL_HOST) !== $host) {
                throw new InvalidArgumentException("IndexNow URL must belong to {$host}: {$url}");
            }
        }

        foreach ($urlCollection->chunk(self::MAX_URLS_PER_REQUEST) as $urlChunk) {
            Http::acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->post((string) config('services.indexnow.endpoint'), [
                    'host' => $host,
                    'key' => $key,
                    'keyLocation' => rtrim($appUrl, '/').'/indexnow-key.txt',
                    'urlList' => $urlChunk->values()->all(),
                ])
                ->throw();
        }

        return $urlCollection->count();
    }
}
