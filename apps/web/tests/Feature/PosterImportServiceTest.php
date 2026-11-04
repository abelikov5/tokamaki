<?php

namespace Tests\Feature;

use App\Jobs\GeneratePosterContentJob;
use App\Models\Poster;
use App\Models\PosterImage;
use App\Models\PosterImport;
use App\Services\PosterImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PosterImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_grouped_variants_as_published_and_mirrors_images(): void
    {
        Queue::fake();
        Storage::fake('public');
        config([
            'services.mockup_catalog.url' => 'https://mockup.test',
            'services.mockup_catalog.token' => 'source-token',
            'catalog.prices.30x40' => 1490,
            'catalog.prices.40x50' => 1990,
        ]);

        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://images.test/')) {
                return Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response([
                'data' => [
                    $this->sourcePoster('10001', 501, '40x30'),
                    $this->sourcePoster('10002', 502, '40x50'),
                    $this->sourcePoster('10003', 503, '40x50'),
                ],
                'meta' => ['next_cursor' => null],
            ]);
        });

        $posterImport = PosterImport::create(['source_user_id' => 1, 'status' => 'pending']);
        app(PosterImportService::class)->run($posterImport);

        $posterImport->refresh();
        $this->assertSame('completed', $posterImport->status);
        $this->assertSame(1, $posterImport->total_count);
        $this->assertSame(1, $posterImport->processed_count);
        $this->assertSame(1, $posterImport->created_count);
        $this->assertDatabaseCount('posters', 1);
        $this->assertDatabaseHas('posters', [
            'source_key' => 'group:77',
            'nm_id' => '10003',
            'status' => 'published',
        ]);
        $poster = Poster::firstOrFail();
        $this->assertNotNull($poster->published_at);
        $this->assertSame([
            'Природа', 'Современный', 'Гостиная', 'розовый', 'фиолетовый', 'белый',
            'бежевый', 'картина на холсте', 'интерьерная картина', 'постер на холсте',
            'картина', 'на', 'холсте', 'горы',
        ], $poster->search_keywords);
        $this->assertSame('розовый, фиолетовый, белый, бежевый', $poster->primary_color);
        $this->assertContains('розовый', $poster->search_keywords);
        $this->assertContains('фиолетовый', $poster->search_keywords);
        $this->assertStringNotContainsString('«', implode(' ', $poster->search_keywords));
        $this->assertStringNotContainsString('»', implode(' ', $poster->search_keywords));
        $this->assertDatabaseHas('posters', ['title' => 'Картина на холсте «Горы»']);
        $this->assertDatabaseHas('poster_categories', [
            'name' => 'Природа',
            'slug' => 'priroda',
            'is_active' => true,
        ]);
        $this->assertSame('Природа', $poster->posterCategory?->name);
        $this->assertSame(['Природа'], $poster->categories()->pluck('name')->all());
        $this->assertDatabaseCount('poster_variants', 2);
        $this->assertDatabaseHas('poster_variants', ['nm_id' => '10001', 'size_code' => '30x40', 'price' => 1490]);
        $this->assertDatabaseHas('poster_variants', ['nm_id' => '10003', 'size_code' => '40x50', 'price' => 1990]);
        $this->assertNotNull(PosterImage::firstOrFail()->path);
        $this->assertSame('pending', $poster->ai_generation_status);
        Queue::assertPushed(GeneratePosterContentJob::class, fn (GeneratePosterContentJob $job): bool => $job->posterId === $poster->id && $job->updateSlug);
    }

    public function test_random_import_selects_unique_designs_and_skips_existing_ones(): void
    {
        Queue::fake();
        Storage::fake('public');
        config([
            'services.mockup_catalog.url' => 'https://mockup.test',
            'services.mockup_catalog.token' => 'source-token',
        ]);

        Poster::factory()->published()->create([
            'source_user_id' => 1,
            'source_key' => 'group:1',
            'source_group_id' => 1,
        ]);

        Http::fake(function ($request) {
            if (str_starts_with($request->url(), 'https://images.test/')) {
                return Http::response('image-bytes', 200, ['Content-Type' => 'image/jpeg']);
            }

            return Http::response([
                'data' => [
                    $this->sourcePoster('10001', 501, '40x50', 1),
                    $this->sourcePoster('10002', 502, '40x50', 2),
                    $this->sourcePoster('10003', 503, '40x50', 3),
                    $this->sourcePoster('10004', 504, '40x50', 4),
                ],
                'meta' => ['next_cursor' => null],
            ]);
        });

        $posterImport = PosterImport::create([
            'source_user_id' => 1,
            'selection_mode' => 'random',
            'random_count' => 2,
            'only_new' => true,
            'status' => 'pending',
        ]);

        app(PosterImportService::class)->run($posterImport);
        $posterImport->refresh();

        $this->assertSame('completed', $posterImport->status);
        $this->assertSame(2, $posterImport->total_count);
        $this->assertSame(2, $posterImport->processed_count);
        $this->assertSame(2, $posterImport->created_count);
        $this->assertSame(1, $posterImport->skipped_count);
        $this->assertDatabaseCount('posters', 3);
        $this->assertSame(2, Poster::whereIn('source_key', ['group:2', 'group:3', 'group:4'])->count());
    }

    /** @return array<string, mixed> */
    private function sourcePoster(string $nmId, int $sourceId, string $size, int $groupId = 77): array
    {
        return [
            'source_id' => $sourceId,
            'user_id' => 1,
            'group_id' => $groupId,
            'group_name' => 'Горный рассвет',
            'nm_id' => $nmId,
            'vendor_code' => 'poster_'.$nmId,
            'title' => 'Картина постер горы',
            'description' => 'Старое описание WB, которое нельзя копировать.',
            'type' => 'poster',
            'characteristics' => [
                ['id' => 171566, 'value' => [$size.' см']],
                ['id' => 14177449, 'name' => 'Цвет', 'value' => ['розовый', 'фиолетовый', 'белый', 'бежевый']],
            ],
            'dimensions' => $size === '40x30'
                ? ['length' => 42, 'width' => 32]
                : ['length' => 52, 'width' => 42],
            'sizes' => [],
            'visual_analysis' => ['summary' => 'Спокойный горный пейзаж в утреннем свете.'],
            'photos' => [[
                'source_id' => 900,
                'role' => 'main',
                'sort_order' => 0,
                'url' => 'https://images.test/poster.jpg',
                'file_name' => 'poster.jpg',
                'mime_type' => 'image/jpeg',
            ]],
            'updated_at' => '2026-11-03T10:00:00+03:00',
        ];
    }
}
