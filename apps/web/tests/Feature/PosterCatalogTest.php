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

    public function test_catalog_searches_ai_keywords_when_query_has_a_different_word_form(): void
    {
        $rainbow = Poster::factory()->published()->create([
            'title' => 'Картина на холсте Радужное небо с облаками',
            'search_keywords' => ['радуга', 'небо с радугой'],
        ]);
        $other = Poster::factory()->published()->create([
            'title' => 'Картина с горами',
            'search_keywords' => ['горы'],
        ]);

        $this->get('/kartiny-na-holste?q=радуга')
            ->assertOk()
            ->assertSee($rainbow->title)
            ->assertDontSee($other->title);
    }

    public function test_published_poster_has_unique_page_and_order_dialog_without_marketplace_link(): void
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
            ->assertSee('id="poster-order-dialog"', false)
            ->assertSee(route('posters.custom.store'), false)
            ->assertSee('name="poster_id" value="'.$poster->id.'"', false)
            ->assertDontSee('https://www.wildberries.ru/catalog/123456789/detail.aspx', false);
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

    public function test_catalog_renders_commercial_content_collections_and_structured_data(): void
    {
        $category = PosterCategory::create([
            'name' => 'Гостиная',
            'slug' => 'gostinaya',
            'type' => 'room',
            'is_active' => true,
        ]);
        $poster = Poster::factory()->published()->create(['title' => 'Тёплая абстракция']);
        $poster->categories()->attach($category);

        $this->get('/kartiny-na-holste')
            ->assertOk()
            ->assertSee('Картины на холсте купить — готовые 30×40 и 40×50 | Токамаки')
            ->assertSee('Готовые картины на холсте')
            ->assertSee('Популярные подборки')
            ->assertSee('/kartiny-na-holste/gostinaya', false)
            ->assertSee('Частые вопросы о картинах на холсте')
            ->assertSee('"@type": "BreadcrumbList"', false)
            ->assertSee('"@type": "ItemList"', false)
            ->assertSee('"@type": "FAQPage"', false);
    }

    public function test_catalog_renders_live_filter_controls_and_active_filter_chips(): void
    {
        $category = PosterCategory::create([
            'name' => 'Минимализм',
            'slug' => 'minimalizm',
            'type' => 'style',
            'is_active' => true,
        ]);
        $poster = Poster::factory()->published()->create([
            'orientation' => 'vertical',
        ]);
        $poster->categories()->attach($category);

        $this->get('/kartiny-na-holste?category=minimalizm&size=30x40&orientation=vertical&sort=price_asc')
            ->assertOk()
            ->assertSee('data-live-filter', false)
            ->assertSee('catalogTools.requestSubmit()', false)
            ->assertSee('aria-label="Активные фильтры"', false)
            ->assertSee('Минимализм')
            ->assertSee('30×40 см')
            ->assertSee('Вертикальные')
            ->assertSee('Сначала дешевле');
    }

    public function test_related_posters_prioritize_primary_theme_then_style_and_visual_attributes(): void
    {
        $animals = PosterCategory::create(['name' => 'Животные', 'slug' => 'zhivotnye', 'type' => 'theme', 'is_active' => true]);
        $nature = PosterCategory::create(['name' => 'Природа', 'slug' => 'priroda', 'type' => 'theme', 'is_active' => true]);
        $eclectic = PosterCategory::create(['name' => 'Эклектика', 'slug' => 'eklektika', 'type' => 'style', 'is_active' => true]);
        $livingRoom = PosterCategory::create(['name' => 'Гостиная', 'slug' => 'gostinaya', 'type' => 'room', 'is_active' => true]);

        $frog = Poster::factory()->published()->create([
            'category_id' => $animals->id,
            'primary_color' => 'розовый',
            'orientation' => 'vertical',
        ]);
        $frog->categories()->attach([$animals->id, $nature->id, $eclectic->id, $livingRoom->id]);

        $abstract = Poster::factory()->published()->create(['title' => 'Абстракция для гостиной']);
        $abstract->categories()->attach([$nature->id, $livingRoom->id]);

        $matchingAnimalColor = Poster::factory()->published()->create([
            'title' => 'Розовая лиса',
            'primary_color' => 'розовый',
            'orientation' => 'vertical',
        ]);
        $matchingAnimalColor->categories()->attach($animals);

        $matchingAnimalStyle = Poster::factory()->published()->create([
            'title' => 'Эклектичный тигр',
            'primary_color' => 'синий',
            'orientation' => 'horizontal',
        ]);
        $matchingAnimalStyle->categories()->attach([$animals->id, $eclectic->id]);

        $natureOnly = Poster::factory()->published()->create(['title' => 'Лесной пейзаж']);
        $natureOnly->categories()->attach($nature);

        $roomOnly = Poster::factory()->published()->create(['title' => 'Декор гостиной']);
        $roomOnly->categories()->attach($livingRoom);

        $this->get(route('posters.show', $frog))
            ->assertOk()
            ->assertViewHas('related', fn ($related): bool => $related->pluck('id')->all() === [
                $matchingAnimalStyle->id,
                $matchingAnimalColor->id,
                $abstract->id,
                $natureOnly->id,
            ]);
    }

    public function test_product_page_increments_view_count(): void
    {
        $poster = Poster::factory()->published()->create(['view_count' => 7]);

        $this->get(route('posters.show', $poster))->assertOk();
        $this->get(route('posters.show', $poster))->assertOk();

        $this->assertSame(9, $poster->fresh()->view_count);
    }

    public function test_admin_recommended_posters_are_first_in_catalog_and_category(): void
    {
        $category = PosterCategory::create([
            'name' => 'Животные',
            'slug' => 'zhivotnye',
            'type' => 'theme',
            'is_active' => true,
        ]);
        $regular = Poster::factory()->published()->create([
            'title' => 'Новая обычная картина',
            'published_at' => now(),
        ]);
        $recommended = Poster::factory()->published()->create([
            'title' => 'Выбор администратора',
            'is_admin_recommended' => true,
            'published_at' => now()->subDay(),
        ]);
        $regular->categories()->attach($category);
        $recommended->categories()->attach($category);

        $this->get(route('posters.index'))
            ->assertOk()
            ->assertViewHas('posters', fn ($posters): bool => $posters->pluck('id')->all() === [
                $recommended->id,
                $regular->id,
            ]);

        $this->get(route('posters.show', $category))
            ->assertOk()
            ->assertViewHas('posters', fn ($posters): bool => $posters->pluck('id')->all() === [
                $recommended->id,
                $regular->id,
            ]);
    }

    public function test_admin_recommended_posters_are_first_in_related_results(): void
    {
        $category = PosterCategory::create([
            'name' => 'Животные',
            'slug' => 'zhivotnye',
            'type' => 'theme',
            'is_active' => true,
        ]);
        $poster = Poster::factory()->published()->create(['category_id' => $category->id]);
        $poster->categories()->attach($category);
        $regular = Poster::factory()->published()->create(['title' => 'Обычная картина']);
        $regular->categories()->attach($category);
        $recommended = Poster::factory()->published()->create([
            'title' => 'Рекомендованная картина',
            'is_admin_recommended' => true,
        ]);
        $recommended->categories()->attach($category);

        $this->get(route('posters.show', $poster))
            ->assertOk()
            ->assertViewHas('related', fn ($related): bool => $related->pluck('id')->take(2)->all() === [
                $recommended->id,
                $regular->id,
            ]);
    }
}
