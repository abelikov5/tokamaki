<?php

namespace Tests\Feature;

use App\Models\Poster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosterAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_poster_and_custom_request_resources(): void
    {
        $admin = User::factory()->create(['email' => config('services.filament_admin.email')]);
        Poster::factory()->create();

        $this->actingAs($admin)->get('/admin/posters')->assertOk();
        $this->actingAs($admin)->get('/admin/custom-poster-requests')->assertOk();
    }
}
