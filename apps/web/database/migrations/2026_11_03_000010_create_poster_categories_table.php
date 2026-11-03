<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poster_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::table('posters', function (Blueprint $table): void {
            $table->foreignId('category_id')
                ->nullable()
                ->after('category')
                ->constrained('poster_categories')
                ->nullOnDelete();
        });

        $categories = DB::table('posters')
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->get(['id', 'category'])
            ->groupBy(fn (object $poster): string => Str::slug(Str::squish((string) $poster->category)));
        $sortOrder = 0;

        foreach ($categories as $slug => $posters) {
            if ($slug === '') {
                continue;
            }

            $sortOrder += 10;
            $name = Str::squish((string) $posters->first()->category);
            $categoryId = DB::table('poster_categories')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('posters')->whereIn('id', $posters->pluck('id'))->update(['category_id' => $categoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('posters', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('category_id');
        });

        Schema::dropIfExists('poster_categories');
    }
};
