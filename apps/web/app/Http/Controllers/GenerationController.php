<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\User;
use App\Models\Visitor;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class GenerationController extends Controller
{
    private const DEFAULT_GENERATOR_SETTINGS = [
        'colors' => 22,
        'auto_colors' => false,
        'min_region_size' => 100,
        'smoothing' => 2,
        'min_region_width' => 4,
        'contour_smoothing' => 2,
        'complexity' => 'balanced',
        'line_separation' => 2,
        'label_density' => 1,
        'focus_regions' => [],
    ];

    public function index(): View
    {
        return view('generations.index', [
            'resumeEndpoint' => null,
            'canCustomize' => false,
        ]);
    }

    public function editor(Request $request): View|RedirectResponse
    {
        if (! $this->canCustomize($request)) {
            return redirect('/admin/login');
        }

        return view('generations.index', [
            'resumeEndpoint' => null,
            'canCustomize' => true,
        ]);
    }

    public function resume(Request $request, Generation $generation): View
    {
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);
        $this->recordActivity($request, $generation);

        return view('generations.index', [
            'resumeEndpoint' => route('generations.show', $generation),
            'canCustomize' => false,
        ]);
    }

    public function editorResume(Request $request, Generation $generation): View|RedirectResponse
    {
        if (! $this->canCustomize($request)) {
            return redirect('/admin/login');
        }
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);
        $this->recordActivity($request, $generation);

        return view('generations.index', [
            'resumeEndpoint' => route('generations.show', $generation),
            'canCustomize' => true,
        ]);
    }

    public function show(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);
        $this->recordActivity($request, $generation);

        return response()->json($this->decoratePayload($generation, [
            'result_id' => $generation->result_id,
            'palette' => $generation->palette,
            'diagnostics' => $generation->diagnostics,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $canCustomize = $this->canCustomize($request);
        $rules = [
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:20480'],
            'focus_regions' => ['nullable', 'json'],
            'complexity' => ['required', Rule::in(['detailed', 'balanced', 'simple', 'minimal'])],
        ];
        if ($canCustomize) {
            $rules = array_merge($rules, [
                'auto_colors' => ['nullable', 'boolean'],
                'colors' => ['nullable', 'required_without:auto_colors', 'integer', 'between:4,32'],
                'min_region_size' => ['required', 'integer', 'between:10,5000'],
                'smoothing' => ['required', 'numeric', 'between:0,5'],
                'min_region_width' => ['required', 'numeric', 'between:0,20'],
                'contour_smoothing' => ['required', 'numeric', 'between:0.25,8'],
                'line_separation' => ['required', 'numeric', 'between:0,10'],
                'label_density' => ['required', 'numeric', 'between:0.25,3'],
            ]);
        } else {
            $rules['colors'] = ['required', 'integer', 'between:4,32'];
        }
        $validated = $request->validate($rules);

        $image = $request->file('image');
        $focusRegions = $canCustomize
            ? json_decode($validated['focus_regions'] ?? '[]', true)
            : [];
        $focusValidator = Validator::make(['focus_regions' => $focusRegions], [
            'focus_regions' => ['array', 'max:8'],
            'focus_regions.*' => ['array:x,y,width,height'],
            'focus_regions.*.x' => ['required', 'numeric', 'between:0,1'],
            'focus_regions.*.y' => ['required', 'numeric', 'between:0,1'],
            'focus_regions.*.width' => ['required', 'numeric', 'between:0.015,1'],
            'focus_regions.*.height' => ['required', 'numeric', 'between:0.015,1'],
        ]);
        $focusValidator->after(function ($validator) use ($focusRegions): void {
            if (! is_array($focusRegions)) {
                return;
            }
            foreach ($focusRegions as $index => $region) {
                if (! is_array($region) || ! isset($region['x'], $region['y'], $region['width'], $region['height'])) {
                    continue;
                }
                if ($region['x'] + $region['width'] > 1 || $region['y'] + $region['height'] > 1) {
                    $validator->errors()->add("focus_regions.{$index}", 'Важная область должна находиться внутри изображения.');
                }
            }
        });
        $focusRegions = $focusValidator->validate()['focus_regions'];
        $allowedSettings = $canCustomize
            ? collect($validated)->except(['image', 'focus_regions'])->all()
            : collect($validated)->only(['colors', 'complexity'])->all();
        $settings = array_merge(self::DEFAULT_GENERATOR_SETTINGS, $allowedSettings);
        $settings['auto_colors'] = $canCustomize && $request->boolean('auto_colors');
        $settings['colors'] ??= 22;
        $settings['focus_regions'] = $focusRegions;
        $generatorSettings = $settings;
        $generatorSettings['focus_regions'] = json_encode($focusRegions, JSON_THROW_ON_ERROR);
        $visitor = $this->resolveVisitor($request);
        $generation = Generation::create([
            'status' => 'processing',
            'visitor_id' => $visitor->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'original_name' => $image->getClientOriginalName(),
            'settings' => $settings,
            'last_activity_at' => now(),
        ]);

        try {
            $response = Http::timeout(config('services.generator.timeout'))
                ->attach(
                    'file',
                    file_get_contents($image->getRealPath()),
                    $image->getClientOriginalName(),
                    ['Content-Type' => $image->getMimeType()]
                )
                ->post(config('services.generator.url').'/api/v1/process', $generatorSettings);

            if ($response->failed()) {
                $message = $response->json('detail') ?? 'Генератор не смог обработать изображение.';
                $generation->update(['status' => 'failed', 'error' => $message]);

                return response()->json(['message' => $message], $response->status());
            }

            $payload = $response->json();
            $generation->update([
                'status' => 'completed',
                'result_id' => $payload['result_id'],
                'palette' => $payload['palette'],
                'diagnostics' => $payload['diagnostics'],
                'completed_at' => now(),
            ]);

            return response()->json($this->decoratePayload($generation, $payload));
        } catch (ConnectionException) {
            $message = 'Python-генератор недоступен. Запустите его на порту 8001.';
            $generation->update(['status' => 'failed', 'error' => $message]);

            return response()->json(['message' => $message], 503);
        } catch (Throwable $exception) {
            report($exception);
            $generation->update(['status' => 'failed', 'error' => $exception->getMessage()]);

            return response()->json(['message' => 'Внутренняя ошибка обработки изображения.'], 500);
        }
    }

    public function edit(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($this->canCustomize($request), 403);
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);
        $this->recordActivity($request, $generation);
        $supportedOperations = [
            'recolor_region', 'unify_background', 'paint_stroke', 'erase_stroke', 'smooth_stroke',
            'magic_stroke', 'add_label',
        ];
        $validated = $request->validate([
            'operation' => ['required_without:operations', Rule::in($supportedOperations)],
            'operations' => ['required_without:operation', 'array', 'min:1', 'max:100'],
            'operations.*.operation' => ['required', Rule::in([
                'recolor_region', 'paint_stroke', 'erase_stroke', 'smooth_stroke', 'magic_stroke',
                'add_label',
            ])],
            'operations.*.target_number' => ['nullable', 'integer', 'min:1'],
            'operations.*.erase_mode' => ['nullable', Rule::in(['color', 'local'])],
            'operations.*.x' => ['nullable', 'integer', 'min:0'],
            'operations.*.y' => ['nullable', 'integer', 'min:0'],
            'operations.*.points' => ['nullable', 'array', 'min:1', 'max:2000'],
            'operations.*.points.*' => ['array', 'size:2'],
            'operations.*.points.*.0' => ['numeric', 'min:0'],
            'operations.*.points.*.1' => ['numeric', 'min:0'],
            'operations.*.radius' => ['nullable', 'integer', 'between:1,80'],
            'x' => ['nullable', 'integer', 'min:0'],
            'y' => ['nullable', 'integer', 'min:0'],
            'target_number' => [
                Rule::requiredIf(fn () => in_array($request->input('operation'), [
                    'recolor_region', 'unify_background', 'paint_stroke',
                ], true) || (
                    $request->input('operation') === 'erase_stroke'
                    && $request->input('erase_mode', 'color') === 'color'
                )),
                'nullable', 'integer', 'min:1',
            ],
            'erase_mode' => ['nullable', Rule::in(['color', 'local'])],
            'tolerance' => ['nullable', 'numeric', 'between:0,100'],
            'points' => [
                Rule::requiredIf(fn () => str_ends_with((string) $request->input('operation'), '_stroke')),
                'nullable', 'array', 'min:1', 'max:2000',
            ],
            'points.*' => ['array', 'size:2'],
            'points.*.0' => ['numeric', 'min:0'],
            'points.*.1' => ['numeric', 'min:0'],
            'radius' => ['nullable', 'integer', 'between:1,80'],
        ]);

        try {
            $response = Http::timeout(config('services.generator.timeout'))
                ->post(
                    config('services.generator.url')."/api/v1/results/{$generation->result_id}/edit",
                    $validated,
                );
            if ($response->failed()) {
                return response()->json([
                    'message' => $response->json('detail') ?? 'Не удалось применить правку.',
                ], $response->status());
            }

            $payload = $response->json();
            $generation->update([
                'previous_result_id' => $generation->result_id,
                'previous_palette' => $generation->palette,
                'previous_diagnostics' => $generation->diagnostics,
                'result_id' => $payload['result_id'],
                'palette' => $payload['palette'],
                'diagnostics' => $payload['diagnostics'],
            ]);
            $generation->increment('edits_count');

            return response()->json($this->decoratePayload($generation, $payload));
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Python-генератор недоступен. Запустите его на порту 8001.',
            ], 503);
        }
    }

    public function undo(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($this->canCustomize($request), 403);
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);
        $this->recordActivity($request, $generation);

        if (! $generation->previous_result_id) {
            return response()->json([
                'message' => 'Нет сохранённого изменения для отмены.',
            ], 409);
        }

        $generation->update([
            'result_id' => $generation->previous_result_id,
            'palette' => $generation->previous_palette,
            'diagnostics' => $generation->previous_diagnostics,
            'previous_result_id' => null,
            'previous_palette' => null,
            'previous_diagnostics' => null,
        ]);

        return response()->json($this->decoratePayload($generation, [
            'result_id' => $generation->result_id,
            'palette' => $generation->palette,
            'diagnostics' => $generation->diagnostics,
        ]));
    }

    public function download(Request $request, Generation $generation, string $filename): Response
    {
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);
        $this->recordActivity($request, $generation);

        $response = Http::timeout(config('services.generator.timeout'))
            ->get(config('services.generator.url')."/api/v1/results/{$generation->result_id}/{$filename}");

        abort_if($response->failed(), $response->status());

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function adminPreview(Request $request, Generation $generation): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->canAccessPanel(Filament::getPanel('admin')),
            403,
        );
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);

        $response = Http::timeout(config('services.generator.timeout'))
            ->get(config('services.generator.url')."/api/v1/results/{$generation->result_id}/preview.png");

        abort_if($response->failed(), $response->status());

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'image/png'),
            'Content-Disposition' => 'inline; filename="preview.png"',
            'Cache-Control' => 'private, max-age=86400',
            'Vary' => 'Cookie',
        ]);
    }

    private function decoratePayload(Generation $generation, array $payload): array
    {
        $payload['generation_id'] = $generation->id;
        $payload['original_name'] = $generation->original_name;
        $payload['updated_at'] = $generation->updated_at?->toIso8601String();
        $resumeRoute = request()->header('X-Generator-Mode') === 'editor' && $this->canCustomize(request())
            ? 'generations.editor.resume'
            : 'generations.resume';
        $payload['resume_url'] = route($resumeRoute, $generation);
        $payload['edit_url'] = route('generations.edit', $generation);
        $payload['undo_url'] = route('generations.undo', $generation);
        $payload['order_url'] = route('orders.store', $generation);
        $payload['can_undo'] = (bool) $generation->previous_result_id;
        $downloads = collect([
            'original' => 'original.png',
            'preview' => 'preview.png',
            'svg' => 'scheme.svg',
            'pdf' => 'scheme.pdf',
            'unlabeled' => 'unlabeled.png',
            'palette' => 'palette.json',
            'diagnostics' => 'diagnostics.json',
        ])->map(fn (string $filename) => route('generations.download', [$generation, $filename])
            .'?v='.rawurlencode((string) $payload['result_id']));
        $payload['downloads'] = $downloads;
        $payload['preview_data_url'] = $downloads['preview'];
        $payload['svg_data_url'] = $downloads['svg'];
        $payload['unlabeled_data_url'] = $downloads['unlabeled'];

        return $payload;
    }

    private function resolveVisitor(Request $request): Visitor
    {
        $browserId = $request->cookie('tokamaki_visitor');

        if (! is_string($browserId) || ! Str::isUuid($browserId)) {
            $browserId = (string) Str::uuid();
        }

        $visitor = Visitor::firstOrCreate(
            ['browser_id' => $browserId],
            [
                'first_ip_address' => $request->ip(),
                'first_user_agent' => $request->userAgent(),
            ],
        );

        $visitor->update([
            'last_ip_address' => $request->ip(),
            'last_user_agent' => $request->userAgent(),
            'last_seen_at' => now(),
        ]);

        Cookie::queue(
            'tokamaki_visitor',
            $browserId,
            60 * 24 * 365 * 5,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );

        return $visitor;
    }

    private function canCustomize(Request $request): bool
    {
        $user = $request->user();

        return $user instanceof User
            && $user->canAccessPanel(Filament::getPanel('admin'));
    }

    private function recordActivity(Request $request, Generation $generation): void
    {
        $generation->update(['last_activity_at' => now()]);
        $visitor = $generation->visitor;

        if (! $visitor || $visitor->browser_id !== $request->cookie('tokamaki_visitor')) {
            return;
        }

        $visitor->update([
            'last_ip_address' => $request->ip(),
            'last_user_agent' => $request->userAgent(),
            'last_seen_at' => now(),
        ]);
    }
}
