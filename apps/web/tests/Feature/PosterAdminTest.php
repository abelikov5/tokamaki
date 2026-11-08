<?php

namespace Tests\Feature;

use App\Filament\Resources\PosterCategories\Pages\ListPosterCategories;
use App\Filament\Resources\PosterImports\Pages\ListPosterImports;
use App\Filament\Resources\Posters\Pages\EditPoster;
use App\Filament\Resources\Posters\Pages\ListPosters;
use App\Models\IndexNowSubmission;
use App\Models\Poster;
use App\Models\PosterImage;
use App\Models\PosterImport;
use App\Models\PosterImportSchedule;
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
        $this->actingAs($admin)->get('/admin/poster-imports')->assertOk();
        $this->actingAs($admin)->get('/admin/custom-poster-requests')->assertOk();

        Livewire::actingAs($admin)->test(ListPosterCategories::class)->assertOk();
        Livewire::actingAs($admin)->test(ListPosters::class)
            ->assertOk()
            ->assertTableColumnExists('is_admin_recommended')
            ->assertTableColumnExists('view_count');

        PosterImport::create([
            'source_user_id' => 1,
            'selection_mode' => 'random',
            'random_count' => 100,
            'only_new' => true,
            'status' => 'running',
            'total_count' => 100,
            'processed_count' => 17,
        ]);
        Livewire::actingAs($admin)->test(ListPosterImports::class)->assertOk()->assertSee('17 / 100');
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

    public function test_admin_sees_indexnow_results_on_the_imports_page(): void
    {
        $admin = User::factory()->create(['email' => config('services.filament_admin.email')]);
        $posterImport = PosterImport::create([
            'source_user_id' => 1,
            'status' => 'completed',
            'generate_ai' => true,
        ]);
        IndexNowSubmission::create([
            'poster_import_id' => $posterImport->id,
            'source' => 'import',
            'status' => 'completed',
            'urls_count' => 4,
            'submitted_at' => now(),
        ]);
        IndexNowSubmission::create([
            'source' => 'full',
            'status' => 'completed',
            'urls_count' => 25,
            'submitted_at' => now(),
        ]);

        Livewire::actingAs($admin)->test(ListPosterImports::class)
            ->assertOk()
            ->assertTableColumnExists('indexnow_status')
            ->assertSee('1 картин · 4 URL')
            ->assertSee('Последняя полная отправка в IndexNow: 25 URL');
    }

    public function test_admin_can_configure_scheduled_poster_import(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);

        Livewire::actingAs($admin)->test(ListPosterImports::class)
            ->assertActionExists('configureSchedule')
            ->callAction('configureSchedule', [
                'enabled' => true,
                'source_user_id' => 2,
                'scheduled_time' => '04:15',
                'random_count' => 25,
                'image_limit' => 4,
                'only_new' => true,
                'generate_ai' => true,
            ])
            ->assertNotified();

        $this->assertDatabaseHas('poster_import_schedules', [
            'id' => PosterImportSchedule::current()->id,
            'enabled' => true,
            'source_user_id' => 2,
            'scheduled_time' => '04:15',
            'random_count' => 25,
            'image_limit' => 4,
            'only_new' => true,
            'generate_ai' => true,
        ]);
    }

    public function test_admin_can_toggle_recommendation_from_poster_list(): void
    {
        config()->set('services.filament_admin.email', 'admin@example.com');
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $poster = Poster::factory()->create(['is_admin_recommended' => false]);

        $this->actingAs($admin);

        $component = Livewire::test(ListPosters::class);

        $result = $component->instance()->updateTableColumnState(
            'is_admin_recommended',
            $component->instance()->getTableRecordKey($poster),
            true,
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('posters', [
            'id' => $poster->id,
            'is_admin_recommended' => true,
        ]);
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
            ->assertFormFieldExists('vendor_code')
            ->assertFormFieldExists('is_admin_recommended')
            ->assertFormFieldExists('view_count')
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
