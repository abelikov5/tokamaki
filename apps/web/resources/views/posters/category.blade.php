@extends('layouts.posters')

@section('title'){{ $category->seo_title ?: $category->name.' — картины на холсте | Токамаки' }}@endsection
@section('description'){{ $category->seo_description ?: 'Картины категории «'.$category->name.'» с печатью на холсте и деревянным подрамником. Размеры 30×40 и 40×50 см.' }}@endsection

@section('content')
<nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="{{ route('home') }}">Главная</a><span>→</span><a href="{{ route('posters.index') }}">Картины на холсте</a><span>→</span><span>{{ $category->name }}</span></nav>

<section class="catalog-hero">
    <div>
        <p class="poster-kicker">Каталог Токамаки</p>
        <h1>Картины на холсте<br><em>{{ mb_strtolower($category->name) }}</em></h1>
        <p>{{ $category->seo_description ?: 'Готовые интерьерные картины с печатью на холсте, натянутом на деревянный подрамник. Выберите сюжет и подходящий размер.' }}</p>
    </div>
    <a class="custom-poster-card" href="{{ route('posters.custom') }}">
        <span>Ваше изображение</span>
        <strong>Картина<br>по своему фото</strong>
        <i>Создать →</i>
    </a>
</section>

<section class="catalog-shell">
    <div class="catalog-heading"><h2>{{ $category->name }}</h2><span>{{ $posters->total() }} {{ trans_choice('картина|картины|картин', $posters->total()) }}</span></div>

    @if($posters->isEmpty())
        <div class="empty-catalog"><h2>В этой категории пока нет картин</h2><p>Посмотрите весь каталог или создайте картину по свой фотографии.</p><a href="{{ route('posters.index') }}">Показать все картины</a></div>
    @else
        <div class="poster-grid">
            @foreach($posters as $poster)
                <article class="poster-card">
                    <a class="poster-card-image" href="{{ route('posters.show', $poster) }}">
                        @if($poster->primary_image_url)<img src="{{ $poster->primary_image_url }}" alt="{{ $poster->title }}" loading="lazy">@else<span>Токамаки</span>@endif
                    </a>
                    <div>
                        <p>{{ $poster->categories->take(3)->pluck('name')->join(' · ') ?: $category->name }}</p>
                        <h3><a href="{{ route('posters.show', $poster) }}">{{ $poster->title }}</a></h3>
                        <div class="poster-card-meta"><span>30×40 · 40×50</span><strong>от {{ number_format((int) (config('catalog.prices.30x40') ?: 990), 0, ',', ' ') }} ₽</strong></div>
                    </div>
                </article>
            @endforeach
        </div>
        <div class="catalog-pagination">{{ $posters->links() }}</div>
    @endif
</section>
@endsection
