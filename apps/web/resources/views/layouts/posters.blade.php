<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Картины на холсте — Токамаки')</title>
    <meta name="description" content="@yield('description', 'Готовые интерьерные картины и картины по вашему фото: печать на холсте с деревянным подрамником.')">
    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <link rel="icon" type="image/webp" href="{{ asset('images/logo_tokamaki.webp') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <link rel="stylesheet" href="{{ asset('css/posters.css') }}">
    @yield('head')
</head>
<body class="poster-page">
@include('partials.metrika')
<header class="poster-header">
    <a class="poster-logo" href="{{ route('home') }}" aria-label="Токамаки — на главную">
        <img src="{{ asset('images/logo_tokamaki.webp') }}" alt="" width="52" height="42">
        <img src="{{ asset('images/tokamaki_label.webp') }}" alt="Токамаки" width="150" height="33">
    </a>
    <nav aria-label="Каталог картин на холсте">
        <a href="{{ route('posters.index') }}">Готовые картины</a>
        <a href="{{ route('posters.custom') }}">Картина по фото</a>
        <a href="{{ route('home') }}">О бренде</a>
    </nav>
</header>
<main>@yield('content')</main>
<footer class="poster-footer">
    <p>Токамаки — интерьерные картины и персональная печать на холсте.</p>
    <nav><a href="{{ route('posters.index') }}">Каталог</a><a href="{{ route('posters.custom') }}">По своему фото</a><a href="{{ route('privacy') }}">Конфиденциальность</a></nav>
</footer>
</body>
</html>
