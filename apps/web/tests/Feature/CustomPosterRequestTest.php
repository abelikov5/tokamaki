<?php

namespace Tests\Feature;

use App\Models\CustomPosterRequest;
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
            'name' => 'Анна',
            'phone' => '+7 900 000-00-00',
            'email' => 'anna@example.test',
            'consent' => '1',
        ])->assertRedirect()->assertSessionHas('poster_request_success');

        $this->assertDatabaseHas('custom_poster_requests', [
            'name' => 'Анна',
            'size_code' => '40x50',
            'status' => 'new',
        ]);

        $path = (string) CustomPosterRequest::firstOrFail()->photo_path;
        Storage::disk('local')->assertExists($path);
    }
}
