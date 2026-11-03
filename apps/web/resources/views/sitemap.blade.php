{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ route('home') }}</loc></url>
    <url><loc>{{ route('posters.index') }}</loc></url>
    <url><loc>{{ route('posters.custom') }}</loc></url>
    @foreach($posters as $poster)
        <url>
            <loc>{{ route('posters.show', $poster) }}</loc>
            <lastmod>{{ $poster->updated_at->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
