@extends('layouts.posters')

@section('title'){{ $poster->seo_title ?: $poster->title.' — постер на подрамнике' }}@endsection
@section('description'){{ $poster->seo_description ?: $poster->short_description }}@endsection

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
        'hasVariant' => $poster->variants->map(function($variant) use ($poster) {
            $product = [
                '@type' => 'Product',
                'name' => $poster->title.' '.$variant->size_code,
                'sku' => $variant->nm_id,
                'size' => str_replace('x', '×', $variant->size_code).' см',
                'url' => route('posters.show', $poster).'?size='.$variant->size_code,
            ];
            if ($variant->price) {
                $product['offers'] = [
                    '@type' => 'Offer',
                    'price' => $variant->price,
                    'priceCurrency' => 'RUB',
                    'availability' => 'https://schema.org/InStock',
                    'url' => $variant->wildberries_url,
                ];
            }
            return $product;
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
<nav class="breadcrumbs" aria-label="Хлебные крошки"><a href="{{ route('home') }}">Главная</a><span>→</span><a href="{{ route('posters.index') }}">Постеры</a><span>→</span><span>{{ $poster->title }}</span></nav>
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
        <p class="poster-kicker">{{ $poster->category ?? 'Интерьерный постер' }}</p>
        <h1>{{ $poster->title }}</h1>
        <p class="product-lead">{{ $poster->short_description }}</p>
        <div class="product-facts"><span>Холст</span><span>Деревянный подрамник</span><span>Готов к размещению</span></div>
        <fieldset class="variant-picker">
            <legend>Выберите размер</legend>
            @foreach($poster->variants as $variant)
                <label>
                    <input type="radio" name="poster-size" value="{{ $variant->size_code }}" data-url="{{ $variant->wildberries_url }}" data-price="{{ $variant->price }}" @checked($loop->first)>
                    <span><b>{{ str_replace('x', '×', $variant->size_code) }} см</b><small>{{ $variant->price ? number_format($variant->price, 0, ',', ' ').' ₽' : 'Цена на Wildberries' }}</small></span>
                </label>
            @endforeach
        </fieldset>
        @php($firstVariant = $poster->variants->first())
        @if($firstVariant)
            <a id="wb-buy" class="wb-buy" href="{{ $firstVariant->wildberries_url }}" rel="nofollow sponsored">Купить на Wildberries <span>↗</span></a>
        @endif
        <p class="market-note">Цена и наличие проверяются на странице Wildberries.</p>
        <details open><summary>О постере</summary><div>{!! nl2br(e($poster->description)) !!}</div></details>
        <details><summary>Размер и материал</summary><div>Интерьерная печать на холсте. Изображение аккуратно натянуто на деревянный подрамник.</div></details>
    </div>
</section>

@if($related->isNotEmpty())
<section class="related-section"><div class="catalog-heading"><h2>Похожие постеры</h2><a href="{{ route('posters.index', ['category' => $poster->category]) }}">Смотреть все →</a></div><div class="poster-grid related-grid">
@foreach($related as $item)<article class="poster-card"><a class="poster-card-image" href="{{ route('posters.show', $item) }}">@if($item->primary_image_url)<img src="{{ $item->primary_image_url }}" alt="{{ $item->title }}" loading="lazy">@endif</a><div><p>{{ $item->category }}</p><h3><a href="{{ route('posters.show', $item) }}">{{ $item->title }}</a></h3></div></article>@endforeach
</div></section>
@endif
<script>
document.querySelectorAll('[name="poster-size"]').forEach((input) => input.addEventListener('change', () => {
    const button = document.getElementById('wb-buy');
    if (button) button.href = input.dataset.url;
}));
</script>
@endsection
