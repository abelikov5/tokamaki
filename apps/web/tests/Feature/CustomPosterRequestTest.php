<?php

namespace Tests\Feature;

use App\Models\CustomPosterRequest;
use App\Models\Poster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CustomPosterRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_submit_photo_for_review(): void
    {
        Storage::fake('local');

        $this->post('/postery/po-foto', [
            'photo' => UploadedFile::fake()->image('family.jpg', 1200, 1600),
            'size' => '40x50',
            'email' => 'anna@example.test',
            'city' => 'Москва',
            'street' => 'ул. Тверская',
            'consent' => '1',
        ])->assertRedirect()->assertSessionHas('poster_request_success');

        $this->assertDatabaseHas('custom_poster_requests', [
            'email' => 'anna@example.test',
            'city' => 'Москва',
            'size_code' => '40x50',
            'amount' => 1290,
            'status' => 'new',
        ]);

        $path = (string) CustomPosterRequest::firstOrFail()->photo_path;
        Storage::disk('local')->assertExists($path);
    }

    public function test_customer_can_order_ready_poster_in_30x40_without_upload(): void
    {
        $poster = Poster::factory()->published()->create();

        $this->post('/postery/po-foto', [
            'poster_id' => $poster->id,
            'size' => '30x40',
            'email' => 'buyer@example.test',
            'city' => 'Казань',
            'street' => 'ул. Баумана',
            'consent' => '1',
        ])->assertRedirect()->assertSessionHas('poster_request_success');

        $this->assertDatabaseHas('custom_poster_requests', [
            'poster_id' => $poster->id,
            'size_code' => '30x40',
            'amount' => 990,
            'photo_path' => null,
        ]);
    }
}
