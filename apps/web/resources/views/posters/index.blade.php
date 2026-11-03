@extends('layouts.posters')

@section('title', 'Постеры на подрамнике 30×40 и 40×50 — каталог Токамаки')
@section('description', 'Готовые интерьерные постеры на подрамнике. Поиск по сюжету, стилю, комнате, цвету и размеру.')

@section('content')
<section class="catalog-hero">
    <div>
        <p class="poster-kicker">Готовы украсить стену</p>
        <h1>Постеры, которые<br><em>собирают интерьер</em></h1>
        <p>Выбирайте готовую работу или создайте свою. Холст уже натянут на деревянный подрамник — останется только повесить.</p>
    </div>
    <a class="custom-poster-card" href="{{ route('posters.custom') }}">
        <span>Ваше изображение</span>
        <strong>Постер<br>по своему фото</strong>
        <i>Создать →</i>
    </a>
</section>

<section class="catalog-shell">
    <form class="catalog-tools" method="get" action="{{ route('posters.index') }}">
        <label class="catalog-search">
            <span class="sr-only">Поиск постеров</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Например, бежевый постер с горами">
            <button type="submit">Найти</button>
        </label>
        <div class="catalog-filters">
            <select name="category" aria-label="Категория">
                <option value="">Все сюжеты</option>
                @foreach($categories as $category)
                    <option value="{{ $category->category }}" @selected(($filters['category'] ?? '') === $category->category)>{{ $category->category }} · {{ $category->total }}</option>
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
        <h2>{{ filled($filters['q'] ?? null) ? 'Результаты поиска' : 'Готовые постеры' }}</h2>
        <span>{{ $posters->total() }} {{ trans_choice('постер|постера|постеров', $posters->total()) }}</span>
    </div>

    @if($posters->isEmpty())
        <div class="empty-catalog"><h2>Ничего не нашли</h2><p>Попробуйте убрать часть фильтров или описать сюжет другими словами.</p><a href="{{ route('posters.index') }}">Показать все постеры</a></div>
    @else
        <div class="poster-grid">
            @foreach($posters as $poster)
                <article class="poster-card">
                    <a class="poster-card-image" href="{{ route('posters.show', $poster) }}">
                        @if($poster->primary_image_url)<img src="{{ $poster->primary_image_url }}" alt="{{ $poster->title }}" loading="lazy">@else<span>Токамаки</span>@endif
                    </a>
                    <div>
                        <p>{{ $poster->category ?? 'Интерьерный постер' }}</p>
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
