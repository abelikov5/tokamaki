<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_configured_administrator_can_open_the_panel(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $visitor = Visitor::factory()->create();
        $generation = Generation::create([
            'visitor_id' => $visitor->id,
            'status' => 'completed',
            'result_id' => 'result-123',
            'original_name' => 'photo.png',
            'settings' => ['colors' => 12],
            'diagnostics' => ['segments' => 24],
        ]);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/visitors')->assertOk();
        $this->actingAs($admin)->get("/admin/visitors/{$visitor->id}")->assertOk();
        $previewUrl = route('admin.generations.preview', [
            'generation' => $generation,
            'v' => $generation->result_id,
        ]);
        $this->actingAs($admin)->get('/admin/generations')->assertOk()->assertSee($previewUrl, false);
        $this->actingAs($admin)->get("/admin/generations/{$generation->id}")->assertOk()->assertSee($previewUrl, false);
    }

    public function test_other_users_cannot_open_the_panel(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $otherUser = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($otherUser)->get('/admin')->assertForbidden();
    }

    public function test_only_configured_administrator_can_load_a_generation_preview(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        config()->set('services.generator.url', 'http://generator.test');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $otherUser = User::factory()->create(['email' => 'other@example.com']);
        $generation = Generation::create([
            'status' => 'completed',
            'result_id' => 'result-123',
            'original_name' => 'photo.png',
            'settings' => ['colors' => 12],
        ]);

        Http::fake([
            'http://generator.test/api/v1/results/result-123/preview.png' => Http::response('png-data', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $this->get(route('admin.generations.preview', $generation))->assertForbidden();
        $this->actingAs($otherUser)
            ->get(route('admin.generations.preview', $generation))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.generations.preview', $generation))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertContent('png-data');

        Http::assertSentCount(1);
    }
}
