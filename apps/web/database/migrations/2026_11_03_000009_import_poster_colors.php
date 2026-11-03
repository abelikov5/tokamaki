<?php

use App\Models\Poster;
use App\Services\PosterContentBuilder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $builder = app(PosterContentBuilder::class);

        Poster::query()
            ->whereNotNull('source_payload')
            ->chunkById(100, function ($posters) use ($builder): void {
                foreach ($posters as $poster) {
                    $source = $poster->source_payload ?? [];
                    $colors = $builder->sourceColors($source);

                    if ($colors === []) {
                        continue;
                    }

                    $poster->primary_color = implode(', ', $colors);
                    $poster->search_keywords = Poster::normalizeSearchKeywords([
                        ...($poster->search_keywords ?? []),
                        ...$colors,
                    ]);
                    $poster->save();
                }
            });
    }

    public function down(): void
    {
        // Imported source colors should remain available.
    }
};
