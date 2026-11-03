@extends('layouts.posters')

@section('title', 'Постер по своему фото на подрамнике — Токамаки')
@section('description', 'Загрузите фотографию, выберите размер 30×40 или 40×50 и закажите персональный постер на подрамнике.')

@section('content')
<section class="custom-hero">
    <div class="custom-copy">
        <p class="poster-kicker">Один экземпляр. Ваша история.</p>
        <h1>Ваше фото —<br><em>постер на стене</em></h1>
        <p>Загрузите фотографию или иллюстрацию. Мы проверим качество, подготовим изображение к печати и согласуем результат.</p>
        <ul><li>Холст на деревянном подрамнике</li><li>Размеры 30×40 и 40×50 см</li><li>Проверка изображения перед печатью</li></ul>
    </div>
    <form class="custom-form" method="post" action="{{ route('posters.custom.store') }}" enctype="multipart/form-data">
        @csrf
        @if(session('poster_request_success'))<div class="form-success">{{ session('poster_request_success') }}</div>@endif
        @if($errors->any())<div class="form-errors"><strong>Проверьте форму:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
        <p class="form-step">01 / Изображение</p>
        <label class="upload-zone">
            <input id="custom-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
            <span id="upload-label"><strong>Выберите фотографию</strong><small>JPG, PNG или WEBP до 20 МБ</small></span>
            <img id="upload-preview" alt="Предпросмотр выбранной фотографии" hidden>
        </label>
        <p class="form-step">02 / Размер</p>
        <div class="size-options">
            <label><input type="radio" name="size" value="30x40" checked><span><b>30×40 см</b><small>Компактный формат</small></span></label>
            <label><input type="radio" name="size" value="40x50"><span><b>40×50 см</b><small>Для акцентной стены</small></span></label>
        </div>
        <div class="contact-fields">
            <label><span>Как к вам обращаться</span><input name="name" value="{{ old('name') }}" required maxlength="120"></label>
            <label><span>Телефон</span><input name="phone" value="{{ old('phone') }}" required maxlength="40" inputmode="tel"></label>
            <label><span>Email, если удобнее</span><input type="email" name="email" value="{{ old('email') }}" maxlength="255"></label>
            <label><span>Комментарий</span><textarea name="comment" rows="3" maxlength="2000">{{ old('comment') }}</textarea></label>
        </div>
        <label class="consent"><input type="checkbox" name="consent" value="1" required> <span>Согласен на обработку данных по <a href="{{ route('privacy') }}" target="_blank">политике конфиденциальности</a>.</span></label>
        <button type="submit">Отправить на проверку</button>
    </form>
</section>
<section class="custom-steps"><article><b>01</b><h2>Загружаете фото</h2><p>Лучше использовать оригинал без сжатия из мессенджера.</p></article><article><b>02</b><h2>Проверяем макет</h2><p>Оценим резкость, пропорции и запас изображения под натяжку.</p></article><article><b>03</b><h2>Печатаем</h2><p>Переносим изображение на холст и натягиваем его на подрамник.</p></article></section>
<script>
const input = document.getElementById('custom-photo');
const preview = document.getElementById('upload-preview');
const label = document.getElementById('upload-label');
input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) return;
    preview.src = URL.createObjectURL(file);
    preview.hidden = false;
    label.hidden = true;
});
</script>
@endsection
