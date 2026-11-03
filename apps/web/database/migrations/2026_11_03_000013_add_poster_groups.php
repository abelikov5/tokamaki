<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('poster_categories', function (Blueprint $table): void {
            $table->string('type', 24)->default('theme')->after('slug')->index();
        });

        Schema::create('poster_category_poster', function (Blueprint $table): void {
            $table->foreignId('poster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poster_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['poster_id', 'poster_category_id']);
        });

        $types = [
            'Интерьерные постеры' => 'collection',
            'Бохо' => 'style', 'Современный' => 'style', 'Минимализм' => 'style', 'Скандинавский' => 'style',
            'Лофт' => 'style', 'Винтаж' => 'style', 'Ретро' => 'style', 'Акварель' => 'style', 'Графика' => 'style',
            'Гостиная' => 'room', 'Спальня' => 'room', 'Детская' => 'room', 'Кухня' => 'room',
            'Кабинет' => 'room', 'Прихожая' => 'room',
        ];

        DB::table('poster_categories')->orderBy('id')->each(function (object $category) use ($types): void {
            DB::table('poster_categories')->where('id', $category->id)->update([
                'type' => $types[$category->name] ?? $category->type ?? 'theme',
            ]);
        });

        DB::table('posters')->whereNotNull('category_id')->orderBy('id')->each(function (object $poster): void {
            DB::table('poster_category_poster')->insertOrIgnore([
                'poster_id' => $poster->id,
                'poster_category_id' => $poster->category_id,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poster_category_poster');
        Schema::table('poster_categories', fn (Blueprint $table) => $table->dropColumn('type'));
    }
};
