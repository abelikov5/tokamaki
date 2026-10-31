<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_simple_generator_and_order_offer(): void
    {
        $this->get('/kartina-po-nomeram-po-foto')
            ->assertOk()
            ->assertSee('Картина по номерам')
            ->assertSee('data-can-customize="0"', false)
            ->assertSee('Создать бесплатный макет')
            ->assertSee('id="upload-comparison"', false)
            ->assertDontSee('comparison-label', false)
            ->assertSee('id="guest-result-actions"', false)
            ->assertSee('role="progressbar"', false)
            ->assertSee('Скачать PDF бесплатно')
            ->assertSee('Заказать полный набор за 1 690 ₽')
            ->assertDontSee('Сгенерировать заново')
            ->assertSee('Премиальный холст 40 × 50 см')
            ->assertSee('Акриловые краски и кисти')
            ->assertSee('Авито или СДЭК')
            ->assertSee('1 690 ₽')
            ->assertSee('Обработка фотографии дизайнером')
            ->assertSee('id="order-dialog"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="city"', false)
            ->assertSee('name="street"', false)
            ->assertSee('class="order-street-field"', false)
            ->assertSee('placeholder="ул. Соборная"', false)
            ->assertDontSee('placeholder="ул. Примерная"', false)
            ->assertSee('id="order-dialog-designer-processing"', false)
            ->assertSee('Обработка дизайнером')
            ->assertSee('+500 ₽')
            ->assertDontSee('name="address"', false)
            ->assertSee('Бесплатная доставка до ПВЗ Авито или СДЭК')
            ->assertDontSee('mailto:abelikov@inbox.ru', false)
            ->assertSee('canonical', false)
            ->assertSee('mc.yandex.ru/metrika/tag.js?id=113757543', false)
            ->assertSee('mc.yandex.ru/watch/113757543', false)
            ->assertDontSee('name="auto_colors" type="checkbox"', false)
            ->assertSee('Формат 40 × 50 см')
            ->assertSee('id="colors" name="colors" type="range" min="4" max="32" step="1" value="22"', false)
            ->assertSee('Количество цветов')
            ->assertSee('Сложность')
            ->assertSee('name="complexity"', false)
            ->assertSee('<option value="balanced" selected>Сбалансированная</option>', false)
            ->assertSee('id="change-photo"', false)
            ->assertSee('Загрузить другую фотку');
    }

    public function test_authenticated_user_sees_all_generator_and_editor_tools(): void
    {
        $this->signInAsEditor();

        $this->get('/edit/kartina-po-nomeram-po-foto')
            ->assertOk()
            ->assertSee('data-can-customize="1"', false)
            ->assertSee('data-tool="select"', false)
            ->assertSee('data-tool="eyedropper"', false)
            ->assertSee('data-tool="magic"', false)
            ->assertSee('data-tool="label"', false)
            ->assertSee('data-tool="unlabeled"', false)
            ->assertSee('data-eraser-mode="local"', false)
            ->assertSee('id="colors" name="colors" type="range" min="4" max="32"', false)
            ->assertSee('name="auto_colors" type="checkbox" value="1" checked', false)
            ->assertSee('data-fit-mode="crop"', false)
            ->assertSee('data-fit-mode="stretch"', false)
            ->assertSee('id="focus-canvas"', false)
            ->assertSee('id="focus-mode"', false)
            ->assertSee('id="apply-edits"', false)
            ->assertSee('id="undo-change"', false)
            ->assertSee('id="saved-projects"', false)
            ->assertSee('id="save-project"', false);
    }

    public function test_order_price_is_rendered_from_configuration_without_breakable_spaces(): void
    {
        config()->set('orders.base_price', 2345);
        config()->set('orders.designer_price', 650);

        $this->get('/kartina-po-nomeram-po-foto')
            ->assertOk()
            ->assertSee('data-order-base-price="2345"', false)
            ->assertSee('data-order-designer-price="650"', false)
            ->assertSee('2 345 ₽')
            ->assertSee('+650 ₽')
            ->assertDontSee('1 690 ₽');
    }

    public function test_public_generator_stays_simple_for_an_authenticated_user(): void
    {
        $this->signInAsEditor();

        $this->get('/kartina-po-nomeram-po-foto')
            ->assertOk()
            ->assertSee('data-can-customize="0"', false)
            ->assertSee('Создать бесплатный макет')
            ->assertSee('id="upload-comparison"', false)
            ->assertSee('id="guest-result-actions"', false)
            ->assertDontSee('name="auto_colors" type="checkbox"', false);
    }

    public function test_authenticated_public_generation_keeps_a_public_resume_url(): void
    {
        $this->signInAsEditor();
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);

        $response = $this->postJson('/generations', $this->validForm());

        $generation = Generation::firstOrFail();
        $response->assertOk()
            ->assertJsonPath('resume_url', route('generations.resume', $generation));
    }

    public function test_editor_generation_keeps_an_editor_resume_url(): void
    {
        $this->signInAsEditor();
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);

        $response = $this->withHeader('X-Generator-Mode', 'editor')
            ->postJson('/generations', $this->validForm());

        $generation = Generation::firstOrFail();
        $response->assertOk()
            ->assertJsonPath('resume_url', route('generations.editor.resume', $generation));
    }

    public function test_guest_is_redirected_to_admin_login_from_editor_page(): void
    {
        $this->get('/edit/kartina-po-nomeram-po-foto')
            ->assertRedirect('/admin/login');
    }

    public function test_image_is_forwarded_to_generator_and_generation_is_saved(): void
    {
        $this->signInAsEditor();
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);

        $response = $this->postJson('/generations', $this->validForm());

        $response->assertOk()
            ->assertJsonPath('result_id', '11111111-1111-4111-8111-111111111111')
            ->assertJsonPath('diagnostics.segments', 42)
            ->assertJsonPath('original_name', 'photo.png')
            ->assertJsonStructure(['resume_url', 'edit_url'])
            ->assertJsonStructure(['unlabeled_data_url', 'downloads' => ['original', 'svg', 'pdf', 'unlabeled', 'palette', 'diagnostics']]);
        $this->assertStringContainsString(
            'v=11111111-1111-4111-8111-111111111111',
            $response->json('svg_data_url'),
        );

        $generation = Generation::firstOrFail();
        $this->assertSame('completed', $generation->status);
        $this->assertSame(42, $generation->diagnostics['segments']);
        $this->assertNotNull($generation->resume_token);
        $this->assertStringContainsString($generation->resume_token, $response->json('resume_url'));
        Http::assertSent(fn ($request) => $request->hasFile('file')
            && str_contains($request->body(), 'focus_regions')
            && str_contains($request->body(), '"width":0.2'));
        $this->assertSame(22, $generation->settings['colors']);
        $this->assertFalse($generation->settings['auto_colors']);
        $this->assertSame(0.35, $generation->settings['focus_regions'][0]['x']);
    }

    public function test_auto_color_mode_accepts_a_missing_manual_value_and_is_forwarded(): void
    {
        $this->signInAsEditor();
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);
        $form = $this->validForm();
        unset($form['colors']);
        $form['auto_colors'] = true;

        $this->postJson('/generations', $form)->assertOk();

        $generation = Generation::firstOrFail();
        $this->assertSame(22, $generation->settings['colors']);
        $this->assertTrue($generation->settings['auto_colors']);
    }

    public function test_guest_generation_uses_selected_colors_and_complexity_but_ignores_advanced_settings(): void
    {
        Http::fake([
            '*/api/v1/process' => Http::response($this->generatorPayload()),
        ]);
        $form = $this->validForm();
        $form['colors'] = 32;
        $form['auto_colors'] = true;
        $form['complexity'] = 'detailed';

        $this->postJson('/generations', $form)->assertOk();

        $generation = Generation::firstOrFail();
        $this->assertSame(32, $generation->settings['colors']);
        $this->assertFalse($generation->settings['auto_colors']);
        $this->assertSame('detailed', $generation->settings['complexity']);
        $this->assertSame([], $generation->settings['focus_regions']);
        Http::assertSentCount(1);
    }

    public function test_authenticated_generation_rejects_more_than_32_colors(): void
    {
        $this->signInAsEditor();
        $form = $this->validForm();
        $form['colors'] = 33;

        $this->postJson('/generations', $form)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('colors');

        $this->assertDatabaseEmpty('generations');
    }

    public function test_generator_connection_error_is_reported(): void
    {
        Http::fake(fn () => throw new ConnectionException('offline'));

        $this->postJson('/generations', $this->validForm())
            ->assertStatus(503)
            ->assertJsonPath('message', 'Python-генератор недоступен. Запустите его на порту 8001.');

        $this->assertDatabaseHas('generations', ['status' => 'failed']);
    }

    public function test_focus_region_outside_the_image_is_rejected(): void
    {
        $this->signInAsEditor();
        $form = $this->validForm();
        $form['focus_regions'] = json_encode([
            ['x' => 0.9, 'y' => 0.2, 'width' => 0.2, 'height' => 0.2],
        ]);

        $this->postJson('/generations', $form)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('focus_regions.0');
    }

    public function test_completed_generation_can_apply_queued_editor_strokes(): void
    {
        $this->signInAsEditor();
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
                    'operation' => 'recolor_region',
                    'target_number' => 1,
                    'x' => 25,
                    'y' => 35,
                ],
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
            'edits_count' => 1,
        ]);
        Http::assertSent(fn ($request) => count($request->data()['operations'] ?? []) === 6
            && $request->data()['operations'][0]['operation'] === 'recolor_region'
            && $request->data()['operations'][0]['target_number'] === 1
            && $request->data()['operations'][3]['erase_mode'] === 'local'
            && ! isset($request->data()['operations'][3]['target_number'])
            && $request->data()['operations'][4]['operation'] === 'magic_stroke'
            && $request->data()['operations'][5]['operation'] === 'add_label'
            && $request->data()['operations'][5]['x'] === 90);

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

    public function test_guest_cannot_use_editor_endpoints(): void
    {
        $generation = Generation::create([
            'status' => 'completed',
            'original_name' => 'photo.png',
            'settings' => ['colors' => 24],
            'result_id' => '11111111-1111-4111-8111-111111111111',
            'palette' => [],
            'diagnostics' => [],
        ]);

        $this->postJson("/generations/{$generation->resume_token}/edit", [
            'operation' => 'recolor_region',
            'target_number' => 1,
            'x' => 1,
            'y' => 1,
        ])->assertForbidden();
        $this->postJson("/generations/{$generation->resume_token}/undo")
            ->assertForbidden();
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

    public function test_original_image_is_available_for_the_comparison_slider(): void
    {
        $generation = Generation::create([
            'status' => 'completed',
            'original_name' => 'saved-photo.png',
            'settings' => ['colors' => 24],
            'result_id' => '11111111-1111-4111-8111-111111111111',
            'palette' => [],
            'diagnostics' => [],
        ]);
        Http::fake([
            '*/api/v1/results/*/original.png' => Http::response('png-data', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $response = $this->get(route('generations.download', [$generation, 'original.png']));

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertSee('png-data');
        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/original.png'));
    }

    private function validForm(): array
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return [
            'image' => UploadedFile::fake()->createWithContent('photo.png', $png),
            'colors' => 22,
            'auto_colors' => false,
            'min_region_size' => 100,
            'smoothing' => 2,
            'min_region_width' => 4,
            'contour_smoothing' => 2,
            'complexity' => 'balanced',
            'line_separation' => 2,
            'label_density' => 1,
            'focus_regions' => json_encode([
                ['x' => 0.35, 'y' => 0.3, 'width' => 0.12, 'height' => 0.08],
                ['x' => 0.4, 'y' => 0.45, 'width' => 0.2, 'height' => 0.1],
            ]),
        ];
    }

    private function signInAsEditor(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $this->actingAs(User::factory()->create(['email' => 'admin@example.com']));
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
