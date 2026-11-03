<?php

namespace Tests\Feature;

use App\Filament\Resources\Posters\Pages\ListPosters;
use App\Models\Poster;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_admin_can_bulk_delete_selected_posters(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $posters = Poster::factory()->count(3)->create();

        $this->actingAs($admin);

        Livewire::test(ListPosters::class)
            ->assertTableBulkActionExists('delete')
            ->callTableBulkAction('delete', $posters->take(2));

        $this->assertDatabaseCount('posters', 1);
        $this->assertDatabaseHas('posters', ['id' => $posters->last()->id]);
    }
}
