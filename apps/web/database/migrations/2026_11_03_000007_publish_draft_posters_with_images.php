<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('posters')
            ->where('status', 'draft')
            ->whereExists(fn ($query) => $query
                ->selectRaw('1')
                ->from('poster_images')
                ->whereColumn('poster_images.poster_id', 'posters.id'))
            ->update([
                'status' => 'published',
                'published_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Publication is intentionally not reverted.
    }
};
