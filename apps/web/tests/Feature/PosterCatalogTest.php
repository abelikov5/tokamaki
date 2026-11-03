<?php

namespace Tests\Feature;

use App\Models\Poster;
use App\Models\PosterVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosterCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_only_shows_published_posters_and_searches_by_synonym(): void
    {
        $published = Poster::factory()->published()->create([
            'title' => 'Синий спортивный автомобиль',
            'category' => 'Автомобили',
        ]);
        Poster::factory()->create(['title' => 'Черновик']);

        $this->get('/postery?q=машина')
            ->assertOk()
            ->assertSee($published->title)
            ->assertDontSee('Черновик');
    }

    public function test_published_poster_has_unique_page_and_variant_link(): void
    {
        $poster = Poster::factory()->published()->create();
        PosterVariant::create([
            'poster_id' => $poster->id,
            'nm_id' => '123456789',
            'size_code' => '30x40',
            'width_cm' => 30,
            'height_cm' => 40,
            'price' => 1990,
            'is_active' => true,
        ]);

        $this->get(route('posters.show', $poster))
            ->assertOk()
            ->assertSee($poster->title)
            ->assertSee('30×40')
            ->assertSee('https://www.wildberries.ru/catalog/123456789/detail.aspx', false);
    }

    public function test_draft_poster_page_is_not_public(): void
    {
        $poster = Poster::factory()->create();

        $this->get(route('posters.show', $poster))->assertNotFound();
    }

    public function test_sitemap_contains_published_product_page(): void
    {
        $poster = Poster::factory()->published()->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('posters.show', $poster), false);
    }
}
