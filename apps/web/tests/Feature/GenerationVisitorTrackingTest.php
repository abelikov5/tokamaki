<?php

namespace Tests\Feature;

use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerationVisitorTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_generation_is_linked_to_a_persistent_browser_visitor(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);
        $browserId = (string) Str::uuid();

        $response = $this
            ->withCredentials()
            ->withCookie('tokamaki_visitor', $browserId)
            ->withHeader('User-Agent', 'Tracking test browser')
            ->postJson('/generations', $this->validForm());

        $response->assertOk()->assertCookie('tokamaki_visitor', $browserId);
        $this->assertDatabaseHas('visitors', [
            'browser_id' => $browserId,
            'last_user_agent' => 'Tracking test browser',
        ]);
        $visitor = Visitor::where('browser_id', $browserId)->firstOrFail();
        $this->assertDatabaseHas('generations', [
            'visitor_id' => $visitor->id,
            'user_agent' => 'Tracking test browser',
            'edits_count' => 0,
        ]);
    }

    public function test_invalid_browser_cookie_is_replaced_with_a_new_visitor_identifier(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);

        $response = $this
            ->withCredentials()
            ->withCookie('tokamaki_visitor', 'not-a-uuid')
            ->postJson('/generations', $this->validForm());

        $response->assertOk()->assertCookie('tokamaki_visitor');
        $this->assertSame(1, Visitor::query()->count());
        $this->assertTrue(Str::isUuid(Visitor::firstOrFail()->browser_id));
    }

    private function validForm(): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return [
            'image' => UploadedFile::fake()->createWithContent('photo.png', $png),
            'colors' => 24,
            'auto_colors' => false,
            'min_region_size' => 100,
            'smoothing' => 2,
            'min_region_width' => 4,
            'contour_smoothing' => 2,
            'complexity' => 'balanced',
            'line_separation' => 2,
            'label_density' => 1,
        ];
    }

    private function generatorPayload(): array
    {
        return [
            'result_id' => '11111111-1111-4111-8111-111111111111',
            'palette' => [],
            'diagnostics' => ['segments' => 1],
        ];
    }
}
