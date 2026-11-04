<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('poster_categories')
            ->where('name', 'Интерьерные постеры')
            ->update(['name' => 'Интерьерные картины']);

        DB::table('posters')
            ->where('category', 'Интерьерные постеры')
            ->update(['category' => 'Интерьерные картины']);
    }

    public function down(): void
    {
        DB::table('poster_categories')
            ->where('name', 'Интерьерные картины')
            ->update(['name' => 'Интерьерные постеры']);

        DB::table('posters')
            ->where('category', 'Интерьерные картины')
            ->update(['category' => 'Интерьерные постеры']);
    }
};
