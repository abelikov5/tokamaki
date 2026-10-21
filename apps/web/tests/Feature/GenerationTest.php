<?php

namespace Tests\Feature;

use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_is_available(): void
    {
        $this->get('/')->assertOk()->assertSee('Картина по номерам');
    }

    public function test_image_is_forwarded_to_generator_and_generation_is_saved(): void
    {
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);

        $response = $this->postJson('/generations', $this->validForm());

        $response->assertOk()
            ->assertJsonPath('result_id', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('diagnostics.segments', 42)
            ->assertJsonStructure(['downloads' => ['svg', 'pdf', 'palette', 'diagnostics']]);

        $generation = Generation::firstOrFail();
        $this->assertSame('completed', $generation->status);
        $this->assertSame(42, $generation->diagnostics['segments']);
        Http::assertSent(fn ($request) => $request->hasFile('file'));
    }

    public function test_generator_connection_error_is_reported(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->postJson('/generations', $this->validForm())
            ->assertStatus(503)
            ->assertJsonPath('message', 'Python-генератор недоступен. Запустите его на порту 8001.');

        $this->assertDatabaseHas('generations', ['status' => 'failed']);
    }

    private function validForm(): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return [
            'image' => UploadedFile::fake()->createWithContent('photo.png', $png),
            'colors' => 24,
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
            'preview_data_url' => 'data:image/png;base64,AA==',
            'svg_data_url' => 'data:image/svg+xml;base64,AA==',
            'pdf_url' => '/api/v1/results/id/scheme.pdf',
            'palette' => [['number' => 1, 'hex' => '#FFFFFF', 'rgb' => [255, 255, 255], 'pixel_count' => 1, 'percentage' => 100]],
            'diagnostics' => [
                'requested_colors' => 24,
                'actual_colors' => 1,
                'segments' => 42,
                'small_regions_before_merge' => 0,
                'small_regions_after_merge' => 0,
                'thin_regions_before_merge' => 0,
                'thin_regions_after_merge' => 0,
                'smoothing' => 2,
                'min_region_width' => 4,
                'contour_smoothing' => 2,
                'complexity' => 'balanced',
                'line_separation' => 2,
                'effective_min_region_size' => 100,
                'effective_min_region_width' => 4,
                'effective_contour_smoothing' => 2,
                'effective_line_separation' => 2,
                'boundary_paths' => 10,
                'label_density' => 1,
                'labels_placed' => 5,
                'processing_width' => 1,
                'processing_height' => 1,
                'processing_ms' => 100,
            ],
        ];
    }
}
