<?php

namespace Tests\Feature;

use App\Filament\Resources\PosterCategories\Pages\ListPosterCategories;
use App\Filament\Resources\Posters\Pages\EditPoster;
use App\Filament\Resources\Posters\Pages\ListPosters;
use App\Models\Poster;
use App\Models\PosterImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
        $this->actingAs($admin)->get('/admin/poster-categories')->assertOk();
        $this->actingAs($admin)->get('/admin/custom-poster-requests')->assertOk();

        Livewire::actingAs($admin)->test(ListPosterCategories::class)->assertOk();
    }

    public function test_admin_can_bulk_delete_selected_posters(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $posters = Poster::factory()->count(3)->create();

        $this->actingAs($admin);

        Livewire::test(ListPosters::class)
            ->assertTableBulkActionExists('generateAiContent')
            ->assertTableBulkActionExists('delete')
            ->callTableBulkAction('delete', $posters->take(2));

        $this->assertDatabaseCount('posters', 1);
        $this->assertDatabaseHas('posters', ['id' => $posters->last()->id]);
    }

    public function test_admin_can_see_images_and_publish_a_draft(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $poster = Poster::factory()->create(['status' => 'draft']);
        PosterImage::create([
            'poster_id' => $poster->id,
            'source_id' => 123,
            'role' => 'main',
            'sort_order' => 0,
            'remote_url' => 'https://images.example.test/poster.jpg',
            'alt_text' => $poster->title,
        ]);

        $this->actingAs($admin);

        Livewire::test(EditPoster::class, ['record' => $poster->getRouteKey()])
            ->assertSee('https://images.example.test/poster.jpg', false)
            ->assertFormFieldExists('images')
            ->assertActionExists('publish')
            ->assertActionExists('generateAiContent')
            ->callAction('publish');

        $this->assertDatabaseHas('posters', ['id' => $poster->id, 'status' => 'published']);
        $this->assertNotNull($poster->fresh()->published_at);
    }

    public function test_deleting_poster_image_removes_its_local_file(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('posters/manual/example.jpg', 'image-data');
        $poster = Poster::factory()->create();
        $image = PosterImage::create([
            'poster_id' => $poster->id,
            'role' => 'slide',
            'sort_order' => 0,
            'path' => 'posters/manual/example.jpg',
        ]);

        $image->delete();

        Storage::disk('public')->assertMissing('posters/manual/example.jpg');
        $this->assertDatabaseMissing('poster_images', ['id' => $image->id]);
    }

    public function test_search_keywords_are_saved_without_quotes(): void
    {
        $poster = Poster::factory()->create();

        $poster->update([
            'search_keywords' => ['«акварельная', 'морская', 'ракушка»', '"интерьер"'],
        ]);

        $this->assertSame(
            ['акварельная', 'морская', 'ракушка', 'интерьер'],
            $poster->fresh()->search_keywords,
        );
    }
}
