<?php

namespace App\Http\Controllers;

use App\Models\CustomPosterRequest;
use App\Models\Poster;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class CustomPosterRequestController extends Controller
{
    public function create(Request $request): View
    {
        $validated = $request->validate([
            'poster' => ['nullable', 'integer'],
            'size' => ['nullable', 'in:30x40,40x50'],
        ]);

        $poster = isset($validated['poster'])
            ? Poster::query()->published()->with('images')->findOrFail($validated['poster'])
            : null;

        return view('posters.custom', [
            'poster' => $poster,
            'selectedSize' => $validated['size'] ?? '30x40',
            'prices' => [
                '30x40' => (int) (config('catalog.prices.30x40') ?: 990),
                '40x50' => (int) (config('catalog.prices.40x50') ?: 1290),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'poster_id' => ['nullable', 'integer'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'size' => ['required', 'in:30x40,40x50'],
            'email' => ['required', 'email', 'max:254'],
            'city' => ['required', 'string', 'max:120'],
            'street' => ['required', 'string', 'max:180'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ]);

        $poster = isset($validated['poster_id'])
            ? Poster::query()->published()->find($validated['poster_id'])
            : null;

        if (isset($validated['poster_id']) && ! $poster) {
            throw ValidationException::withMessages(['poster_id' => 'Выбранный постер недоступен.']);
        }

        $file = $request->file('photo');
        if (! $poster && ! $file) {
            throw ValidationException::withMessages(['photo' => 'Загрузите фотографию для персонального постера.']);
        }

        $path = $file?->store('custom-posters/'.now()->format('Y/m'), 'local');
        $amount = (int) (config('catalog.prices.'.$validated['size']) ?: ($validated['size'] === '30x40' ? 990 : 1290));

        CustomPosterRequest::create([
            'status' => 'new',
            'poster_id' => $poster?->id,
            'email' => $validated['email'],
            'city' => $validated['city'],
            'street' => $validated['street'],
            'size_code' => $validated['size'],
            'amount' => $amount,
            'original_name' => $file?->getClientOriginalName(),
            'photo_path' => $path,
            'comment' => $validated['comment'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        return back()->with('poster_request_success', 'Заказ принят. Мы напишем вам на почту и уточним удобный пункт выдачи.');
    }

    public function photo(Request $request, CustomPosterRequest $customPosterRequest): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessPanel(Filament::getPanel('admin')), 403);

        $disk = Storage::disk('local');
        abort_unless($customPosterRequest->photo_path && $disk->exists($customPosterRequest->photo_path), 404);

        return response($disk->get($customPosterRequest->photo_path), 200, [
            'Content-Type' => $disk->mimeType($customPosterRequest->photo_path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="photo"',
            'Cache-Control' => 'private, max-age=86400',
            'Vary' => 'Cookie',
        ]);
    }
}
