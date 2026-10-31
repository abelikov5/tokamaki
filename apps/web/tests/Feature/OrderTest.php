<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_create_an_order_and_both_images_are_saved_privately(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        config()->set('services.generator.url', 'http://generator.test');
        config()->set('orders.base_price', 1800);
        config()->set('orders.designer_price', 600);

        Http::fake([
            'http://generator.test/api/v1/results/result-123/original.png' => Http::response('original-image'),
            'http://generator.test/api/v1/results/result-123/preview.png' => Http::response('generated-image'),
        ]);

        $generation = $this->completedGeneration();
        $response = $this->postJson(route('orders.store', $generation), [
            'email' => 'customer@example.com',
            'city' => 'Москва',
            'street' => 'Тверская',
            'designer_processing' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Заказ принят. Мы свяжемся с вами по электронной почте.')
            ->assertJsonStructure(['order_id']);

        $order = Order::sole();
        $this->assertSame($generation->id, $order->generation_id);
        $this->assertSame(
            config('orders.base_price') + config('orders.designer_price'),
            $order->amount,
        );
        $this->assertTrue($order->designer_processing);
        Storage::disk('local')->assertExists($order->original_image_path);
        Storage::disk('local')->assertExists($order->generated_image_path);
        $this->assertSame('original-image', Storage::disk('local')->get($order->original_image_path));
        $this->assertSame('generated-image', Storage::disk('local')->get($order->generated_image_path));
        Http::assertSentCount(2);
    }

    public function test_order_fields_are_required(): void
    {
        Http::preventStrayRequests();

        $this->postJson(route('orders.store', $this->completedGeneration()), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'city', 'street', 'designer_processing']);

        $this->assertDatabaseEmpty('orders');
        Http::assertNothingSent();
    }

    public function test_order_cannot_be_created_for_an_incomplete_generation(): void
    {
        Http::preventStrayRequests();
        $generation = Generation::create([
            'status' => 'processing',
            'original_name' => 'portrait.jpg',
            'settings' => ['colors' => 24],
        ]);

        $this->postJson(route('orders.store', $generation), [
            'email' => 'customer@example.com',
            'city' => 'Москва',
            'street' => 'Тверская',
            'designer_processing' => false,
        ])->assertNotFound();

        $this->assertDatabaseEmpty('orders');
        Http::assertNothingSent();
    }

    public function test_only_the_administrator_can_view_private_order_images(): void
    {
        Storage::fake('local');
        config()->set('services.filament_admin.email', 'admin@example.com');
        $order = Order::factory()->create();
        Storage::disk('local')->put($order->original_image_path, 'original-image');
        Storage::disk('local')->put($order->generated_image_path, 'generated-image');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $otherUser = User::factory()->create(['email' => 'other@example.com']);

        $this->get(route('admin.orders.image', [$order, 'original']))->assertForbidden();
        $this->actingAs($otherUser)
            ->get(route('admin.orders.image', [$order, 'original']))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.orders.image', [$order, 'original']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertContent('original-image');
        $this->actingAs($admin)
            ->get(route('admin.orders.image', [$order, 'generated']))
            ->assertOk()
            ->assertContent('generated-image');
    }

    public function test_administrator_sees_order_in_the_panel(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $order = Order::factory()->create([
            'email' => 'customer@example.com',
            'city' => 'Казань',
            'street' => 'Баумана',
        ]);

        $this->actingAs($admin)
            ->get('/admin/orders')
            ->assertOk()
            ->assertSee('customer@example.com')
            ->assertSee('Казань');
        $this->actingAs($admin)
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee('Баумана');
    }

    private function completedGeneration(): Generation
    {
        return Generation::create([
            'status' => 'completed',
            'result_id' => 'result-123',
            'original_name' => 'portrait.jpg',
            'settings' => ['colors' => 24],
        ]);
    }
}
