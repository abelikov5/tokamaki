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

    public function test_job_generates_content_assigns_multiple_groups_and_creates_new_ai_suggestions(): void
    {
        config()->set([
            'services.aitunnel.key' => 'test-key',
            'services.aitunnel.api_url' => 'https://aitunnel.test/v1/chat/completions',
            'services.aitunnel.poster_model' => 'qwen3.7-flash',
        ]);
        $currentCategory = PosterCategory::create(['name' => 'Интерьерные картины', 'slug' => 'interernye-kartiny', 'is_active' => true]);
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
            'https://aitunnel.test/*' => Http::response($this->responsePayload([
                ['name' => 'Природа', 'slug' => 'priroda', 'type' => 'theme'],
                ['name' => 'Бохо', 'slug' => 'boho', 'type' => 'style'],
                ['name' => 'Для гостиной', 'slug' => 'dlya-gostinoy', 'type' => 'room'],
            ])),
        ]);

        (new GeneratePosterContentJob($poster->id))->handle(app(AiPosterContentService::class));

        $poster->refresh();
        $this->assertSame('generated', $poster->ai_generation_status);
        $this->assertSame('qwen3.7-flash', $poster->ai_model);
        $this->assertSame(AiPosterContentService::PROMPT_VERSION, $poster->ai_prompt_version);
        $this->assertSame('Картина на холсте с горным озером', $poster->title);
        $this->assertNotNull($poster->ai_generated_at);
        $this->assertSame($poster->content_hash, $poster->ai_input_hash);
        $this->assertSame(['Природа', 'Бохо', 'Гостиная'], $poster->categories()->orderByPivot('poster_category_id')->pluck('name')->all());
        $this->assertSame('priroda', $poster->posterCategory?->slug);
        $this->assertSame('priroda', $poster->ai_response['groups'][0]['slug']);
        $this->assertDatabaseHas('poster_categories', ['name' => 'Бохо', 'type' => 'style']);
        $this->assertDatabaseHas('poster_categories', ['name' => 'Гостиная', 'type' => 'room']);
        Http::assertSent(function (Request $request): bool {
            $userContent = $request['messages'][1]['content'];

            return $request['model'] === 'qwen3.7-flash'
                && is_string($userContent)
                && str_contains($userContent, 'existing_groups')
                && str_contains($userContent, 'interernye-kartiny')
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
        $category = PosterCategory::create(['name' => 'Интерьерные картины', 'slug' => 'interernye-kartiny', 'is_active' => true]);
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
            'https://aitunnel.test/*' => Http::response($this->responsePayload([
                ['name' => 'Интерьерные картины', 'slug' => 'interernye-kartiny', 'type' => 'collection'],
            ])),
        ]);

        app(AiPosterContentService::class)->generate($poster);

        Http::assertSent(function (Request $request): bool {
            $content = $request['messages'][1]['content'];

            return is_array($content)
                && str_starts_with((string) data_get($content, '1.image_url.url'), 'data:image/jpeg;base64,');
        });
    }

    public function test_ai_can_create_a_new_normalized_group(): void
    {
        config()->set([
            'services.aitunnel.key' => 'test-key',
            'services.aitunnel.api_url' => 'https://aitunnel.test/v1/chat/completions',
            'services.aitunnel.poster_model' => 'qwen3.7-flash',
        ]);
        $category = PosterCategory::create(['name' => 'Интерьерные картины', 'slug' => 'interernye-kartiny', 'is_active' => true]);
        $poster = Poster::factory()->published()->create([
            'category_id' => $category->id,
            'source_payload' => ['visual_analysis' => ['description' => 'Абстрактный сюжет']],
        ]);
        Http::fake([
            'https://aitunnel.test/*' => Http::response($this->responsePayload([
                ['name' => 'Морские пейзажи', 'slug' => 'morskie-peizazhi', 'type' => 'theme'],
            ])),
        ]);

        $result = app(AiPosterContentService::class)->generate($poster);

        $created = PosterCategory::where('name', 'Морские пейзажи')->firstOrFail();
        $this->assertSame($created->id, $result['category_id']);
        $this->assertSame([$created->id], $result['group_ids']);
        $this->assertDatabaseCount('poster_categories', 2);
    }

    /** @return array<string, mixed> */
    private function responsePayload(array $groups): array
    {
        return [
            'choices' => [[
                'message' => [
                    'content' => json_encode([
                        'title' => 'Картина на холсте с горным озером',
                        'short_description' => str_repeat('Спокойный горный пейзаж в мягких утренних оттенках. ', 3),
                        'description' => str_repeat('На картине изображено тихое горное озеро в лучах рассвета. Сдержанная палитра и глубокая перспектива создают спокойное настроение и визуально расширяют пространство. ', 5),
                        'seo_title' => 'Горное озеро — картина на холсте',
                        'seo_description' => str_repeat('Интерьерная картина с горным озером и рассветным небом, напечатанная на холсте. ', 2),
                        'groups' => $groups,
                        'style' => 'Современный',
                        'room' => 'Гостиная',
                        'orientation' => 'vertical',
                        'search_keywords' => ['горное озеро', 'рассвет', 'пейзаж', 'картина в гостиную', 'горы', 'спокойный интерьер'],
                    ], JSON_UNESCAPED_UNICODE),
                ],
            ]],
        ];
    }
}
