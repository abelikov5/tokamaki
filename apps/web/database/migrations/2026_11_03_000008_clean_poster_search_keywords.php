<?php

use App\Models\Poster;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Poster::query()
            ->whereNotNull('search_keywords')
            ->chunkById(100, function ($posters): void {
                foreach ($posters as $poster) {
                    $poster->search_keywords = Poster::normalizeSearchKeywords($poster->search_keywords ?? []);
                    $poster->save();
                }
            });
    }

    public function down(): void
    {
        // Removed punctuation cannot be restored reliably.
    }
};
