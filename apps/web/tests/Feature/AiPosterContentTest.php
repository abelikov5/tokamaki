<?php

namespace Tests\Feature;

use App\Jobs\GeneratePosterContentJob;
use App\Models\Poster;
use App\Models\PosterCategory;
use App\Models\PosterImage;
use App\Services\AiPosterContentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiPosterContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_generates_site_content_and_uses_only_controlled_category(): void
    {
        config()->set([
            'services.aitunnel.key' => 'test-key',
            'services.aitunnel.api_url' => 'https://aitunnel.test/v1/chat/completions',
            'services.aitunnel.poster_model' => 'qwen3.7-flash',
        ]);
        $currentCategory = PosterCategory::create(['name' => 'Интерьерные постеры', 'slug' => 'interernye-postery', 'is_active' => true]);
        $natureCategory = PosterCategory::create(['name' => 'Природа', 'slug' => 'priroda', 'is_active' => true, 'sort_order' => 10]);
        PosterCategory::create(['name' => 'Скрытая', 'slug' => 'skrytaya', 'is_active' => false]);
        $poster = Poster::factory()->published()->create([
            'category_id' => $currentCategory->id,
            'content_hash' => str_repeat('a', 64),
            'source_payload' => [
                'title' => 'Картина горы 40x50',
                'description' => 'Старое описание Wildberries не должно попадать в запрос.',
                'visual_analysis' => [
                    'description' => 'Горный пейзаж на рассвете с озером на переднем плане.',
                    'primary_colors' => ['синий', 'бежевый'],
                ],
            ],
        ]);

        Http::fake([
            'https://aitunnel.test/*' => Http::response($this->responsePayload('priroda')),
        ]);

        (new GeneratePosterContentJob($poster->id))->handle(app(AiPosterContentService::class));

        $poster->refresh();
        $this->assertSame('generated', $poster->ai_generation_status);
        $this->assertSame('qwen3.7-flash', $poster->ai_model);
        $this->assertSame(AiPosterContentService::PROMPT_VERSION, $poster->ai_prompt_version);
        $this->assertSame($natureCategory->id, $poster->category_id);
        $this->assertSame('Постер с горным озером на рассвете', $poster->title);
        $this->assertNotNull($poster->ai_generated_at);
        $this->assertSame($poster->content_hash, $poster->ai_input_hash);
        $this->assertSame('priroda', $poster->ai_response['category_slug']);
        Http::assertSent(function (Request $request): bool {
            $userContent = $request['messages'][1]['content'];

            return $request['model'] === 'qwen3.7-flash'
                && is_string($userContent)
                && str_contains($userContent, 'interernye-postery')
                && str_contains($userContent, 'priroda')
                && ! str_contains($userContent, 'skrytaya')
                && ! str_contains($userContent, 'Старое описание Wildberries');
        });
    }

    public function test_service_sends_main_image_when_visual_analysis_is_missing(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('posters/test.jpg', 'image-bytes');
        config()->set([
            'services.aitunnel.key' => 'test-key',
            'services.aitunnel.api_url' => 'https://aitunnel.test/v1/chat/completions',
            'services.aitunnel.poster_model' => 'vision-flash',
        ]);
        $category = PosterCategory::create(['name' => 'Интерьерные постеры', 'slug' => 'interernye-postery', 'is_active' => true]);
        $poster = Poster::factory()->published()->create([
            'category_id' => $category->id,
            'source_payload' => ['title' => 'Неизвестный сюжет'],
        ]);
        PosterImage::create([
            'poster_id' => $poster->id,
            'role' => 'main',
            'sort_order' => 0,
            'path' => 'posters/test.jpg',
            'mime_type' => 'image/jpeg',
        ]);
        Http::fake([
            'https://aitunnel.test/*' => Http::response($this->responsePayload('interernye-postery')),
        ]);

        app(AiPosterContentService::class)->generate($poster);

        Http::assertSent(function (Request $request): bool {
            $content = $request['messages'][1]['content'];

            return is_array($content)
                && str_starts_with((string) data_get($content, '1.image_url.url'), 'data:image/jpeg;base64,');
        });
    }

    public function test_unknown_ai_category_falls_back_without_creating_a_category(): void
    {
        config()->set([
            'services.aitunnel.key' => 'test-key',
            'services.aitunnel.api_url' => 'https://aitunnel.test/v1/chat/completions',
            'services.aitunnel.poster_model' => 'qwen3.7-flash',
        ]);
        $category = PosterCategory::create(['name' => 'Интерьерные постеры', 'slug' => 'interernye-postery', 'is_active' => true]);
        $poster = Poster::factory()->published()->create([
            'category_id' => $category->id,
            'source_payload' => ['visual_analysis' => ['description' => 'Абстрактный сюжет']],
        ]);
        Http::fake([
            'https://aitunnel.test/*' => Http::response($this->responsePayload('pridumannaya-kategoriya')),
        ]);

        $result = app(AiPosterContentService::class)->generate($poster);

        $this->assertSame($category->id, $result['category_id']);
        $this->assertDatabaseCount('poster_categories', 1);
    }

    /** @return array<string, mixed> */
    private function responsePayload(string $categorySlug): array
    {
        return [
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'Постер с горным озером на рассвете',
                        'short_description' => str_repeat('Спокойный горный пейзаж в мягких утренних оттенках. ', 3),
                        'description' => str_repeat('На постере изображено тихое горное озеро в лучах рассвета. Сдержанная палитра и глубокая перспектива создают спокойное настроение и визуально расширяют пространство. ', 5),
                        'seo_title' => 'Постер с горным озером на рассвете | Токамаки',
                        'seo_description' => str_repeat('Интерьерный постер с горным озером и рассветным небом на холсте с деревянным подрамником. ', 2),
                        'category_slug' => $categorySlug,
                        'style' => 'Современный',
                        'room' => 'Гостиная',
                        'orientation' => 'vertical',
                        'search_keywords' => ['горное озеро', 'рассвет', 'пейзаж', 'постер в гостиную', 'горы', 'спокойный интерьер'],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ];
    }
}
