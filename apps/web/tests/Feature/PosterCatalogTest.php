<?php

namespace Tests\Feature;

use App\Models\Poster;
use App\Models\PosterCategory;
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

    public function test_30x40_is_available_even_without_wildberries_variant(): void
    {
        $poster = Poster::factory()->published()->create();
        PosterVariant::create([
            'poster_id' => $poster->id,
            'nm_id' => '987654321',
            'size_code' => '40x50',
            'width_cm' => 40,
            'height_cm' => 50,
            'price' => 1290,
            'is_active' => true,
        ]);

        $this->get('/postery?size=30x40')
            ->assertOk()
            ->assertSee($poster->title);

        $this->get(route('posters.show', $poster))
            ->assertOk()
            ->assertSee('30×40')
            ->assertSee(route('posters.custom', ['poster' => $poster->id, 'size' => '30x40']), false);
    }

    public function test_sitemap_contains_published_product_page(): void
    {
        $poster = Poster::factory()->published()->create();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('posters.show', $poster), false);
    }

    public function test_active_category_has_seo_page_and_filters_catalog_by_slug(): void
    {
        $category = PosterCategory::create([
            'name' => 'Животные',
            'slug' => 'zhivotnye',
            'is_active' => true,
            'sort_order' => 10,
            'seo_title' => 'Постеры с животными',
            'seo_description' => 'Авторские постеры с животными для интерьера.',
        ]);
        $included = Poster::factory()->published()->create(['category_id' => $category->id]);
        $excluded = Poster::factory()->published()->create(['title' => 'Постер из другой категории']);

        $this->get('/postery/zhivotnye')
            ->assertOk()
            ->assertSee('Постеры с животными')
            ->assertSee($included->title)
            ->assertDontSee($excluded->title);

        $this->get('/postery?category=zhivotnye')
            ->assertOk()
            ->assertSee($included->title)
            ->assertDontSee($excluded->title);

        $this->get('/sitemap.xml')->assertSee('/postery/zhivotnye', false);
    }

    public function test_inactive_category_is_hidden_without_hiding_its_posters_from_main_catalog(): void
    {
        $category = PosterCategory::create([
            'name' => 'Скрытая категория',
            'slug' => 'skrytaya-kategoriya',
            'is_active' => false,
        ]);
        $poster = Poster::factory()->published()->create(['category_id' => $category->id]);

        $this->get('/postery/skrytaya-kategoriya')->assertNotFound();
        $this->get('/postery')->assertOk()->assertSee($poster->title)->assertDontSee('Скрытая категория ·');
        $this->get('/sitemap.xml')->assertDontSee('/postery/skrytaya-kategoriya', false);
    }
}
