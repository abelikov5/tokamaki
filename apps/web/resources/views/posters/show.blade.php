@extends('layouts.posters')

@section('title'){{ $poster->seo_title ?: $poster->title.' — картина на холсте' }}@endsection
@section('description'){{ $poster->seo_description ?: $poster->short_description }}@endsection

@php
    $prices = [
        '30x40' => (int) (config('catalog.prices.30x40') ?: 990),
        '40x50' => (int) (config('catalog.prices.40x50') ?: 1290),
    ];
    $selectedSize = in_array(request('size'), array_keys($prices), true) ? request('size') : '30x40';
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
        <div class="product-facts"><span>Холст</span><span>Деревянный подрамник</span><span>Готов к размещению</span></div>
        <fieldset class="variant-picker">
            <legend>Выберите размер</legend>
            @foreach($prices as $size => $price)
                @php($wbVariant = $activeVariants->get($size))
                <label>
                    <input type="radio" name="poster-size" value="{{ $size }}" data-order-url="{{ route('posters.custom', ['poster' => $poster->id, 'size' => $size]) }}" data-wb-url="{{ $wbVariant?->wildberries_url }}" @checked($selectedSize === $size)>
                    <span><b>{{ str_replace('x', '×', $size) }} см</b><small>{{ number_format($price, 0, ',', ' ') }} ₽</small></span>
                </label>
            @endforeach
        </fieldset>
        @php($selectedWbVariant = $activeVariants->get($selectedSize))
        <a id="poster-order" class="wb-buy" href="{{ route('posters.custom', ['poster' => $poster->id, 'size' => $selectedSize]) }}">Оформить заказ <span>→</span></a>
        <a id="wb-buy" class="wb-secondary" href="{{ $selectedWbVariant?->wildberries_url ?: '#' }}" rel="nofollow sponsored" @if(!$selectedWbVariant) hidden @endif>Купить на Wildberries <span>↗</span></a>
        <p class="market-note">Размер 30×40 доступен всегда, даже если его нет на Wildberries. Бесплатная доставка до ПВЗ.</p>
        <details open><summary>О картине</summary><div>{!! nl2br(e($poster->description)) !!}</div></details>
        <details><summary>Размер и материал</summary><div>Интерьерная печать на холсте. Изображение аккуратно натянуто на деревянный подрамник.</div></details>
    </div>
</section>

@if($related->isNotEmpty())
<section class="related-section"><div class="catalog-heading"><h2>Похожие картины</h2>@if($primaryGroup)<a href="{{ route('posters.show', $primaryGroup) }}">Смотреть все →</a>@endif</div><div class="poster-grid related-grid">
@foreach($related as $item)<article class="poster-card"><a class="poster-card-image" href="{{ route('posters.show', $item) }}">@if($item->primary_image_url)<img src="{{ $item->primary_image_url }}" alt="{{ $item->title }}" loading="lazy">@endif</a><div><p>{{ $item->categories->take(3)->pluck('name')->join(' · ') ?: $item->category }}</p><h3><a href="{{ route('posters.show', $item) }}">{{ $item->title }}</a></h3></div></article>@endforeach
</div></section>
@endif
<script>
document.querySelectorAll('[name="poster-size"]').forEach((input) => input.addEventListener('change', () => {
    document.getElementById('poster-order').href = input.dataset.orderUrl;
    const wbButton = document.getElementById('wb-buy');
    wbButton.hidden = !input.dataset.wbUrl;
    wbButton.href = input.dataset.wbUrl || '#';
}));
</script>
@endsection
