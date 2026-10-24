<?php

namespace Tests\Feature;

use App\Models\Generation;
use App\Models\User;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'original_name' => 'photo.png',
            'settings' => ['colors' => 12],
            'diagnostics' => ['segments' => 24],
        ]);

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/visitors')->assertOk();
        $this->actingAs($admin)->get("/admin/visitors/{$visitor->id}")->assertOk();
        $this->actingAs($admin)->get('/admin/generations')->assertOk();
        $this->actingAs($admin)->get("/admin/generations/{$generation->id}")->assertOk();
    }

    public function test_other_users_cannot_open_the_panel(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $otherUser = User::factory()->create(['email' => 'other@example.com']);

        $this->actingAs($otherUser)->get('/admin')->assertForbidden();
    }
}
