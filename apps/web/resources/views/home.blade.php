<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Токамаки — товары для творчества и печать на холсте</title>
    <meta name="description" content="Токамаки создаёт картины по номерам, алмазную мозаику, принты на кружках и печатает изображения на холсте. Производство продукции по фото и под брендом заказчика.">
    <link rel="canonical" href="https://tokamaki.ru/">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:site_name" content="Токамаки">
    <meta property="og:title" content="Токамаки — идеи, которые хочется создавать">
    <meta property="og:description" content="Товары для творчества, персональные наборы по фото и производство под брендом заказчика.">
    <meta property="og:url" content="https://tokamaki.ru/">
    <meta property="og:image" content="https://tokamaki.ru/images/tokamaki-logo.png">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0b1f3a">
    <link rel="icon" type="image/png" href="{{ asset('images/tokamaki-logo.png') }}">
    <link rel="stylesheet" href="{{ asset('css/site.css') }}">
    <script type="application/ld+json">
        {
            "@@context": "https://schema.org",
            "@@type": "Organization",
            "name": "Токамаки",
            "url": "https://tokamaki.ru/",
            "logo": "https://tokamaki.ru/images/tokamaki-logo.png",
            "description": "Производство товаров для творчества, печать на холсте и персональная продукция по фото."
        }
    </script>
</head>
<body class="brand-page">
<header class="brand-header">
    <a class="brand-mark" href="{{ route('home') }}" aria-label="Токамаки — на главную">
        <img src="{{ asset('images/tokamaki-logo.png') }}" alt="Токамаки" width="56" height="56">
        <span>Токамаки<small>изо студио</small></span>
    </a>
    <nav class="brand-nav" aria-label="Основная навигация">
        <a href="#products">Продукция</a>
        <a href="#personal">По фото</a>
        <a href="#business">Для бизнеса</a>
        <a class="nav-action" href="{{ route('generator') }}">Создать макет</a>
    </nav>
</header>

<main>
    <section class="home-hero">
        <div class="hero-copy">
            <p class="kicker"><span></span> Товары для творчества</p>
            <h1>Идеи, которые<br><em>хочется создавать</em></h1>
            <p class="hero-lead">Печатаем на холсте, выпускаем картины по номерам, алмазную мозаику и принты на кружках — серийно, по фотографии или под вашим брендом.</p>
            <div class="hero-actions">
                <a class="button button-primary" href="#products">Смотреть направления</a>
                <a class="button button-quiet" href="{{ route('generator') }}">Картина по фото <span>↗</span></a>
            </div>
            <ul class="hero-notes" aria-label="Ключевые возможности">
                <li><b>01</b> Собственное производство</li>
                <li><b>02</b> Персональные макеты</li>
                <li><b>03</b> Выпуск под СТМ</li>
            </ul>
        </div>
        <div class="hero-gallery" aria-label="Примеры изображений для творчества">
            <figure class="gallery-main"><img src="{{ asset('images/beach.jpg') }}" alt="Яркая иллюстрация пляжа" width="900" height="1261"></figure>
            <figure class="gallery-top"><img src="{{ asset('images/elephant.jpg') }}" alt="Поп-арт иллюстрация слона" width="900" height="1273"></figure>
            <figure class="gallery-bottom"><img src="{{ asset('images/ribbons.jpg') }}" alt="Абстрактные цветные линии" width="900" height="1259"></figure>
            <div class="paint-orbit" aria-hidden="true"><i></i><i></i><i></i></div>
            <div class="gallery-stamp">Творчество<br><strong>без границ</strong></div>
        </div>
    </section>

    <section class="marquee" aria-label="Направления производства">
        <div>Картины по номерам <span>✦</span> Печать на холсте <span>✦</span> Алмазная мозаика <span>✦</span> Принты на кружках <span>✦</span></div>
    </section>

    <section id="products" class="section products-section">
        <div class="section-heading">
            <div><p class="kicker"><span></span> Что мы создаём</p><h2>Для вдохновения,<br>дома и подарков</h2></div>
            <p>От готовой творческой идеи до продукта, который приятно держать в руках. Работаем с индивидуальными и серийными заказами.</p>
        </div>
        <div class="product-grid">
            <article class="product-card product-card-large">
                <img src="{{ asset('images/raccoon.jpg') }}" alt="Изображение енота для картины по номерам" loading="lazy" width="900" height="1260">
                <div class="product-copy"><span>01</span><h3>Картины<br>по номерам</h3><p>Сюжеты для спокойного творчества: холст с разметкой, понятная палитра и удовольствие от процесса.</p></div>
            </article>
            <article class="product-card product-card-blue">
                <div class="canvas-demo" aria-hidden="true"><span>1</span><span>2</span><span>3</span><span>4</span></div>
                <div class="product-copy"><span>02</span><h3>Печать<br>на холсте</h3><p>Фотографии, иллюстрации и интерьерные принты на фактурной основе.</p></div>
            </article>
            <article class="product-card product-card-orange">
                <div class="diamond-demo" aria-hidden="true"></div>
                <div class="product-copy"><span>03</span><h3>Алмазная<br>мозаика</h3><p>Яркие наборы с аккуратной схемой для медитативной выкладки.</p></div>
            </article>
            <article class="product-card product-card-image">
                <img src="{{ asset('images/elephant.jpg') }}" alt="Яркий авторский принт" loading="lazy" width="900" height="1273">
                <div class="product-copy"><span>04</span><h3>Принты<br>на кружках</h3><p>Фирменные, подарочные и персональные изображения для ежедневных вещей.</p></div>
            </article>
        </div>
    </section>

    <section id="personal" class="personal-section">
        <div class="personal-visual">
            <img src="{{ asset('images/beach.jpg') }}" alt="Пример изображения для персональной картины" loading="lazy" width="900" height="1261">
            <div class="scheme-overlay" aria-hidden="true"><span>3</span><span>7</span><span>12</span><span>4</span></div>
            <p>Фото <b>→</b> творческий набор</p>
        </div>
        <div class="personal-copy">
            <p class="kicker kicker-light"><span></span> Особенный продукт</p>
            <h2>Ваша история —<br><em>в цвете и деталях</em></h2>
            <p>Превращаем любимую фотографию в картину по номерам или алмазную мозаику. Можно заранее подготовить схему в нашем онлайн-генераторе: подобрать палитру, настроить детализацию и скачать результат.</p>
            <ol>
                <li><span>01</span> Загрузите фотографию</li>
                <li><span>02</span> Настройте цвета и детали</li>
                <li><span>03</span> Получите готовую схему</li>
            </ol>
            <a class="button button-bright" href="{{ route('generator') }}">Открыть генератор <span>→</span></a>
        </div>
    </section>

    <section id="business" class="section business-section">
        <div class="business-copy">
            <p class="kicker"><span></span> Производство для бизнеса</p>
            <h2>Ваш бренд.<br><em>Наше производство.</em></h2>
            <p>Выпускаем творческие наборы и печатную продукцию под брендом заказчика. Помогаем превратить идею, коллекцию изображений или фирменный стиль в готовый продукт.</p>
            <div class="business-tags"><span>СТМ</span><span>Корпоративные подарки</span><span>Лимитированные серии</span><span>Авторские коллекции</span></div>
        </div>
        <div class="business-panel">
            <p class="panel-number">B2B / 04</p>
            <h3>От макета<br>до готовой партии</h3>
            <ul>
                <li><span>А</span> Адаптация изображения под продукт</li>
                <li><span>Б</span> Брендирование и упаковка</li>
                <li><span>В</span> Серийное производство</li>
            </ul>
            <a href="#process">Как строится работа <span>↓</span></a>
        </div>
    </section>

    <section id="process" class="process-section">
        <div class="section-heading section-heading-light">
            <div><p class="kicker kicker-light"><span></span> Как мы работаем</p><h2>Понятный путь<br>от идеи до продукта</h2></div>
            <p>Формат работы зависит от продукта и тиража, но логика остаётся простой и прозрачной.</p>
        </div>
        <div class="process-grid">
            <article><b>01</b><h3>Знакомимся<br>с задачей</h3><p>Определяем продукт, сюжет, формат и требования к бренду.</p></article>
            <article><b>02</b><h3>Готовим<br>макет</h3><p>Адаптируем изображение и согласовываем визуальную часть.</p></article>
            <article><b>03</b><h3>Создаём<br>продукт</h3><p>Печатаем, комплектуем и проверяем результат.</p></article>
            <article><b>04</b><h3>Передаём<br>заказ</h3><p>Подготавливаем готовую продукцию к отправке.</p></article>
        </div>
    </section>

    <section class="section faq-section">
        <div><p class="kicker"><span></span> Вопросы и ответы</p><h2>Коротко<br>о главном</h2></div>
        <div class="faq-list">
            <details open><summary>Можно сделать картину по своей фотографии?<span>+</span></summary><p>Да. Загрузите фотографию в генератор, настройте палитру и детализацию, а затем сохраните готовую схему.</p></details>
            <details><summary>Работаете ли вы с брендами и авторами?<span>+</span></summary><p>Да, возможно производство продукции под брендом заказчика, выпуск авторских коллекций и корпоративных серий.</p></details>
            <details><summary>Какие продукты можно персонализировать?<span>+</span></summary><p>Картины по номерам и алмазную мозаику можно подготовить по фотографии. Для холстов и кружек можно использовать собственные изображения и макеты.</p></details>
        </div>
    </section>

    <section class="final-cta">
        <div class="cta-art" aria-hidden="true"><span></span><span></span><span></span></div>
        <p>Начните с фотографии</p>
        <h2>Создайте свою<br><em>картину по номерам</em></h2>
        <a class="button button-primary" href="{{ route('generator') }}">Перейти в генератор <span>↗</span></a>
    </section>
</main>

<footer class="brand-footer">
    <a class="footer-logo" href="{{ route('home') }}"><img src="{{ asset('images/tokamaki-logo.png') }}" alt="" width="72" height="72"><strong>Токамаки</strong></a>
    <p>Товары для творчества,<br>которые становятся личными.</p>
    <nav><a href="#products">Продукция</a><a href="#personal">По фото</a><a href="#business">Для бизнеса</a><a href="{{ route('generator') }}">Генератор</a></nav>
    <small>© {{ date('Y') }} Токамаки. tokamaki.ru</small>
</footer>
</body>
</html>
