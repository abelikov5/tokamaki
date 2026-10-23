<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Throwable;

class GenerationController extends Controller
{
    public function index()
    {
        return view('generations.index');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:20480'],
            'auto_colors' => ['nullable', 'boolean'],
            'colors' => ['nullable', 'required_without:auto_colors', 'integer', 'between:4,48'],
            'min_region_size' => ['required', 'integer', 'between:10,5000'],
            'smoothing' => ['required', 'numeric', 'between:0,5'],
            'min_region_width' => ['required', 'numeric', 'between:0,20'],
            'contour_smoothing' => ['required', 'numeric', 'between:0.25,8'],
            'complexity' => ['required', Rule::in(['detailed', 'balanced', 'simple', 'minimal'])],
            'line_separation' => ['required', 'numeric', 'between:0,10'],
            'label_density' => ['required', 'numeric', 'between:0.25,3'],
        ]);

        $image = $request->file('image');
        $settings = collect($validated)->except('image')->all();
        $settings['auto_colors'] = $request->boolean('auto_colors');
        $settings['colors'] ??= 24;
        $generation = Generation::create([
            'status' => 'processing',
            'original_name' => $image->getClientOriginalName(),
            'settings' => $settings,
        ]);

        try {
            $response = Http::timeout(config('services.generator.timeout'))
                ->attach(
                    'file',
                    file_get_contents($image->getRealPath()),
                    $image->getClientOriginalName(),
                    ['Content-Type' => $image->getMimeType()]
                )
                ->post(config('services.generator.url').'/api/v1/process', $settings);

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
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);
        $supportedOperations = [
            'recolor_region', 'unify_background', 'paint_stroke', 'erase_stroke', 'smooth_stroke',
            'magic_stroke', 'add_label',
        ];
        $validated = $request->validate([
            'operation' => ['required_without:operations', Rule::in($supportedOperations)],
            'operations' => ['required_without:operation', 'array', 'min:1', 'max:100'],
            'operations.*.operation' => ['required', Rule::in([
                'paint_stroke', 'erase_stroke', 'smooth_stroke', 'magic_stroke', 'add_label',
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
                'result_id' => $payload['result_id'],
                'palette' => $payload['palette'],
                'diagnostics' => $payload['diagnostics'],
            ]);

            return response()->json($this->decoratePayload($generation, $payload));
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Python-генератор недоступен. Запустите его на порту 8001.',
            ], 503);
        }
    }

    public function download(Generation $generation, string $filename): Response
    {
        abort_unless($generation->status === 'completed' && $generation->result_id, 404);

        $response = Http::timeout(config('services.generator.timeout'))
            ->get(config('services.generator.url')."/api/v1/results/{$generation->result_id}/{$filename}");

        abort_if($response->failed(), $response->status());

        return response($response->body(), 200, [
            'Content-Type' => $response->header('Content-Type', 'application/octet-stream'),
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function decoratePayload(Generation $generation, array $payload): array
    {
        $payload['generation_id'] = $generation->id;
        $payload['edit_url'] = route('generations.edit', $generation);
        $payload['downloads'] = collect([
            'preview' => 'preview.png',
            'svg' => 'scheme.svg',
            'pdf' => 'scheme.pdf',
            'palette' => 'palette.json',
            'diagnostics' => 'diagnostics.json',
        ])->map(fn (string $filename) => route('generations.download', [$generation, $filename]));

        return $payload;
    }
}
