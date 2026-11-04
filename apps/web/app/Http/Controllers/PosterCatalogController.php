<?php

namespace App\Http\Controllers;

use App\Models\Poster;
use App\Models\PosterCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class PosterCatalogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:80'],
            'orientation' => ['nullable', 'in:vertical,horizontal,square'],
            'size' => ['nullable', 'in:30x40,40x50'],
            'sort' => ['nullable', 'in:new,price_asc,price_desc'],
        ]);

        $query = Poster::query()->published()->with(['images', 'variants', 'categories']);

        if (filled($validated['q'] ?? null)) {
            $this->applySearch($query, (string) $validated['q']);
        }

        $query
            ->when($validated['category'] ?? null, fn (Builder $builder, string $category) => $builder->whereHas(
                'categories',
                fn (Builder $categoryQuery) => $categoryQuery->active()->where('slug', $category),
            ))
            ->when($validated['orientation'] ?? null, fn (Builder $builder, string $orientation) => $builder->where('orientation', $orientation))
            ->when(($validated['size'] ?? null) === '40x50', fn (Builder $builder) => $builder->whereHas('variants', fn (Builder $variants) => $variants->where('size_code', '40x50')->where('is_active', true)));

        match ($validated['sort'] ?? 'new') {
            'price_asc' => $query->withMin('variants', 'price')->orderBy('variants_min_price'),
            'price_desc' => $query->withMax('variants', 'price')->orderByDesc('variants_max_price'),
            default => $query->orderByDesc('published_at'),
        };

        return view('posters.index', [
            'posters' => $query->paginate(24)->withQueryString(),
            'categories' => PosterCategory::query()->active()
                ->withCount(['posters as total' => fn (Builder $query) => $query->published()])
                ->whereHas('posters', fn (Builder $query) => $query->published())
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'filters' => $validated,
        ]);
    }

    public function show(string $slug): View
    {
        $category = PosterCategory::query()->active()->where('slug', $slug)->first();

        if ($category !== null) {
            return $this->showCategory($category);
        }

        $poster = Poster::query()->where('slug', $slug)->firstOrFail();
        abort_unless($poster->status === 'published' && $poster->published_at !== null, 404);
        $poster->load(['images', 'variants', 'categories']);

        $groupIds = $poster->categories->pluck('id');
        $primaryTheme = $poster->categories->first(
            fn (PosterCategory $category): bool => $category->type === 'theme' && $category->id === $poster->category_id,
        ) ?? $poster->categories->firstWhere('type', 'theme');
        $relatedGroupIds = [
            'primary_theme' => $primaryTheme ? [$primaryTheme->id] : [],
            'theme' => $poster->categories
                ->where('type', 'theme')
                ->where('id', '!=', $primaryTheme?->id)
                ->pluck('id'),
            'style' => $poster->categories->where('type', 'style')->pluck('id'),
            'collection' => $poster->categories->where('type', 'collection')->pluck('id'),
            'room' => $poster->categories->where('type', 'room')->pluck('id'),
        ];

        $relatedQuery = Poster::query()
            ->published()
            ->whereKeyNot($poster->id)
            ->when($groupIds->isNotEmpty(), fn (Builder $query) => $query->whereHas(
                'categories',
                fn (Builder $categories) => $categories->whereIn('poster_categories.id', $groupIds),
            ))
            ->with(['images', 'variants', 'categories']);

        foreach ($relatedGroupIds as $group => $ids) {
            $relatedQuery->withCount([
                'categories as related_'.$group.'_count' => fn (Builder $categories) => $categories
                    ->whereIn('poster_categories.id', $ids),
            ]);
        }

        $related = $relatedQuery
            ->orderByDesc('related_primary_theme_count')
            ->orderByDesc('related_theme_count')
            ->orderByDesc('related_style_count')
            ->when(filled($poster->primary_color), fn (Builder $query) => $query
                ->orderByRaw('CASE WHEN primary_color = ? THEN 1 ELSE 0 END DESC', [$poster->primary_color]))
            ->when(filled($poster->orientation), fn (Builder $query) => $query
                ->orderByRaw('CASE WHEN orientation = ? THEN 1 ELSE 0 END DESC', [$poster->orientation]))
            ->orderByDesc('related_collection_count')
            ->orderByDesc('related_room_count')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(4)
            ->get();

        return view('posters.show', compact('poster', 'related'));
    }

    public function sitemap(): Response
    {
        return response()
            ->view('sitemap', [
                'posters' => Poster::query()->published()->orderBy('updated_at')->get(['slug', 'updated_at']),
                'categories' => PosterCategory::query()->active()
                    ->whereHas('posters', fn (Builder $query) => $query->published())
                    ->orderBy('updated_at')->get(['slug', 'updated_at']),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    private function showCategory(PosterCategory $category): View
    {
        $posters = $category->posters()
            ->published()
            ->with(['images', 'variants', 'categories'])
            ->orderByDesc('published_at')
            ->paginate(24);

        return view('posters.category', compact('category', 'posters'));
    }

    private function applySearch(Builder $query, string $search): void
    {
        $synonyms = [
            'машина' => ['автомобиль', 'авто'],
            'авто' => ['машина', 'автомобиль'],
            'кот' => ['кошка', 'котёнок'],
            'море' => ['океан', 'пляж'],
            'детская' => ['ребёнок', 'мультфильм'],
        ];
        $terms = Str::of($search)->lower()->replaceMatches('/[^\pL\pN]+/u', ' ')->squish()->explode(' ');

        $query->where(function (Builder $searchQuery) use ($terms, $synonyms): void {
            foreach ($terms as $term) {
                $variants = array_unique([$term, ...($synonyms[$term] ?? [])]);
                $searchQuery->where(function (Builder $termQuery) use ($variants): void {
                    foreach ($variants as $variant) {
                        $like = '%'.mb_strtolower($variant).'%';

                        $termQuery
                            ->orWhereRaw('LOWER(title) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(category) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(style) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(room) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(primary_color) LIKE ?', [$like])
                            ->orWhereJsonContains('search_keywords', $variant)
                            ->orWhereHas('categories', fn (Builder $categories) => $categories->whereRaw('LOWER(name) LIKE ?', [$like]));
                    }
                });
            }
        });
    }
}
