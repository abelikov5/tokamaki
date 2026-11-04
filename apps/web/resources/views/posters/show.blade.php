@extends('layouts.posters')

@section('title'){{ $poster->seo_title ?: $poster->title.' — картина на холсте' }}@endsection
@section('description'){{ $poster->seo_description ?: $poster->short_description }}@endsection

@php
    $prices = [
        '30x40' => (int) (config('catalog.prices.30x40') ?: 990),
        '40x50' => (int) (config('catalog.prices.40x50') ?: 1290),
    ];
    $selectedSize = in_array(request('size'), array_keys($prices), true) ? request('size') : '30x40';
    $currentOrderSize = in_array(old('size', $selectedSize), array_keys($prices), true) ? old('size', $selectedSize) : $selectedSize;
    $activeVariants = $poster->variants->where('is_active', true)->keyBy('size_code');
    $activeGroups = $poster->categories->where('is_active', true);
    $primaryGroup = $activeGroups->firstWhere('type', 'theme') ?? $activeGroups->first();
@endphp

@section('head')
@php
    $schema = [
        '@context' => 'https://schema.org',
        '@type' => 'ProductGroup',
        'name' => $poster->title,
        'description' => $poster->short_description,
        'image' => $poster->images->map(fn($image) => $image->path ? Storage::disk('public')->url($image->path) : $image->remote_url)->filter()->values()->all(),
        'brand' => ['@type' => 'Brand', 'name' => 'Токамаки'],
        'productGroupID' => 'tokamaki-'.$poster->id,
        'variesBy' => ['https://schema.org/size'],
        'hasVariant' => collect($prices)->map(function($price, $size) use ($poster, $activeVariants) {
            $variant = $activeVariants->get($size);
            return [
                '@type' => 'Product',
                'name' => $poster->title.' '.$size,
                'sku' => $variant?->nm_id ?: 'tokamaki-'.$poster->id.'-'.$size,
                'size' => str_replace('x', '×', $size).' см',
                'url' => route('posters.show', $poster).'?size='.$size,
                'offers' => [
                    '@type' => 'Offer',
                    'price' => $price,
                    'priceCurrency' => 'RUB',
                    'availability' => 'https://schema.org/InStock',
                    'url' => route('posters.custom', ['poster' => $poster->id, 'size' => $size]),
                ],
            ];
        })->values()->all(),
    ];
@endphp
<meta property="og:type" content="product">
<meta property="og:title" content="{{ $poster->title }}">
<meta property="og:description" content="{{ $poster->short_description }}">
@if($poster->primary_image_url)<meta property="og:image" content="{{ $poster->primary_image_url }}">@endif
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endsection

@section('content')
<nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="{{ route('home') }}">Главная</a><span>→</span><a href="{{ route('posters.index') }}">Картины на холсте</a><span>→</span><span>{{ $poster->title }}</span></nav>
<section class="product-layout">
    <div class="product-gallery">
        @forelse($poster->images as $image)
            @php($url = $image->path ? Storage::disk('public')->url($image->path) : $image->remote_url)
            @if($url)<figure><img src="{{ $url }}" alt="{{ $image->alt_text ?: $poster->title }}" @if(!$loop->first) loading="lazy" @endif></figure>@endif
        @empty
            <figure class="product-placeholder">Токамаки</figure>
        @endforelse
    </div>
    <div class="product-info">
        <p class="poster-kicker">{{ $activeGroups->pluck('name')->join(' · ') ?: ($poster->category ?? 'Интерьерная картина') }}</p>
        <h1>{{ $poster->title }}</h1>
        <p class="product-lead">{{ $poster->short_description }}</p>
        <div class="product-facts"><span>Бесплатная доставка</span><span>Сочные цвета</span><span>Крепежи в комплекте!</span></div>
        <fieldset class="variant-picker">
            <legend>Выберите размер</legend>
            @foreach($prices as $size => $price)
                <label>
                    <input type="radio" name="poster-size" value="{{ $size }}" data-price="{{ $price }}" @checked($currentOrderSize === $size)>
                    <span><b>{{ str_replace('x', '×', $size) }} см</b><small>{{ number_format($price, 0, ',', ' ') }} ₽</small></span>
                </label>
            @endforeach
        </fieldset>
        <button id="poster-order" class="wb-buy" type="button">Оформить заказ <span>→</span></button>
        <p class="market-note">Бесплатная доставка до ПВЗ Авито или СДЭК.</p>
        <details open><summary>О картине</summary><div>{!! nl2br(e($poster->description)) !!}</div></details>
        <details><summary>Размер и материал</summary><div>Интерьерная печать на холсте. Изображение аккуратно натянуто на деревянный подрамник.</div></details>
    </div>
</section>

<dialog id="poster-order-dialog" class="poster-order-dialog" aria-labelledby="poster-order-dialog-title">
    <div class="poster-order-dialog-card">
        <button class="poster-order-dialog-close" type="button" data-poster-order-close aria-label="Закрыть">×</button>

        @if(session('poster_request_success'))
            <div class="poster-order-success">
                <span aria-hidden="true">✓</span>
                <h2 id="poster-order-dialog-title">Заказ принят</h2>
                <p>{{ session('poster_request_success') }}</p>
                <button type="button" data-poster-order-close>Закрыть</button>
            </div>
        @else
            <p class="poster-order-eyebrow">Оформление заказа</p>
            <h2 id="poster-order-dialog-title">Куда доставить картину?</h2>
            <p class="poster-order-dialog-copy">Доставка до ПВЗ Авито или СДЭК бесплатная. Менеджер уточнит удобный пункт выдачи по электронной почте.</p>

            <form class="poster-order-dialog-form" method="post" action="{{ route('posters.custom.store') }}">
                @csrf
                <input type="hidden" name="poster_id" value="{{ $poster->id }}">
                <input id="poster-order-size" type="hidden" name="size" value="{{ $currentOrderSize }}">
                <input type="hidden" name="consent" value="1">

                @if($errors->any())
                    <div class="poster-order-dialog-errors" role="alert">
                        <strong>Проверьте форму:</strong>
                        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <label><span>Email</span><input name="email" type="email" autocomplete="email" required maxlength="254" value="{{ old('email') }}" placeholder="name@example.ru"></label>
                <label><span>Город</span><input name="city" type="text" autocomplete="address-level2" required maxlength="120" value="{{ old('city') }}" placeholder="Москва"></label>
                <label class="poster-order-street-field"><span>Улица</span><input name="street" type="text" autocomplete="address-line1" required maxlength="180" value="{{ old('street') }}" placeholder="ул. Соборная"></label>
                <div class="poster-order-dialog-summary">
                    <span>Картина <b id="poster-order-size-label">{{ str_replace('x', '×', $currentOrderSize) }} см</b></span>
                    <strong id="poster-order-total">{{ number_format($prices[$currentOrderSize], 0, ',', ' ') }} ₽</strong>
                </div>
                <button class="poster-order-submit" type="submit">Оформить заказ</button>
                <small>Нажимая кнопку, вы соглашаетесь с <a href="{{ route('privacy') }}" target="_blank">политикой конфиденциальности</a>.</small>
            </form>
        @endif
    </div>
</dialog>

@if($related->isNotEmpty())
<section class="related-section"><div class="catalog-heading"><h2>Похожие картины</h2>@if($primaryGroup)<a href="{{ route('posters.show', $primaryGroup) }}">Смотреть все →</a>@endif</div><div class="poster-grid related-grid">
@foreach($related as $item)<article class="poster-card"><a class="poster-card-image" href="{{ route('posters.show', $item) }}">@if($item->primary_image_url)<img src="{{ $item->primary_image_url }}" alt="{{ $item->title }}" loading="lazy">@endif</a><div><p>{{ $item->categories->take(3)->pluck('name')->join(' · ') ?: $item->category }}</p><h3><a href="{{ route('posters.show', $item) }}">{{ $item->title }}</a></h3></div></article>@endforeach
</div></section>
@endif
<script>
const posterOrderDialog = document.getElementById('poster-order-dialog');
const posterOrderSize = document.getElementById('poster-order-size');
const posterOrderSizeLabel = document.getElementById('poster-order-size-label');
const posterOrderTotal = document.getElementById('poster-order-total');
const posterPriceFormatter = new Intl.NumberFormat('ru-RU');

document.querySelectorAll('[name="poster-size"]').forEach((input) => input.addEventListener('change', () => {
    if (posterOrderSize) posterOrderSize.value = input.value;
    if (posterOrderSizeLabel) posterOrderSizeLabel.textContent = `${input.value.replace('x', '×')} см`;
    if (posterOrderTotal) posterOrderTotal.textContent = `${posterPriceFormatter.format(Number(input.dataset.price))} ₽`;
}));
document.getElementById('poster-order').addEventListener('click', () => posterOrderDialog.showModal());
document.querySelectorAll('[data-poster-order-close]').forEach((button) => button.addEventListener('click', () => posterOrderDialog.close()));
posterOrderDialog.addEventListener('click', (event) => {
    if (event.target === posterOrderDialog) posterOrderDialog.close();
});
@if($errors->any() || session('poster_request_success'))
posterOrderDialog.showModal();
@endif
</script>
@endsection
