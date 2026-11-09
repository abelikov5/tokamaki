@extends('layouts.posters')

@section('title', 'Картины на холсте купить — готовые 30×40 и 40×50 | Токамаки')
@section('description', 'Купить готовые интерьерные картины на холсте с деревянным подрамником. Размеры 30×40 и 40×50 см, подборки по сюжету, стилю и комнате.')
@section('robots', request()->hasAny(['q', 'category', 'orientation', 'size', 'sort', 'page']) ? 'noindex,follow' : 'index,follow')

@php
    $featuredCategories = $categories
        ->whereIn('type', ['room', 'style', 'theme'])
        ->sortBy([['type', 'asc'], ['sort_order', 'asc']])
        ->take(10);
    $activeCategory = filled($filters['category'] ?? null)
        ? $categories->firstWhere('slug', $filters['category'])
        : null;
    $activeFilters = collect([
        'q' => filled($filters['q'] ?? null) ? 'Поиск: «'.$filters['q'].'»' : null,
        'category' => $activeCategory?->name,
        'size' => match ($filters['size'] ?? null) {
            '30x40' => '30×40 см',
            '40x50' => '40×50 см',
            default => null,
        },
        'orientation' => match ($filters['orientation'] ?? null) {
            'vertical' => 'Вертикальные',
            'horizontal' => 'Горизонтальные',
            default => null,
        },
        'sort' => match ($filters['sort'] ?? null) {
            'price_asc' => 'Сначала дешевле',
            'price_desc' => 'Сначала дороже',
            default => null,
        },
    ])->filter();
    $faqItems = [
        [
            'question' => 'На чём напечатаны картины?',
            'answer' => 'Изображение печатается на холсте, который аккуратно натягивается на деревянный подрамник. Картина готова к размещению в интерьере.',
        ],
        [
            'question' => 'Какие размеры доступны?',
            'answer' => 'В каталоге представлены картины размером 30×40 и 40×50 см. Доступность конкретного варианта можно проверить на странице выбранной картины.',
        ],
        [
            'question' => 'Как подобрать картину к комнате?',
            'answer' => 'Используйте подборки по комнате, стилю и сюжету, а затем уточните размер и ориентацию с помощью фильтров каталога.',
        ],
        [
            'question' => 'Можно ли заказать картину со своим изображением?',
            'answer' => 'Да. Перейдите в раздел «Картина по своему фото», загрузите изображение, выберите размер и оформите заказ.',
        ],
    ];
    $schema = [
        '@context' => 'https://schema.org',
        '@graph' => [
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Главная', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Картины на холсте', 'item' => route('posters.index')],
                ],
            ],
            [
                '@type' => 'ItemList',
                'name' => 'Готовые картины на холсте',
                'numberOfItems' => $posters->total(),
                'itemListElement' => $posters->values()->map(fn ($poster, $index) => array_filter([
                    '@type' => 'ListItem',
                    'position' => ($posters->firstItem() ?? 1) + $index,
                    'url' => route('posters.show', $poster),
                    'name' => $poster->title,
                    'image' => $poster->primary_image_url,
                ]))->all(),
            ],
            [
                '@type' => 'FAQPage',
                'mainEntity' => collect($faqItems)->map(fn ($item) => [
                    '@type' => 'Question',
                    'name' => $item['question'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
                ])->all(),
            ],
        ],
    ];
@endphp

@section('head')
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}</script>
@endsection

@section('content')
<section class="catalog-hero">
    <div>
        <p class="poster-kicker">Готовы украсить стену</p>
        <h1>Готовые картины на холсте<br><em>30×40 и 40×50</em></h1>
        <p>Выбирайте работу, которая соберёт интерьер, или создайте свою. Холст уже натянут на деревянный подрамник — останется только повесить.</p>
    </div>
    <a class="custom-poster-card" href="{{ route('posters.custom') }}">
        <span>Ваше изображение</span>
        <strong>Картина<br>по своему фото</strong>
        <i>Создать →</i>
    </a>
</section>

@if($featuredCategories->isNotEmpty())
<nav class="catalog-collections" aria-labelledby="catalog-collections-title">
    <div>
        <p class="poster-kicker">Быстрый выбор</p>
        <h2 id="catalog-collections-title">Популярные подборки</h2>
    </div>
    <div>
        @foreach($featuredCategories as $category)
            <a href="{{ route('posters.show', $category) }}">{{ $category->name }} <span>{{ $category->total }}</span></a>
        @endforeach
    </div>
</nav>
@endif

<section class="catalog-shell">
    <form id="catalog-tools" class="catalog-tools" method="get" action="{{ route('posters.index') }}">
        <label class="catalog-search">
            <span class="sr-only">Поиск картин</span>
            <input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Например, бежевая картина с горами">
            <button type="submit">Найти</button>
        </label>
        <div class="catalog-filters">
            <select name="category" aria-label="Категория" data-live-filter>
                <option value="">Все группы</option>
                @foreach($categories->groupBy('type') as $type => $group)
                    <optgroup label="{{ \App\Models\PosterCategory::typeOptions()[$type] ?? 'Другие' }}">
                        @foreach($group as $category)
                            <option value="{{ $category->slug }}" @selected(($filters['category'] ?? '') === $category->slug)>{{ $category->name }} · {{ $category->total }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <select name="size" aria-label="Размер" data-live-filter>
                <option value="">Любой размер</option>
                <option value="30x40" @selected(($filters['size'] ?? '') === '30x40')>30×40 см</option>
                <option value="40x50" @selected(($filters['size'] ?? '') === '40x50')>40×50 см</option>
            </select>
            <select name="orientation" aria-label="Ориентация" data-live-filter>
                <option value="">Любая ориентация</option>
                <option value="vertical" @selected(($filters['orientation'] ?? '') === 'vertical')>Вертикальные</option>
                <option value="horizontal" @selected(($filters['orientation'] ?? '') === 'horizontal')>Горизонтальные</option>
            </select>
            <select name="sort" aria-label="Сортировка" data-live-filter>
                <option value="new">Сначала новые</option>
                <option value="price_asc" @selected(($filters['sort'] ?? '') === 'price_asc')>Сначала дешевле</option>
                <option value="price_desc" @selected(($filters['sort'] ?? '') === 'price_desc')>Сначала дороже</option>
            </select>
            <button class="catalog-apply" type="submit">Применить</button>
            @if($filters !== [])<a href="{{ route('posters.index') }}">Сбросить всё</a>@endif
        </div>
        @if($activeFilters->isNotEmpty())
            <div class="catalog-active-filters" aria-label="Активные фильтры">
                <span>Выбрано:</span>
                @foreach($activeFilters as $key => $label)
                    <a href="{{ route('posters.index', request()->except($key, 'page')) }}" aria-label="Убрать фильтр {{ $label }}">{{ $label }} <b aria-hidden="true">×</b></a>
                @endforeach
            </div>
        @endif
    </form>

    <div class="catalog-trust" aria-label="Преимущества картин Токамаки">
        <div><b>01</b><span><strong>Печать на холсте</strong><small>Интерьерная печать с аккуратной цветопередачей</small></span></div>
        <div><b>02</b><span><strong>Деревянный подрамник</strong><small>Холст уже натянут и готов к размещению</small></span></div>
        <div><b>03</b><span><strong>Бесплатная доставка</strong><small>До ПВЗ СДЭК или Авито</small></span></div>
    </div>

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

<section class="catalog-guide">
    <div class="catalog-guide-intro">
        <p class="poster-kicker">Помогаем выбрать</p>
        <h2>Картина, которая подходит именно вашему интерьеру</h2>
        <p>Готовая картина на холсте — простой способ добавить комнате цвет, глубину и законченный акцент без сложного декора. В каталоге Токамаки собраны работы для гостиной, спальни, детской, кухни и кабинета: от спокойной абстракции и минимализма до ярких сюжетов, природы и животных.</p>
        <p>Все изображения напечатаны на холсте и натянуты на деревянный подрамник. Вам не нужно отдельно искать основу или оформлять работу в багет: выберите подходящий сюжет, размер и ориентацию, а готовую картину останется разместить на стене.</p>
    </div>
    <div class="catalog-guide-grid">
        <article><h3>Как выбрать размер</h3><p>Формат 30×40 см хорошо подходит для небольшой стены, ниши или композиции из нескольких работ. Картина 40×50 см заметнее в интерьере и может стать самостоятельным акцентом над комодом, рабочим столом или прикроватной зоной.</p></article>
        <article><h3>Как подобрать сюжет</h3><p>Ориентируйтесь на настроение комнаты и уже используемые оттенки. Спокойные природные и абстрактные сюжеты поддержат спальню, выразительная графика оживит кабинет, а яркая работа станет центром нейтральной гостиной.</p></article>
        <article><h3>Как сочетать с интерьером</h3><p>Картине необязательно точно повторять цвет стен или мебели. Достаточно, чтобы один-два оттенка перекликались с текстилем, декором или отделкой. Для быстрого поиска используйте подборки по комнате, стилю и теме.</p></article>
    </div>
</section>

<section class="catalog-faq">
    <div><p class="poster-kicker">Коротко о главном</p><h2>Частые вопросы о картинах на холсте</h2></div>
    <div class="catalog-faq-list">
        @foreach($faqItems as $item)
            <details><summary>{{ $item['question'] }}</summary><p>{{ $item['answer'] }}</p></details>
        @endforeach
    </div>
</section>

<script>
const catalogTools = document.getElementById('catalog-tools');

if (catalogTools) {
    catalogTools.classList.add('filters-live');
    catalogTools.querySelectorAll('[data-live-filter]').forEach((filter) => {
        filter.addEventListener('change', () => catalogTools.requestSubmit());
    });
}
</script>
@endsection
