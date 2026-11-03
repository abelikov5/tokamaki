<?php

namespace Tests\Feature;

use App\Jobs\ImportPostersJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PosterImportApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.catalog_api.token' => 'catalog-test-token']);
    }

    public function test_import_requires_token(): void
    {
        $this->postJson('/api/v1/admin/poster-imports')->assertUnauthorized();
    }

    public function test_import_defaults_to_user_one_and_dispatches_job(): void
    {
        Queue::fake();

        $response = $this->withToken('catalog-test-token')
            ->postJson('/api/v1/admin/poster-imports', ['nm_ids' => ['10001', '10002']])
            ->assertAccepted()
            ->assertJsonPath('data.user_id', 1)
            ->assertJsonPath('data.status', 'pending');

        $id = $response->json('data.id');
        $this->assertDatabaseHas('poster_imports', ['id' => $id, 'source_user_id' => 1]);
        Queue::assertPushed(ImportPostersJob::class, fn (ImportPostersJob $job): bool => $job->posterImportId === $id);
    }

    public function test_import_accepts_explicit_user_id(): void
    {
        Queue::fake();

        $this->withToken('catalog-test-token')
            ->postJson('/api/v1/admin/poster-imports', ['user_id' => 7])
            ->assertAccepted()
            ->assertJsonPath('data.user_id', 7);
    }
}
