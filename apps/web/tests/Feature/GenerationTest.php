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

    public function test_generator_page_is_available(): void
    {
        $this->get('/kartina-po-nomeram-po-foto')
            ->assertOk()
            ->assertSee('Картина по номерам')
            ->assertSee('canonical', false)
            ->assertSee('mc.yandex.ru/metrika/tag.js?id=113757543', false)
            ->assertSee('mc.yandex.ru/watch/113757543', false)
            ->assertSee('data-tool="eyedropper"', false)
            ->assertSee('data-tool="magic"', false)
            ->assertSee('data-tool="label"', false)
            ->assertSee('data-tool="unlabeled"', false)
            ->assertSee('data-eraser-mode="local"', false)
            ->assertSee('id="colors"', false)
            ->assertSee('id="auto-colors"', false)
            ->assertSee('name="auto_colors" type="checkbox" value="1" checked', false)
            ->assertSee('Формат 40 × 50″')
            ->assertSee('data-fit-mode="crop"', false)
            ->assertSee('data-fit-mode="stretch"', false)
            ->assertSee('id="apply-edits"', false)
            ->assertSee('id="undo-change"', false)
            ->assertSee('id="saved-projects"', false)
            ->assertSee('id="save-project"', false);
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
            ->assertJsonPath('original_name', 'photo.png')
            ->assertJsonStructure(['resume_url', 'edit_url'])
            ->assertJsonStructure(['unlabeled_data_url', 'downloads' => ['svg', 'pdf', 'unlabeled', 'palette', 'diagnostics']]);
        $this->assertStringContainsString(
            'v=11111111-1111-4111-8111-111111111111',
            $response->json('svg_data_url'),
        );

        $generation = Generation::firstOrFail();
        $this->assertSame('completed', $generation->status);
        $this->assertSame(42, $generation->diagnostics['segments']);
        $this->assertNotNull($generation->resume_token);
        $this->assertStringContainsString($generation->resume_token, $response->json('resume_url'));
        Http::assertSent(fn ($request) => $request->hasFile('file'));
        $this->assertSame(24, $generation->settings['colors']);
        $this->assertFalse($generation->settings['auto_colors']);
    }

    public function test_auto_color_mode_accepts_a_missing_manual_value_and_is_forwarded(): void
    {
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);
        $form = $this->validForm();
        unset($form['colors']);
        $form['auto_colors'] = true;

        $this->postJson('/generations', $form)->assertOk();

        $generation = Generation::firstOrFail();
        $this->assertSame(24, $generation->settings['colors']);
        $this->assertTrue($generation->settings['auto_colors']);
    }

    public function test_generator_connection_error_is_reported(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->postJson('/generations', $this->validForm())
            ->assertStatus(503)
            ->assertJsonPath('message', 'Python-генератор недоступен. Запустите его на порту 8001.');

        $this->assertDatabaseHas('generations', ['status' => 'failed']);
    }

    public function test_completed_generation_can_apply_queued_editor_strokes(): void
    {
        $generation = Generation::create([
            'status' => 'completed',
            'original_name' => 'photo.png',
            'settings' => ['colors' => 24],
            'result_id' => '11111111-1111-4111-8111-111111111111',
            'palette' => [],
            'diagnostics' => [],
        ]);
        $payload = $this->generatorPayload();
        $payload['result_id'] = '22222222-2222-4222-8222-222222222222';
        $payload['diagnostics']['segments'] = 7;
        Http::fake(['*/api/v1/results/*/edit' => Http::response($payload)]);

        $response = $this->postJson("/generations/{$generation->resume_token}/edit", [
            'operations' => [
                [
                    'operation' => 'smooth_stroke',
                    'points' => [[10, 20], [20, 25], [30, 27]],
                    'radius' => 8,
                ],
                [
                    'operation' => 'erase_stroke',
                    'erase_mode' => 'color',
                    'target_number' => 1,
                    'points' => [[40, 50], [44, 54]],
                    'radius' => 6,
                ],
                [
                    'operation' => 'erase_stroke',
                    'erase_mode' => 'local',
                    'points' => [[60, 70], [64, 74]],
                    'radius' => 5,
                ],
                [
                    'operation' => 'magic_stroke',
                    'points' => [[80, 90], [84, 94]],
                    'radius' => 10,
                ],
                [
                    'operation' => 'add_label',
                    'x' => 90,
                    'y' => 100,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('result_id', '22222222-2222-4222-8222-222222222222')
            ->assertJsonPath('diagnostics.segments', 7)
            ->assertJsonPath('can_undo', true)
            ->assertJsonStructure(['edit_url', 'undo_url', 'downloads']);
        $this->assertStringContainsString(
            'v=22222222-2222-4222-8222-222222222222',
            $response->json('svg_data_url'),
        );
        $this->assertDatabaseHas('generations', [
            'id' => $generation->id,
            'result_id' => '22222222-2222-4222-8222-222222222222',
            'previous_result_id' => '11111111-1111-4111-8111-111111111111',
        ]);
        Http::assertSent(fn ($request) => count($request->data()['operations'] ?? []) === 5
            && $request->data()['operations'][2]['erase_mode'] === 'local'
            && ! isset($request->data()['operations'][2]['target_number'])
            && $request->data()['operations'][3]['operation'] === 'magic_stroke'
            && $request->data()['operations'][4]['operation'] === 'add_label'
            && $request->data()['operations'][4]['x'] === 90);

        $undo = $this->postJson("/generations/{$generation->resume_token}/undo");

        $undo->assertOk()
            ->assertJsonPath('result_id', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('can_undo', false)
            ->assertJsonPath('diagnostics', []);
        $this->assertDatabaseHas('generations', [
            'id' => $generation->id,
            'result_id' => '11111111-1111-4111-8111-111111111111',
            'previous_result_id' => null,
        ]);
        $this->postJson("/generations/{$generation->resume_token}/undo")
            ->assertConflict()
            ->assertJsonPath('message', 'Нет сохранённого изменения для отмены.');
    }

    public function test_completed_generation_can_be_reopened_with_its_secret_link(): void
    {
        $generation = Generation::create([
            'status' => 'completed',
            'original_name' => 'saved-photo.png',
            'settings' => ['colors' => 24],
            'result_id' => '11111111-1111-4111-8111-111111111111',
            'palette' => $this->generatorPayload()['palette'],
            'diagnostics' => $this->generatorPayload()['diagnostics'],
        ]);

        $this->get("/kartina-po-nomeram-po-foto/proekt/{$generation->resume_token}")
            ->assertOk()
            ->assertSee(route('generations.show', $generation), false);

        $response = $this->getJson("/generations/{$generation->resume_token}");

        $response->assertOk()
            ->assertJsonPath('original_name', 'saved-photo.png')
            ->assertJsonPath('result_id', $generation->result_id)
            ->assertJsonPath('diagnostics.segments', 42);
        $this->assertStringContainsString($generation->resume_token, $response->json('edit_url'));
        $this->assertStringContainsString($generation->resume_token, $response->json('downloads.svg'));
        $this->getJson("/generations/{$generation->id}")->assertNotFound();
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
            'preview_data_url' => 'data:image/png;base64,AA==',
            'svg_data_url' => 'data:image/svg+xml;base64,AA==',
            'pdf_url' => '/api/v1/results/id/scheme.pdf',
            'palette' => [['number' => 1, 'hex' => '#FFFFFF', 'rgb' => [255, 255, 255], 'pixel_count' => 1, 'percentage' => 100]],
            'diagnostics' => [
                'requested_colors' => 24,
                'actual_colors' => 1,
                'color_mode' => 'manual',
                'estimated_colors' => null,
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
                'unlabeled_segments' => 3,
                'processing_width' => 1,
                'processing_height' => 1,
                'processing_ms' => 100,
            ],
        ];
    }
}
