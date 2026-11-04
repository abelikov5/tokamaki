@extends('layouts.posters')

@section('title', 'Картины на холсте 30×40 и 40×50 — Токамаки')
@section('description', 'Готовые интерьерные картины с печатью на холсте и деревянным подрамником. Поиск по сюжету, стилю, комнате и цвету.')

@section('content')
<section class="catalog-hero">
    <div>
        <p class="poster-kicker">Готовы украсить стену</p>
        <h1>Картины на холсте,<br><em>которые собирают интерьер</em></h1>
        <p>Выбирайте готовую работу или создайте свою. Холст уже натянут на деревянный подрамник — останется только повесить.</p>
    </div>
    <a class="custom-poster-card" href="{{ route('posters.custom') }}">
        <span>Ваше изображение</span>
        <strong>Картина<br>по своему фото</strong>
        <i>Создать →</i>
    </a>
</section>

<section class="catalog-shell">
    <form class="catalog-tools" method="get" action="{{ route('posters.index') }}">
        <label class="catalog-search">
            <span class="sr-only">Поиск картин</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Например, бежевая картина с горами">
            <button type="submit">Найти</button>
        </label>
        <div class="catalog-filters">
            <select name="category" aria-label="Категория">
                <option value="">Все группы</option>
                @foreach($categories->groupBy('type') as $type => $group)
                    <optgroup label="{{ \App\Models\PosterCategory::typeOptions()[$type] ?? 'Другие' }}">
                        @foreach($group as $category)
                            <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }} · {{ $category->total }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <select name="size" aria-label="Размер">
                <option value="">Любой размер</option>
                <option value="30x40" @selected(($filters['size'] ?? '') === '30x40')>30×40 см</option>
                <option value="40x50" @selected(($filters['size'] ?? '') === '40x50')>40×50 см</option>
            </select>
            <select name="orientation" aria-label="Ориентация">
                <option value="">Любая ориентация</option>
                <option value="vertical" @selected(($filters['orientation'] ?? '') === 'vertical')>Вертикальные</option>
                <option value="horizontal" @selected(($filters['orientation'] ?? '') === 'horizontal')>Горизонтальные</option>
            </select>
            <select name="sort" aria-label="Сортировка">
                <option value="new">Сначала новые</option>
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Сначала дешевле</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Сначала дороже</option>
            </select>
            <button type="submit">Применить</button>
            @if($filters !== [])<a href="{{ route('posters.index') }}">Сбросить</a>@endif
        </div>
    </form>

    <div class="catalog-heading">
        <h2>{{ filled($filters['q'] ?? null) ? 'Результаты поиска' : 'Готовые картины' }}</h2>
        <span>{{ $posters->total() }} {{ trans_choice('картина|картины|картин', $posters->total()) }}</span>
    </div>

    @if($posters->isEmpty())
        <div class="empty-catalog"><h2>Ничего не нашли</h2><p>Попробуйте убрать часть фильтров или описать сюжет другими словами.</p><a href="{{ route('posters.index') }}">Показать все картины</a></div>
    @else
        <div class="poster-grid">
            @foreach($posters as $poster)
                <article class="poster-card">
                    <a class="poster-card-image" href="{{ route('posters.show', $poster) }}">
                        @if($poster->primary_image_url)<img src="{{ $poster->primary_image_url }}" alt="{{ $poster->title }}" loading="lazy">@else<span>Токамаки</span>@endif
                    </a>
                    <div>
                        <p>{{ $poster->categories->take(3)->pluck('name')->join(' · ') ?: ($poster->category ?? 'Интерьерная картина') }}</p>
                        <h3><a href="{{ route('posters.show', $poster) }}">{{ $poster->title }}</a></h3>
                        <div class="poster-card-meta">
                            <span>30×40 · 40×50</span>
                            <strong>от {{ number_format((int) (config('catalog.prices.30x40') ?: 990), 0, ',', ' ') }} ₽</strong>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="catalog-pagination">{{ $posters->links() }}</div>
    @endif
</section>
@endsection
