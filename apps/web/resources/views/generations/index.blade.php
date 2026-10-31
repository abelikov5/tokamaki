@php
    $orderBasePrice = (int) config('orders.base_price');
    $orderDesignerPrice = (int) config('orders.designer_price');
    $formatOrderPrice = static fn (int $price): string => number_format($price, 0, ',', "\u{00A0}")."\u{00A0}₽";
@endphp
<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Создать картину по номерам по фото онлайн — Токамаки</title>
    <meta name="description" content="{{ $canCustomize
        ? 'Создайте картину по номерам по фото: настройте цвета и детализацию, отредактируйте схему и скачайте SVG или PDF.'
        : 'Бесплатный генератор картин по номерам по фото без регистрации, лимитов и ограничений. Создавайте сколько угодно макетов и скачивайте готовые PDF.' }}">
    @if ($canCustomize)
        <meta name="robots" content="noindex,nofollow">
    @endif
    <link rel="canonical" href="https://tokamaki.ru/kartina-po-nomeram-po-foto">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:site_name" content="Токамаки">
    <meta property="og:title" content="Картина по номерам по фото — онлайн-генератор">
    <meta property="og:description" content="{{ $canCustomize
        ? 'Загрузите фотографию, настройте палитру и получите готовую схему картины по номерам.'
        : 'Бесплатно создавайте картины по номерам из фотографий — без регистрации и ограничений на количество макетов.' }}">
    <meta property="og:url" content="https://tokamaki.ru/kartina-po-nomeram-po-foto">
    <meta property="og:image" content="https://tokamaki.ru/images/logo_tokamaki.webp">
    <meta name="theme-color" content="#0b1f3a">
    <link rel="icon" type="image/webp" href="{{ asset('images/logo_tokamaki.webp') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <script src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}" defer></script>
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "WebApplication",
            "name": "Генератор картин по номерам Токамаки",
            "url": "https://tokamaki.ru/kartina-po-nomeram-po-foto",
            "applicationCategory": "DesignApplication",
            "operatingSystem": "Web",
            "offers": {"@@type": "Offer", "price": "0", "priceCurrency": "RUB"},
            "description": "Бесплатный генератор картин по номерам по фото без регистрации, лимитов и ограничений на количество созданных макетов."
        }
    </script>
</head>
<body class="generator-page" data-resume-endpoint="{{ $resumeEndpoint }}" data-can-customize="{{ $canCustomize ? '1' : '0' }}" data-order-base-price="{{ $orderBasePrice }}" data-order-designer-price="{{ $orderDesignerPrice }}">
@include('partials.metrika')
<header class="brand-header">
    <a class="brand-mark" href="{{ route('home') }}" aria-label="Токамаки — на главную">
        <img class="brand-symbol" src="{{ asset('images/logo_tokamaki.webp') }}" alt="" width="268" height="212">
        <img class="brand-wordmark" src="{{ asset('images/tokamaki_label.webp') }}" alt="Токамаки" width="543" height="117">
    </a>
    <nav class="brand-nav" aria-label="Основная навигация">
        <a href="{{ route('home') }}#products">Продукция</a>
        <a href="{{ route('home') }}#personal">По фото</a>
        <a href="{{ route('home') }}#business">Для бизнеса</a>
        @auth
            <a href="{{ route('generator.editor') }}">Расширенный редактор</a>
        @endauth
        <a class="nav-action" href="{{ route('home') }}">На главную</a>
    </nav>
</header>
<main class="shell">
    <header class="hero generator-intro">
        <p class="eyebrow">Бесплатный Онлайн-генератор</p>
        <h1>Картина по номерам<br><span>из вашей фотографии</span></h1>
        <p>{{ $canCustomize
            ? 'Загрузите JPG или PNG, настройте палитру и детализацию. Сервис построит контуры, расставит номера и подготовит файлы для печати.'
            : 'Загрузите JPG или PNG — сервис автоматически подготовит изображение, построит контуры и создаст PDF с палитрой.' }}</p>
    </header>

    <section id="saved-projects" class="panel saved-projects" hidden>
        <div>
            <p class="eyebrow">Ваши проекты</p>
            <h2>Продолжить работу</h2>
            <p>Последние картины, сохранённые в этом браузере.</p>
        </div>
        <div id="saved-project-list" class="saved-project-list"></div>
    </section>
    <p id="resume-loading" class="resume-loading" hidden>Загружаем сохранённую картину…</p>

    <form id="generation-form" class="panel workspace" action="{{ route('generations.store') }}" method="post" enctype="multipart/form-data">
        @csrf
        <div class="source-column">
            <div id="upload-area" class="upload">
                <input id="image" name="image" type="file" accept="image/jpeg,image/png" required>
                <label id="upload-placeholder" for="image"><strong>Выберите фотографию</strong><small>JPG или PNG, до 20 МБ</small></label>
                <img id="source-preview" alt="Фотография, подготовленная в формате 40 на 50 сантиметров" hidden>
                <label id="change-photo" class="change-photo" for="image" hidden>Загрузить другую фотку</label>
                <div id="upload-comparison" class="upload-comparison" style="--comparison-position: 50%" hidden>
                    <div class="comparison-generated-layer">
                        <img id="comparison-generated" class="comparison-image" alt="Готовая схема с контурами и номерами">
                    </div>
                    <div class="comparison-divider" aria-hidden="true"><span>↔</span></div>
                    <input id="comparison-range" class="comparison-range" type="range" min="0" max="100" value="50" aria-label="Сравнить исходную фотографию и готовые контуры">
                </div>
                <canvas id="focus-canvas" class="focus-canvas" aria-label="Важные области фотографии" hidden></canvas>
                <span id="image-preparing" class="image-preparing" hidden>Подготавливаем фото…</span>
            </div>
            <input id="focus-regions" name="focus_regions" type="hidden" value="[]">

            <section id="format-controls" class="format-controls" aria-label="Подготовка формата фотографии" hidden>
                <div class="format-summary">
                    <strong>Формат 40 × 50 см</strong>
                    <small>Вертикальный 4:5 · файл для обработки 1600 × 2000 px</small>
                </div>
                <div class="fit-switch" role="group" aria-label="Способ размещения фотографии">
                    <button type="button" class="selected" data-fit-mode="crop" aria-pressed="true">Обрезать</button>
                    <button type="button" data-fit-mode="stretch" aria-pressed="false">Растянуть</button>
                </div>
                <p id="fit-description">Лишнее по краям обрезается автоматически, без искажения пропорций.</p>
                <div class="focus-controls">
                    <div>
                        <strong>Важные области</strong>
                        <small id="focus-status">Выделите глаза, губы или другие места, где важны детали и точный цвет.</small>
                    </div>
                    <button id="focus-mode" type="button" aria-pressed="false">Выделить области</button>
                    <button id="focus-undo" type="button" disabled>Убрать последнюю</button>
                    <button id="focus-clear" type="button" disabled>Очистить</button>
                </div>
            </section>
        </div>

        <section class="controls{{ $canCustomize ? '' : ' guest-controls' }}">
            @if ($canCustomize)
            <div class="control-row">
                <div class="color-control">
                    <label class="range" id="color-range">Количество цветов <output data-for="colors">22</output>
                        <input id="colors" name="colors" type="range" min="4" max="32" step="1" value="22">
                    </label>
                    <label class="auto-colors" for="auto-colors">
                        <input id="auto-colors" name="auto_colors" type="checkbox" value="1" checked>
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
            @else
                <div class="guest-settings">
                    <p class="eyebrow">Настройки макета</p>
                    <label class="range" id="color-range">Количество цветов <output data-for="colors">22</output>
                        <input id="colors" name="colors" type="range" min="4" max="32" step="1" value="22">
                    </label>
                    <label>Сложность
                        <select name="complexity">
                            <option value="detailed">Детальная</option>
                            <option value="balanced" selected>Сбалансированная</option>
                            <option value="simple">Простая</option>
                            <option value="minimal">Минимальная</option>
                        </select>
                    </label>
                </div>
                <input id="auto-colors" name="auto_colors" type="hidden" value="0">
                <input name="min_region_size" type="hidden" value="100">
                <input name="smoothing" type="hidden" value="2">
                <input name="min_region_width" type="hidden" value="4">
                <input name="contour_smoothing" type="hidden" value="2">
                <input name="line_separation" type="hidden" value="2">
                <input name="label_density" type="hidden" value="1">
            @endif

            <button id="submit-button" type="submit">{{ $canCustomize ? 'Создать картину' : 'Создать бесплатный макет' }}</button>
            <div id="generation-progress" class="generation-progress" role="progressbar" aria-label="Прогресс создания макета" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden>
                <div class="generation-progress-heading">
                    <strong>Создаём ваш макет</strong>
                    <span id="generation-progress-percent">0%</span>
                </div>
                <div class="generation-progress-track" aria-hidden="true">
                    <span id="generation-progress-fill"></span>
                </div>
                <small id="generation-progress-time">Прошло: 0 сек</small>
            </div>
            @if (! $canCustomize)
                <div id="guest-result-actions" class="guest-result-actions" hidden>
                    <p class="eyebrow">Ваш макет готов</p>
                    <h2>Что хотите сделать дальше?</h2>
                    <a id="free-pdf-button" class="result-action result-action-secondary" href="#">Скачать PDF бесплатно</a>
                    <section class="result-order-offer" aria-labelledby="result-order-title">
                        <p class="result-order-label">Полный комплект для рисования</p>
                        <h3 id="result-order-title">Картина по вашему фото — {{ $formatOrderPrice($orderBasePrice) }}</h3>
                        <ul>
                            <li><span aria-hidden="true">✓</span> Премиальный холст 40 × 50 см</li>
                            <li><span aria-hidden="true">✓</span> Акриловые краски и кисти</li>
                            <li><span aria-hidden="true">✓</span> Бесплатная доставка до ПВЗ Авито или СДЭК</li>
                        </ul>
                        <button class="order-button" type="button">Заказать полный набор за {{ $formatOrderPrice($orderBasePrice) }}</button>
                        <small>Менеджер уточнит удобный пункт выдачи после оформления.</small>
                    </section>
                </div>
            @endif
            <p id="error" class="error" role="alert" hidden></p>
        </section>
    </form>

    <section id="result" class="result" hidden>
        <div class="panel project-save-bar" @if (! $canCustomize) hidden @endif>
            <div>
                <strong id="project-name">Сохранённая картина</strong>
                <span id="project-save-status">Применённые изменения сохраняются автоматически.</span>
            </div>
            <button id="save-project" type="button">Сохранить проект</button>
        </div>
        <div id="stats" class="stats" @if (! $canCustomize) hidden @endif></div>
        <article class="panel editor-panel" @if (! $canCustomize) hidden @endif>
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
                            <button type="button" data-tool="select">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 3 12 9-6 1 4 7-3 1-4-7-4 4 1-15Z"/></svg>
                                Стрелка
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
                            <button type="button" data-tool="label">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11V7a3 3 0 0 1 6 0v4l3 3v2H6v-2l3-3Z"/><path d="M5 20h14M8 16v4M16 16v4"/></svg>
                                Номер
                            </button>
                            <button type="button" data-tool="unlabeled">
                                <svg class="tool-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12s3.5-6 9-6 9 6 9 6-3.5 6-9 6-9-6-9-6Z"/><path d="M9.5 9.5a3.5 3.5 0 1 0 5 5"/><path d="M16 4v4M14 6h4"/></svg>
                                Без номеров
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
                            <button id="zoom-fit" type="button" title="Разместить холст на всю высоту редактора">По высоте</button>
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
                        <button id="undo-change" type="button" disabled>Отменить последнее <kbd>Ctrl+Z</kbd></button>
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
        @if (! $canCustomize)
            <article class="panel order-card" id="order-card">
                <div class="order-copy">
                    <p class="eyebrow">Полный комплект для рисования</p>
                    <h2>Всё необходимое за {{ $formatOrderPrice($orderBasePrice) }}</h2>
                    <p class="kit-intro">Получите готовый набор по своей фотографии — ничего дополнительно покупать не понадобится.</p>
                    <ul class="kit-list">
                        <li><span aria-hidden="true">✓</span><div><strong>Премиальный холст 40 × 50 см</strong><small>С нанесёнными контурами и номерами</small></div></li>
                        <li><span aria-hidden="true">✓</span><div><strong>Набор акриловых красок</strong><small>Цвета подобраны под палитру вашей картины</small></div></li>
                        <li><span aria-hidden="true">✓</span><div><strong>3 кисти разного размера</strong><small>Для крупных областей и мелких деталей</small></div></li>
                        <li><span aria-hidden="true">✓</span><div><strong>Деревянный подрамник</strong><small>С художественной натяжкой — готов к рисованию</small></div></li>
                    </ul>
                    <p class="delivery-note"><strong>Бесплатная доставка</strong> до ПВЗ Авито или СДЭК включена в стоимость.</p>
                </div>
                <div class="order-options">
                    <label class="designer-option" for="designer-processing">
                        <input id="designer-processing" type="checkbox">
                        <span>
                            <strong>Обработка фотографии дизайнером</strong>
                            <small>Проверим детали, цвета и подготовим макет вручную</small>
                        </span>
                        <b>+{{ $formatOrderPrice($orderDesignerPrice) }}</b>
                    </label>
                    <div class="order-total"><span>Итого</span><strong id="order-total">{{ $formatOrderPrice($orderBasePrice) }}</strong></div>
                    <button class="order-button" type="button">Заказать полный набор за {{ $formatOrderPrice($orderBasePrice) }}</button>
                    <small>Укажите город и улицу — менеджер уточнит удобный ПВЗ после оформления.</small>
                </div>
            </article>

            <dialog id="order-dialog" class="order-dialog" aria-labelledby="order-dialog-title">
                <div class="order-dialog-card">
                    <button class="order-dialog-close" type="button" data-order-dialog-close aria-label="Закрыть">×</button>
                    <div id="order-form-panel">
                        <p class="eyebrow">Оформление заказа</p>
                        <h2 id="order-dialog-title">Куда доставить набор?</h2>
                        <p>Доставка до ПВЗ Авито или СДЭК бесплатная. Менеджер уточнит удобный пункт выдачи по электронной почте.</p>
                        <form id="order-form" class="order-form">
                            <label>
                                <span>Email</span>
                                <input id="order-email" name="email" type="email" autocomplete="email" required maxlength="254" placeholder="name@example.ru">
                            </label>
                            <label>
                                <span>Город</span>
                                <input id="order-city" name="city" type="text" autocomplete="address-level2" required maxlength="120" placeholder="Москва">
                            </label>
                            <label class="order-street-field">
                                <span>Улица</span>
                                <input id="order-street" name="street" type="text" autocomplete="address-line1" required maxlength="180" placeholder="ул. Соборная">
                            </label>
                            <label class="designer-option order-dialog-designer-option" for="order-dialog-designer-processing">
                                <input id="order-dialog-designer-processing" type="checkbox">
                                <span>
                                    <strong>Обработка дизайнером</strong>
                                    <small>Проверим детали, цвета и подготовим макет вручную</small>
                                </span>
                                <b>+{{ $formatOrderPrice($orderDesignerPrice) }}</b>
                            </label>
                            <div class="order-dialog-summary">
                                <span>Полный набор 40 × 50 см</span>
                                <strong id="order-dialog-total">{{ $formatOrderPrice($orderBasePrice) }}</strong>
                            </div>
                            <p id="order-form-error" class="order-form-error" role="alert" hidden></p>
                            <button id="order-submit" class="order-submit" type="submit">Оформить заказ</button>
                            <small>Нажимая кнопку, вы соглашаетесь с <a href="{{ route('privacy') }}" target="_blank">политикой конфиденциальности</a>.</small>
                        </form>
                    </div>
                    <div id="order-success" class="order-success" hidden>
                        <span aria-hidden="true">✓</span>
                        <h2>Заказ принят</h2>
                        <p id="order-success-message"></p>
                        <button type="button" data-order-dialog-close>Закрыть</button>
                    </div>
                </div>
            </dialog>
        @endif
    </section>

    @if (! $canCustomize)
        <section class="customer-reviews" aria-labelledby="customer-reviews-title">
            <header class="customer-reviews-heading">
                <div>
                    <p class="eyebrow">Истории наших клиентов</p>
                    <h2 id="customer-reviews-title">Картины по номерам по фото, которые становятся личными подарками</h2>
                </div>
                <p>Семейный снимок, любимый персонаж или дорогой сердцу сад — фотографию можно превратить в картину, которую приятно раскрашивать и хранить.</p>
            </header>

            <div class="customer-reviews-grid">
                <article class="customer-review-card">
                    <img src="{{ asset('images/reviews/child-ladybug.webp') }}"
                         alt="Девочка держит картину по номерам с любимым персонажем Леди Баг"
                         width="1536"
                         height="1024"
                         loading="lazy">
                    <div class="customer-review-copy">
                        <p class="review-theme">Для ребёнка</p>
                        <h3>Картина по номерам с любимым персонажем</h3>
                        <blockquote>«Дочка обожает Леди Баг, поэтому заказали картину по её фотографии вместе с любимой героиней. Она сразу начала раскрашивать — получилось настоящее приключение, а не просто подарок».</blockquote>
                        <p class="review-author">Марина, мама Софии · Москва</p>
                    </div>
                </article>

                <article class="customer-review-card">
                    <img src="{{ asset('images/reviews/couple-gift.webp') }}"
                         alt="Пара держит готовую картину по номерам по совместной фотографии"
                         width="1536"
                         height="1024"
                         loading="lazy">
                    <div class="customer-review-copy">
                        <p class="review-theme">Для второй половинки</p>
                        <h3>Подарок парню или девушке по общей фотографии</h3>
                        <blockquote>«Сделал картину по нашему фото на годовщину. Девушка узнала снимок ещё до того, как закончила раскрашивать. Теперь готовая картина висит в спальне и напоминает о нашей поездке».</blockquote>
                        <p class="review-author">Артём · Санкт-Петербург</p>
                    </div>
                </article>

                <article class="customer-review-card">
                    <img src="{{ asset('images/reviews/grandmother-garden.webp') }}"
                         alt="Бабушка держит картину по номерам с изображением своего сада"
                         width="1536"
                         height="1024"
                         loading="lazy">
                    <div class="customer-review-copy">
                        <p class="review-theme">Для бабушки</p>
                        <h3>Памятная картина с любимым садом</h3>
                        <blockquote>«Перенесли на холст фотографию бабушкиного сада, который она выращивает много лет. Она растрогалась, а потом раскрашивала картину вечерами. Очень личный подарок, который хранит воспоминания о доме».</blockquote>
                        <p class="review-author">Елена · Казань</p>
                    </div>
                </article>
            </div>
        </section>
    @endif

    <section class="seo-content" aria-label="О генераторе картин по номерам">
        <article class="seo-card">
            <h2>Как сделать картину по номерам из фото</h2>
            @if (! $canCustomize)
                <p>Генератор полностью бесплатный и работает без регистрации. Создавайте сколько угодно картин — без лимитов и ограничений на количество макетов.</p>
            @endif
            <ol>
                <li>Выберите чёткую фотографию в формате JPG или PNG.</li>
                @if ($canCustomize)
                    <li>Настройте количество цветов, сглаживание и сложность областей.</li>
                    <li>Создайте схему, при необходимости поправьте её кистью и скачайте SVG или PDF.</li>
                @else
                    <li>Нажмите «Создать бесплатный макет» и дождитесь готового результата.</li>
                    <li>Сравните контуры с исходником, скачайте PDF или закажите готовый набор.</li>
                @endif
            </ol>
        </article>
        <article class="seo-card">
            <h2>Какая фотография подойдёт</h2>
            <p>Лучше всего работают снимки с хорошим освещением, заметным главным объектом и различимыми цветовыми областями. Для портретов выбирайте кадр без сильных теней, а для пейзажей — изображение с понятным передним и задним планом.</p>
        </article>
        <article class="seo-card">
            <h2>{{ $canCustomize ? 'Что можно настроить' : 'Готовый PDF с палитрой' }}</h2>
            <p>{{ $canCustomize
                ? 'Управляйте числом цветов, размером мелких участков, плавностью контуров и плотностью номеров. Встроенный редактор позволяет локально менять цвет, стирать и сглаживать области.'
                : 'В PDF находятся контуры с номерами и палитра использованных цветов. Файл можно бесплатно скачать сразу после создания макета.' }}</p>
        </article>
        <article class="seo-card">
            @if ($canCustomize)
                <h2>Файлы для дальнейшей работы</h2>
                <p>После обработки доступны цветное превью, векторная схема SVG, PDF для печати и палитра. Сохраните результат, чтобы подготовить собственный творческий проект.</p>
            @else
                <h2>Готовый набор на подрамнике</h2>
                <p>Можно заказать картину 40 × 50 см на подрамнике с нанесёнными контурами, красками и бесплатной доставкой до ПВЗ Авито или СДЭК. Стоимость набора — {{ $formatOrderPrice($orderBasePrice) }}.</p>
            @endif
        </article>
    </section>
</main>
<footer class="generator-footer"><span>© {{ date('Y') }} Токамаки</span><nav><a href="{{ route('privacy') }}">Конфиденциальность</a><a href="{{ route('home') }}">Товары для творчества →</a></nav></footer>
</body>
</html>
