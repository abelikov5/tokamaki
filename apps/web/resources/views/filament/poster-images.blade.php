@if($images->isEmpty())
    <div style="padding: 1rem; border: 1px dashed #d1d5db; border-radius: .75rem; color: #6b7280;">
        Фотографии не импортированы. Повторите импорт из WB после проверки фотографий в исходном проекте.
    </div>
@else
    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem;">
        @foreach($images as $image)
            @php($url = $image->path ? Storage::disk('public')->url($image->path) : $image->remote_url)
            @if($url)
                <a href="{{ $url }}" target="_blank" rel="noopener" style="display: block; overflow: hidden; border: 1px solid #e5e7eb; border-radius: .75rem; background: #f9fafb; text-decoration: none;">
                    <img src="{{ $url }}" alt="{{ $image->alt_text ?: 'Фотография постера' }}" style="display: block; width: 100%; height: 190px; object-fit: contain; background: white;">
                    <span style="display: block; padding: .6rem .75rem; color: #6b7280; font-size: .75rem;">{{ $image->role === 'main' ? 'Главное изображение' : 'Дополнительное изображение' }}</span>
                </a>
            @endif
        @endforeach
    </div>
@endif
