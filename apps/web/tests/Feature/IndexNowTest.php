<?php

namespace Tests\Feature;

use App\Jobs\SubmitUrlsToIndexNowJob;
use App\Models\IndexNowSubmission;
use App\Models\Poster;
use App\Models\PosterCategory;
use App\Models\PosterImport;
use App\Services\IndexNowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Tests\TestCase;

class IndexNowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'app.url' => 'https://tokamaki.test',
            'services.indexnow.endpoint' => 'https://yandex.test/indexnow',
            'services.indexnow.key' => 'tokamaki-test-key-12345678',
        ]);
        URL::useOrigin('https://tokamaki.test');
        URL::forceScheme('https');
    }

    protected function tearDown(): void
    {
        URL::useOrigin(null);
        URL::forceScheme(null);

        parent::tearDown();
    }

    public function test_key_endpoint_exposes_configured_verification_key(): void
    {
        $this->get('/indexnow-key.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText('tokamaki-test-key-12345678');
    }

    public function test_service_submits_unique_site_urls_to_yandex(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.test/indexnow' => Http::response([], 200),
        ]);

        $submittedCount = app(IndexNowService::class)->submit([
            'https://tokamaki.test/',
            'https://tokamaki.test/kartiny-na-holste',
            'https://tokamaki.test/kartiny-na-holste',
        ]);

        $this->assertSame(2, $submittedCount);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://yandex.test/indexnow'
                && $request['host'] === 'tokamaki.test'
                && $request['key'] === 'tokamaki-test-key-12345678'
                && $request['keyLocation'] === 'https://tokamaki.test/indexnow-key.txt'
                && $request['urlList'] === [
                    'https://tokamaki.test/',
                    'https://tokamaki.test/kartiny-na-holste',
                ];
        });
    }

    public function test_service_splits_more_than_ten_thousand_urls_into_batches(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.test/indexnow' => Http::response([], 200),
        ]);
        $urls = collect(range(1, 10_001))
            ->map(fn (int $number): string => "https://tokamaki.test/page-{$number}");

        $submittedCount = app(IndexNowService::class)->submit($urls);

        $this->assertSame(10_001, $submittedCount);
        Http::assertSentCount(2);
        $requests = Http::recorded();
        $this->assertCount(10_000, $requests[0][0]['urlList']);
        $this->assertCount(1, $requests[1][0]['urlList']);
    }

    public function test_command_submits_every_public_sitemap_page(): void
    {
        $category = PosterCategory::create([
            'name' => 'Природа',
            'slug' => 'priroda',
            'is_active' => true,
        ]);
        $poster = Poster::factory()->published()->create();
        $poster->categories()->attach($category);
        Poster::factory()->create(['status' => 'draft']);
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.test/indexnow' => Http::response([], 202),
        ]);

        $this->artisan('indexnow:submit-all')
            ->expectsOutput('Отправлено URL в IndexNow: 5')
            ->assertSuccessful();

        Http::assertSentCount(1);
        $request = Http::recorded()->first()[0];
        $this->assertSame([
            'https://tokamaki.test',
            'https://tokamaki.test/kartiny-na-holste',
            'https://tokamaki.test/kartiny-na-holste/po-foto',
            route('posters.show', $category),
            route('posters.show', $poster),
        ], $request['urlList']);
        $this->assertDatabaseHas('index_now_submissions', [
            'poster_import_id' => null,
            'source' => 'full',
            'status' => 'completed',
            'urls_count' => 5,
        ]);
    }

    public function test_queued_submission_records_success_for_its_import(): void
    {
        $posterImport = PosterImport::create(['source_user_id' => 1, 'status' => 'completed']);
        Http::preventStrayRequests();
        Http::fake([
            'https://yandex.test/indexnow' => Http::response([], 200),
        ]);

        $job = new SubmitUrlsToIndexNowJob([
            'https://tokamaki.test/kartiny-na-holste/example',
            'https://tokamaki.test/kartiny-na-holste/category',
        ], $posterImport->id);
        $job->handle(app(IndexNowService::class));

        $this->assertDatabaseHas('index_now_submissions', [
            'poster_import_id' => $posterImport->id,
            'source' => 'import',
            'status' => 'completed',
            'urls_count' => 2,
        ]);
        $this->assertNotNull(IndexNowSubmission::firstOrFail()->submitted_at);
    }

    public function test_terminal_queued_submission_failure_is_recorded_for_its_import(): void
    {
        $posterImport = PosterImport::create(['source_user_id' => 1, 'status' => 'completed']);
        $job = new SubmitUrlsToIndexNowJob(['https://tokamaki.test/example'], $posterImport->id);

        $job->failed(new RuntimeException('Yandex unavailable'));

        $this->assertDatabaseHas('index_now_submissions', [
            'poster_import_id' => $posterImport->id,
            'source' => 'import',
            'status' => 'failed',
            'urls_count' => 0,
            'error' => 'Yandex unavailable',
        ]);
    }
}
