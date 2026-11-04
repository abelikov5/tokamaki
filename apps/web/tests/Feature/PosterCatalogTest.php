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

        $this->get('/kartiny-na-holste?q=машина')
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

        $this->get('/kartiny-na-holste?size=30x40')
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
            'seo_title' => 'Картины с животными',
            'seo_description' => 'Картины на холсте с животными для интерьера.',
        ]);
        $included = Poster::factory()->published()->create(['category_id' => $category->id]);
        $included->categories()->attach($category);
        $excluded = Poster::factory()->published()->create(['title' => 'Картина из другой категории']);

        $this->get('/kartiny-na-holste/zhivotnye')
            ->assertOk()
            ->assertSee('Картины с животными')
            ->assertSee($included->title)
            ->assertDontSee($excluded->title);

        $this->get('/kartiny-na-holste?category=zhivotnye')
            ->assertOk()
            ->assertSee($included->title)
            ->assertDontSee($excluded->title);

        $this->get('/sitemap.xml')->assertSee('/kartiny-na-holste/zhivotnye', false);
    }

    public function test_inactive_category_is_hidden_without_hiding_its_posters_from_main_catalog(): void
    {
        $category = PosterCategory::create([
            'name' => 'Скрытая категория',
            'slug' => 'skrytaya-kategoriya',
            'is_active' => false,
        ]);
        $poster = Poster::factory()->published()->create(['category_id' => $category->id]);
        $poster->categories()->attach($category);

        $this->get('/kartiny-na-holste/skrytaya-kategoriya')->assertNotFound();
        $this->get('/kartiny-na-holste')->assertOk()->assertSee($poster->title)->assertDontSee('Скрытая категория ·');
        $this->get('/sitemap.xml')->assertDontSee('/kartiny-na-holste/skrytaya-kategoriya', false);
    }

    public function test_one_poster_can_be_found_in_several_group_pages(): void
    {
        $boho = PosterCategory::create(['name' => 'Бохо', 'slug' => 'boho', 'type' => 'style', 'is_active' => true]);
        $abstract = PosterCategory::create(['name' => 'Абстракция', 'slug' => 'abstraktsiya', 'type' => 'theme', 'is_active' => true]);
        $bedroom = PosterCategory::create(['name' => 'Спальня', 'slug' => 'spalnya', 'type' => 'room', 'is_active' => true]);
        $poster = Poster::factory()->published()->create();
        $poster->categories()->attach([$boho->id, $abstract->id, $bedroom->id]);

        foreach (['boho', 'abstraktsiya', 'spalnya'] as $slug) {
            $this->get('/kartiny-na-holste/'.$slug)->assertOk()->assertSee($poster->title);
        }

        $this->get('/kartiny-na-holste')->assertOk()->assertSee('Бохо · Абстракция · Спальня');
    }

    public function test_legacy_poster_urls_redirect_permanently_to_canvas_paintings(): void
    {
        $poster = Poster::factory()->published()->create();

        $this->get('/postery?q=море')
            ->assertRedirect('/kartiny-na-holste?q=%D0%BC%D0%BE%D1%80%D0%B5')
            ->assertStatus(301);
        $this->get('/postery/'.$poster->slug)
            ->assertRedirect(route('posters.show', $poster))
            ->assertStatus(301);
    }
}
