<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ImportPostersJob;
use App\Models\PosterImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PosterImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'nm_ids' => ['sometimes', 'array', 'max:100'],
            'nm_ids.*' => ['string', 'distinct', 'max:32'],
            'image_limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $posterImport = PosterImport::create([
            'source_user_id' => (int) ($validated['user_id'] ?? 1),
            'selection_mode' => isset($validated['nm_ids']) ? 'selected' : 'all',
            'requested_nm_ids' => $validated['nm_ids'] ?? null,
            'image_limit' => (int) ($validated['image_limit'] ?? 3),
            'status' => 'pending',
        ]);

        ImportPostersJob::dispatch($posterImport->id);

        return response()->json([
            'data' => $this->serialize($posterImport),
        ], Response::HTTP_ACCEPTED);
    }

    public function show(PosterImport $posterImport): JsonResponse
    {
        return response()->json(['data' => $this->serialize($posterImport)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PosterImport $posterImport): array
    {
        return [
            'id' => $posterImport->id,
            'user_id' => $posterImport->source_user_id,
            'selection_mode' => $posterImport->selection_mode,
            'status' => $posterImport->status,
            'requested_nm_ids' => $posterImport->requested_nm_ids,
            'random_count' => $posterImport->random_count,
            'only_new' => $posterImport->only_new,
            'image_limit' => $posterImport->image_limit,
            'total_count' => $posterImport->total_count,
            'processed_count' => $posterImport->processed_count,
            'created_count' => $posterImport->created_count,
            'updated_count' => $posterImport->updated_count,
            'failed_count' => $posterImport->failed_count,
            'skipped_count' => $posterImport->skipped_count,
            'errors' => $posterImport->errors,
            'created_at' => $posterImport->created_at?->toIso8601String(),
            'finished_at' => $posterImport->finished_at?->toIso8601String(),
        ];
    }
}
