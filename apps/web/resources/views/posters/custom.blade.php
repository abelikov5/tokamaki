@extends('layouts.posters')

@section('title', $poster ? 'Заказать «'.$poster->title.'» — Токамаки' : 'Постер по своему фото на подрамнике — Токамаки')
@section('description', $poster ? 'Оформите заказ готового постера на подрамнике в размере 30×40 или 40×50 см.' : 'Загрузите фотографию, выберите размер 30×40 или 40×50 и закажите персональный постер на подрамнике.')

@php
    $currentSize = old('size', $selectedSize);
    $formatPrice = fn (int $price) => number_format($price, 0, ',', ' ').' ₽';
@endphp

@section('content')
<section class="poster-checkout">
    <div class="checkout-copy">
        <p class="poster-kicker">Оформление заказа</p>
        <h1>{{ $poster ? 'Ваш постер — готов к заказу' : 'Ваше фото — постер на стене' }}</h1>
        <p>{{ $poster ? 'Выберите размер и укажите город с улицей. Менеджер уточнит удобный пункт выдачи по электронной почте.' : 'Загрузите фотографию, выберите размер и укажите адрес. Мы проверим качество изображения перед печатью.' }}</p>

        <div class="checkout-product">
            @if($poster)
                <div class="checkout-product-image">@if($poster->primary_image_url)<img src="{{ $poster->primary_image_url }}" alt="{{ $poster->title }}">@endif</div>
                <div><small>Выбранный постер</small><strong>{{ $poster->title }}</strong><a href="{{ route('posters.show', $poster) }}">Вернуться к постеру</a></div>
            @else
                <div class="checkout-product-mark">+</div>
                <div><small>Персональный постер</small><strong>По вашему фото</strong><span>Проверим резкость и пропорции до печати</span></div>
            @endif
        </div>

        <ul class="checkout-benefits">
            <li><span>✓</span><div><strong>Холст на подрамнике</strong><small>Готовый постер можно сразу разместить на стене</small></div></li>
            <li><span>✓</span><div><strong>Размер 30×40 всегда доступен</strong><small>Независимо от наличия этой позиции на Wildberries</small></div></li>
            <li><span>✓</span><div><strong>Бесплатная доставка</strong><small>До ПВЗ Авито или СДЭК</small></div></li>
            <li><span>✓</span><div><strong>Проверка перед печатью</strong><small>Если потребуется, согласуем детали по email</small></div></li>
        </ul>
    </div>

    <form class="poster-order-form" method="post" action="{{ route('posters.custom.store') }}" enctype="multipart/form-data">
        @csrf
        @if($poster)<input type="hidden" name="poster_id" value="{{ $poster->id }}">@endif
        <input type="hidden" name="consent" value="1">

        @if(session('poster_request_success'))
            <div class="order-success-inline"><span>✓</span><div><strong>Заказ принят</strong><p>{{ session('poster_request_success') }}</p></div></div>
        @endif
        @if($errors->any())
            <div class="form-errors"><strong>Проверьте форму:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <div class="order-form-heading">
            <p>Данные заказа</p>
            <h2>Куда доставить постер?</h2>
            <span>Доставка до ПВЗ бесплатная. Точный пункт выдачи уточним после оформления.</span>
        </div>

        @unless($poster)
            <label class="upload-zone checkout-upload">
                <input id="custom-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
                <span id="upload-label"><strong>Выберите фотографию</strong><small>JPG, PNG или WEBP до 20 МБ</small></span>
                <img id="upload-preview" alt="Предпросмотр выбранной фотографии" hidden>
            </label>
        @endunless

        <fieldset class="checkout-size-options">
            <legend>Размер постера</legend>
            @foreach(['30x40' => 'Компактный формат', '40x50' => 'Для акцентной стены'] as $size => $caption)
                <label>
                    <input type="radio" name="size" value="{{ $size }}" data-price="{{ $prices[$size] }}" @checked($currentSize === $size)>
                    <span><b>{{ str_replace('x', '×', $size) }} см</b><small>{{ $caption }}</small><strong>{{ $formatPrice($prices[$size]) }}</strong></span>
                </label>
            @endforeach
        </fieldset>

        <div class="order-address-fields">
            <label><span>Email</span><input name="email" type="email" autocomplete="email" required maxlength="254" value="{{ old('email') }}" placeholder="name@example.ru"></label>
            <label><span>Город</span><input name="city" type="text" autocomplete="address-level2" required maxlength="120" value="{{ old('city') }}" placeholder="Москва"></label>
            <label class="street-field"><span>Улица</span><input name="street" type="text" autocomplete="address-line1" required maxlength="180" value="{{ old('street') }}" placeholder="ул. Соборная"></label>
            <label class="street-field"><span>Комментарий <small>необязательно</small></span><textarea name="comment" rows="3" maxlength="2000" placeholder="Пожелания к заказу">{{ old('comment') }}</textarea></label>
        </div>

        <div class="checkout-total"><span>Постер <b id="checkout-size-label">{{ str_replace('x', '×', $currentSize) }} см</b></span><strong id="checkout-total">{{ $formatPrice($prices[$currentSize]) }}</strong></div>
        <button class="poster-order-submit" type="submit">Оформить заказ</button>
        <small class="checkout-privacy">Нажимая кнопку, вы соглашаетесь с <a href="{{ route('privacy') }}" target="_blank">политикой конфиденциальности</a>.</small>
    </form>
</section>

<section class="custom-steps"><article><b>01</b><h2>Оформляете заказ</h2><p>Выбираете размер и указываете город с улицей.</p></article><article><b>02</b><h2>Уточняем детали</h2><p>Проверяем макет и подбираем удобный пункт выдачи.</p></article><article><b>03</b><h2>Печатаем и отправляем</h2><p>Натягиваем холст на подрамник и передаём в доставку.</p></article></section>

<script>
const priceFormatter = new Intl.NumberFormat('ru-RU');
document.querySelectorAll('[name="size"]').forEach((input) => input.addEventListener('change', () => {
    document.getElementById('checkout-total').textContent = `${priceFormatter.format(Number(input.dataset.price))} ₽`;
    document.getElementById('checkout-size-label').textContent = `${input.value.replace('x', '×')} см`;
}));
const photoInput = document.getElementById('custom-photo');
photoInput?.addEventListener('change', () => {
    const file = photoInput.files[0];
    if (!file) return;
    const preview = document.getElementById('upload-preview');
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
    document.getElementById('upload-label').hidden = true;
});
</script>
@endsection
