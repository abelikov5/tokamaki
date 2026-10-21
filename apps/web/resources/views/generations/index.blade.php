<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Картина по номерам</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
</head>
<body>
<main class="shell">
    <header class="hero">
        <p class="eyebrow">Технический прототип</p>
        <h1>Картина по номерам<br><span>из вашей фотографии</span></h1>
        <p>Laravel управляет интерфейсом и заданиями, отдельный Python-сервис строит палитру, контуры, номера и PDF.</p>
    </header>

    <form id="generation-form" class="panel workspace" action="{{ route('generations.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <label class="upload" for="image">
            <input id="image" name="image" type="file" accept="image/jpeg,image/png" required>
            <span id="upload-placeholder"><strong>Выберите фотографию</strong><small>JPG или PNG, до 20 МБ</small></span>
            <img id="source-preview" alt="Исходная фотография" hidden>
        </label>

        <section class="controls">
            <div class="control-row">
                <label>Количество цветов
                    <select name="colors">
                        <option>16</option><option selected>24</option><option>36</option><option>48</option>
                    </select>
                </label>
                <label>Сложность областей
                    <select name="complexity">
                        <option value="detailed">Детальная</option>
                        <option value="balanced" selected>Сбалансированная</option>
                        <option value="simple">Простая</option>
                        <option value="minimal">Минимальная</option>
                    </select>
                </label>
            </div>

            <label class="range">Минимальный участок <output data-for="min_region_size">100 px</output>
                <input name="min_region_size" type="range" min="10" max="500" step="10" value="100" data-unit=" px">
            </label>
            <label class="range">Сглаживание фотографии <output data-for="smoothing">2</output>
                <input name="smoothing" type="range" min="0" max="5" step="0.5" value="2">
            </label>
            <label class="range">Минимальная толщина области <output data-for="min_region_width">4 px</output>
                <input name="min_region_width" type="range" min="0" max="12" step="0.5" value="4" data-unit=" px">
            </label>
            <label class="range">Сглаживание контуров <output data-for="contour_smoothing">2</output>
                <input name="contour_smoothing" type="range" min="0.5" max="6" step="0.5" value="2">
            </label>
            <label class="range">Расстояние между линиями <output data-for="line_separation">2 px</output>
                <input name="line_separation" type="range" min="0" max="8" step="0.5" value="2" data-unit=" px">
            </label>
            <label class="range">Плотность номеров <output data-for="label_density">1×</output>
                <input name="label_density" type="range" min="0.25" max="3" step="0.25" value="1" data-unit="×">
            </label>

            <button id="submit-button" type="submit">Создать картину</button>
            <p id="error" class="error" role="alert" hidden></p>
        </section>
    </form>

    <section id="result" class="result" hidden>
        <div id="stats" class="stats"></div>
        <div class="visuals">
            <article class="panel"><h2>Цветное превью</h2><img id="result-preview" alt="Цветное превью"></article>
            <article class="panel"><h2>Схема с номерами</h2><img id="result-scheme" alt="Схема с номерами"></article>
        </div>
        <nav id="downloads" class="downloads" aria-label="Скачать результаты"></nav>
        <article class="panel palette-panel"><h2>Палитра</h2><div id="palette" class="palette"></div></article>
    </section>
</main>
</body>
</html>
