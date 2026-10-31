<?php

namespace App\Http\Controllers;

use App\Models\Generation;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OrderController extends Controller
{
    public function store(Request $request, Generation $generation): JsonResponse
    {
        abort_unless($generation->status === 'completed' && filled($generation->result_id), 404);

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'city' => ['required', 'string', 'max:120'],
            'street' => ['required', 'string', 'max:180'],
            'designer_processing' => ['required', 'boolean'],
        ]);

        try {
            $generator = Http::timeout(config('services.generator.timeout'));
            $original = $generator->get(config('services.generator.url')."/api/v1/results/{$generation->result_id}/original.png");
            $generated = $generator->get(config('services.generator.url')."/api/v1/results/{$generation->result_id}/preview.png");
        } catch (ConnectionException) {
            return response()->json([
                'message' => 'Не удалось сохранить изображения заказа. Попробуйте ещё раз.',
            ], 503);
        }

        if ($original->failed() || $generated->failed()) {
            return response()->json([
                'message' => 'Не удалось получить изображения заказа. Попробуйте ещё раз.',
            ], 502);
        }

        $directory = 'orders/'.Str::uuid();
        $originalPath = $directory.'/original.png';
        $generatedPath = $directory.'/generated.png';
        $disk = Storage::disk('local');
        if (! $disk->put($originalPath, $original->body()) || ! $disk->put($generatedPath, $generated->body())) {
            $disk->deleteDirectory($directory);

            return response()->json([
                'message' => 'Не удалось сохранить изображения заказа. Попробуйте ещё раз.',
            ], 500);
        }

        try {
            $order = Order::create([
                'generation_id' => $generation->id,
                'status' => 'new',
                'email' => $validated['email'],
                'city' => $validated['city'],
                'street' => $validated['street'],
                'designer_processing' => $validated['designer_processing'],
                'amount' => (int) config('orders.base_price')
                    + ($validated['designer_processing'] ? (int) config('orders.designer_price') : 0),
                'original_name' => $generation->original_name,
                'original_image_path' => $originalPath,
                'generated_image_path' => $generatedPath,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
            ]);
        } catch (Throwable $exception) {
            $disk->deleteDirectory($directory);
            throw $exception;
        }

        return response()->json([
            'message' => 'Заказ принят. Мы свяжемся с вами по электронной почте.',
            'order_id' => $order->id,
        ], 201);
    }

    public function image(Request $request, Order $order, string $kind): Response
    {
        $user = $request->user();
        abort_unless(
            $user instanceof User && $user->canAccessPanel(Filament::getPanel('admin')),
            403,
        );

        $path = $kind === 'original'
            ? $order->original_image_path
            : $order->generated_image_path;
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$kind.'.png"',
            'Cache-Control' => 'private, max-age=86400',
            'Vary' => 'Cookie',
        ]);
    }
}
