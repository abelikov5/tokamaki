<?php

namespace App\Http\Controllers;

use App\Models\Poster;
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

        $query = Poster::query()->published()->with(['images', 'variants']);

        if (filled($validated['q'] ?? null)) {
            $this->applySearch($query, (string) $validated['q']);
        }

        $query
            ->when($validated['category'] ?? null, fn (Builder $builder, string $category) => $builder->where('category', $category))
            ->when($validated['orientation'] ?? null, fn (Builder $builder, string $orientation) => $builder->where('orientation', $orientation))
            ->when(($validated['size'] ?? null) === '40x50', fn (Builder $builder) => $builder->whereHas('variants', fn (Builder $variants) => $variants->where('size_code', '40x50')->where('is_active', true)));

        match ($validated['sort'] ?? 'new') {
            'price_asc' => $query->withMin('variants', 'price')->orderBy('variants_min_price'),
            'price_desc' => $query->withMax('variants', 'price')->orderByDesc('variants_max_price'),
            default => $query->orderByDesc('published_at'),
        };

        return view('posters.index', [
            'posters' => $query->paginate(24)->withQueryString(),
            'categories' => Poster::query()->published()->whereNotNull('category')
                ->selectRaw('category, count(*) as total')
                ->groupBy('category')
                ->orderByDesc('total')
                ->get(),
            'filters' => $validated,
        ]);
    }

    public function show(Poster $poster): View
    {
        abort_unless($poster->status === 'published' && $poster->published_at !== null, 404);
        $poster->load(['images', 'variants']);

        $related = Poster::query()
            ->published()
            ->whereKeyNot($poster->id)
            ->when($poster->category, fn (Builder $query) => $query->where('category', $poster->category))
            ->with(['images', 'variants'])
            ->limit(4)
            ->get();

        return view('posters.show', compact('poster', 'related'));
    }

    public function sitemap(): Response
    {
        return response()
            ->view('sitemap', [
                'posters' => Poster::query()->published()->orderBy('updated_at')->get(['slug', 'updated_at']),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
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
                        $termQuery->orWhere('title', 'like', '%'.$variant.'%')
                            ->orWhere('description', 'like', '%'.$variant.'%')
                            ->orWhere('category', 'like', '%'.$variant.'%')
                            ->orWhere('style', 'like', '%'.$variant.'%')
                            ->orWhere('room', 'like', '%'.$variant.'%')
                            ->orWhere('primary_color', 'like', '%'.$variant.'%');
                    }
                });
            }
        });
    }
}
