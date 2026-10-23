<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Создать картину по номерам по фото онлайн — Токамаки</title>
    <meta name="description" content="Бесплатный онлайн-генератор картины по номерам из фотографии. Настройте число цветов и детализацию, отредактируйте схему и скачайте SVG или PDF.">
    <link rel="canonical" href="https://tokamaki.ru/kartina-po-nomeram-po-foto">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:site_name" content="Токамаки">
    <meta property="og:title" content="Картина по номерам по фото — онлайн-генератор">
    <meta property="og:description" content="Загрузите фотографию, настройте палитру и получите готовую схему картины по номерам.">
    <meta property="og:url" content="https://tokamaki.ru/kartina-po-nomeram-po-foto">
    <meta property="og:image" content="https://tokamaki.ru/images/tokamaki-logo.png">
    <meta name="theme-color" content="#0b1f3a">
    <link rel="icon" type="image/png" href="{{ asset('images/tokamaki-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <script src="{{ asset('js/app.js') }}" defer></script>
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebApplication",
            "name": "Генератор картин по номерам Токамаки",
            "url": "https://tokamaki.ru/kartina-po-nomeram-po-foto",
            "applicationCategory": "DesignApplication",
            "operatingSystem": "Web",
            "offers": {"@@type": "Offer", "price": "0", "priceCurrency": "RUB"},
            "description": "Онлайн-сервис для создания схемы картины по номерам из фотографии."
        }
    </script>
</head>
<body class="generator-page">
<header class="brand-header">
    <a class="brand-mark" href="{{ route('home') }}" aria-label="Токамаки — на главную">
        <img src="{{ asset('images/tokamaki-logo.png') }}" alt="Токамаки" width="56" height="56">
        <span>Токамаки<small>изо студио</small></span>
    </a>
    <nav class="brand-nav" aria-label="Основная навигация">
        <a href="{{ route('home') }}#products">Продукция</a>
        <a href="{{ route('home') }}#personal">По фото</a>
        <a href="{{ route('home') }}#business">Для бизнеса</a>
        <a class="nav-action" href="{{ route('home') }}">На главную</a>
    </nav>
</header>
<main class="shell">
    <header class="hero generator-intro">
        <p class="eyebrow">Онлайн-генератор Токамаки</p>
        <h1>Картина по номерам<br><span>из вашей фотографии</span></h1>
        <p>Загрузите JPG или PNG, подберите палитру и детализацию. Сервис построит контуры, расставит номера и подготовит файлы для печати.</p>
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
                <div class="color-control">
                    <label class="range" id="color-range">Количество цветов <output data-for="colors">24</output>
                        <input id="colors" name="colors" type="range" min="4" max="48" step="1" value="24">
                    </label>
                    <label class="auto-colors" for="auto-colors">
                        <input id="auto-colors" name="auto_colors" type="checkbox" value="1">
                        <span aria-hidden="true"></span>
                        <strong>Авто</strong>
                        <small>Определить число заметных цветов по изображению</small>
                    </label>
                </div>
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
        <article class="panel editor-panel">
            <div class="editor-heading">
                <div>
                    <p class="eyebrow">Редактор картины</p>
                    <h2>Локальная правка контуров</h2>
                </div>
                <p>Нанесите несколько правок, затем перестройте контуры и номера одной кнопкой или клавишей Enter.</p>
            </div>
            <div class="editor-workspace">
                <aside class="toolbox" aria-label="Инструменты редактора">
                    <div class="tool-group">
                        <strong>Вид</strong>
                        <div class="segmented">
                            <button type="button" data-view="scheme" class="selected">Контуры</button>
                            <button type="button" data-view="color">Цвет</button>
                        </div>
                    </div>
                    <div class="tool-group">
                        <strong>Инструмент</strong>
                        <div class="tool-grid">
                            <button type="button" data-tool="pan" class="selected">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 11V7a2 2 0 0 1 4 0v3-5a2 2 0 0 1 4 0v5-3a2 2 0 0 1 4 0v7c0 5-3 8-8 8-3 0-5-2-7-5l-2-3a2 2 0 0 1 3-2l2 2v-3Z"/></svg>
                                Рука
                            </button>
                            <button type="button" data-tool="eyedropper">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m19 3 2 2-9.5 9.5-4 1 1-4L18 2l1 1Z"/><path d="m14 6 4 4M7.5 15.5 4 19v2h2l3.5-3.5"/></svg>
                                Пипетка
                            </button>
                            <button type="button" data-tool="brush">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m14 15 7-7-5-5-7 7"/><path d="M14 15c-1 5-4 7-9 6 2-1 1-3 2-5 1-3 4-4 7-1Z"/></svg>
                                Кисть
                            </button>
                            <button type="button" data-tool="eraser">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m3 15 9-10a2 2 0 0 1 3 0l5 5a2 2 0 0 1 0 3l-7 8H7l-4-4a2 2 0 0 1 0-2Z"/><path d="m8 10 7 7M13 21h8"/></svg>
                                Ластик
                            </button>
                            <button type="button" data-tool="smooth">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 15c3-8 6 8 9 0s6 8 9 0"/><path d="M4 20h16"/></svg>
                                Сгладить
                            </button>
                            <button type="button" data-tool="magic">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m4 20 10-10"/><path d="m12 8 4 4"/><path d="M18 2v4M16 4h4M7 3v3M5.5 4.5h3M20 15v3M18.5 16.5h3"/></svg>
                                Волшебная кисть
                            </button>
                        </div>
                    </div>
                    <div id="eraser-options" class="tool-group" hidden>
                        <strong>Режим ластика</strong>
                        <div class="segmented eraser-modes">
                            <button type="button" data-eraser-mode="color" class="selected">По цвету</button>
                            <button type="button" data-eraser-mode="local">Обычный</button>
                        </div>
                        <small id="eraser-mode-help">Удаляет весь сегмент выбранного цвета.</small>
                    </div>
                    <div class="tool-group">
                        <strong>Масштаб</strong>
                        <div class="zoom-controls">
                            <button id="zoom-out" type="button" title="Уменьшить">−</button>
                            <output id="zoom-value">100%</output>
                            <button id="zoom-in" type="button" title="Увеличить">+</button>
                            <button id="zoom-fit" type="button">Вписать</button>
                        </div>
                    </div>
                    <label class="range tool-size">Размер инструмента <output data-for="brush-size">12 px</output>
                        <input id="brush-size" type="range" min="2" max="60" step="1" value="12" data-unit=" px">
                    </label>
                    <div id="brush-colors" class="tool-group">
                        <strong>Выбранный цвет</strong>
                        <div id="editor-palette" class="editor-palette"></div>
                    </div>
                    <p id="tool-help" class="tool-help">Перетаскивайте холст мышью. Масштаб также меняется колёсиком с Ctrl.</p>
                    <div class="edit-actions">
                        <button id="apply-edits" type="button" disabled>Применить изменения <kbd>Enter</kbd></button>
                        <button id="discard-edits" type="button" disabled>Отменить правки</button>
                    </div>
                    <span id="editor-status"></span>
                </aside>
                <div id="editor-stage" class="editor-stage">
                    <canvas id="editor-canvas" aria-label="Редактируемая схема"></canvas>
                    <div id="editor-loader" hidden>Перестраиваем схему…</div>
                </div>
            </div>
        </article>
        <nav id="downloads" class="downloads" aria-label="Скачать результаты"></nav>
        <article class="panel palette-panel"><h2>Палитра</h2><div id="palette" class="palette"></div></article>
    </section>

    <section class="seo-content" aria-label="О генераторе картин по номерам">
        <article class="seo-card">
            <h2>Как сделать картину по номерам из фото</h2>
            <ol>
                <li>Выберите чёткую фотографию в формате JPG или PNG.</li>
                <li>Настройте количество цветов, сглаживание и сложность областей.</li>
                <li>Создайте схему, при необходимости поправьте её кистью и скачайте SVG или PDF.</li>
            </ol>
        </article>
        <article class="seo-card">
            <h2>Какая фотография подойдёт</h2>
            <p>Лучше всего работают снимки с хорошим освещением, заметным главным объектом и различимыми цветовыми областями. Для портретов выбирайте кадр без сильных теней, а для пейзажей — изображение с понятным передним и задним планом.</p>
        </article>
        <article class="seo-card">
            <h2>Что можно настроить</h2>
            <p>Управляйте числом цветов, размером мелких участков, плавностью контуров и плотностью номеров. Встроенный редактор позволяет локально менять цвет, стирать и сглаживать области.</p>
        </article>
        <article class="seo-card">
            <h2>Файлы для дальнейшей работы</h2>
            <p>После обработки доступны цветное превью, векторная схема SVG, PDF для печати и палитра. Сохраните результат, чтобы подготовить собственный творческий проект.</p>
        </article>
    </section>
</main>
<footer class="generator-footer"><span>© {{ date('Y') }} Токамаки</span><a href="{{ route('home') }}">Товары для творчества →</a></footer>
</body>
</html>
