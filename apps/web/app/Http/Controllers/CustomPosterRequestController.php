<?php

namespace App\Http\Controllers;

use App\Models\CustomPosterRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CustomPosterRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480'],
            'size' => ['required', 'in:30x40,40x50'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'consent' => ['accepted'],
        ]);

        $file = $request->file('photo');
        $path = $file->store('custom-posters/'.now()->format('Y/m'), 'local');

        CustomPosterRequest::create([
            'status' => 'new',
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'size_code' => $validated['size'],
            'original_name' => $file->getClientOriginalName(),
            'photo_path' => $path,
            'comment' => $validated['comment'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        return back()->with('poster_request_success', 'Фотография получена. Мы проверим её и свяжемся с вами.');
    }

    public function photo(Request $request, CustomPosterRequest $customPosterRequest): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->canAccessPanel(Filament::getPanel('admin')), 403);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($customPosterRequest->photo_path), 404);

        return response($disk->get($customPosterRequest->photo_path), 200, [
            'Content-Type' => $disk->mimeType($customPosterRequest->photo_path) ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="photo"',
            'Cache-Control' => 'private, max-age=86400',
            'Vary' => 'Cookie',
        ]);
    }
}
