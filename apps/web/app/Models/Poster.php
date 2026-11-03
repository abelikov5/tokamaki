<?php

namespace App\Models;

use Database\Factories\PosterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Poster extends Model
{
    /** @use HasFactory<PosterFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $poster): void {
            $poster->search_keywords = self::normalizeSearchKeywords($poster->search_keywords ?? []);

            if ($poster->isDirty('category_id') && $poster->category_id !== null) {
                $poster->category = PosterCategory::find($poster->category_id)?->name ?? $poster->category;
            }

            if ($poster->status === 'published' && $poster->published_at === null) {
                $poster->published_at = now();
            }

            if ($poster->status !== 'published') {
                $poster->published_at = null;
            }
        });
    }

    protected $fillable = [
        'source_id', 'source_group_id', 'source_key', 'source_user_id', 'nm_id', 'vendor_code', 'slug', 'status', 'title',
        'short_description', 'description', 'seo_title', 'seo_description', 'category', 'category_id',
        'style', 'room', 'primary_color', 'orientation', 'search_keywords', 'source_payload',
        'content_hash', 'source_updated_at', 'imported_at', 'published_at', 'ai_generation_status',
        'ai_model', 'ai_prompt_version', 'ai_input_hash', 'ai_generation_error', 'ai_generated_at',
        'ai_response',
    ];

    protected function casts(): array
    {
        return [
            'search_keywords' => 'array',
            'source_payload' => 'array',
            'source_updated_at' => 'datetime',
            'imported_at' => 'datetime',
            'published_at' => 'datetime',
            'ai_generated_at' => 'datetime',
            'ai_response' => 'array',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(PosterImage::class)->orderBy('sort_order');
    }

    public function posterCategory(): BelongsTo
    {
        return $this->belongsTo(PosterCategory::class, 'category_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(PosterVariant::class)->orderBy('width_cm')->orderBy('height_cm');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function getPrimaryImageUrlAttribute(): ?string
    {
        $image = $this->images->firstWhere('role', 'main') ?? $this->images->first();

        if (! $image) {
            return null;
        }

        return $image->path ? Storage::disk('public')->url($image->path) : $image->remote_url;
    }

    public function getWildberriesUrlAttribute(): string
    {
        return 'https://www.wildberries.ru/catalog/'.$this->nm_id.'/detail.aspx';
    }

    /** @param  array<int, mixed>  $keywords */
    public static function normalizeSearchKeywords(array $keywords): array
    {
        return collect($keywords)
            ->map(fn (mixed $keyword): string => Str::of((string) $keyword)
                ->replaceMatches('/[«»"„“”]/u', '')
                ->replaceMatches('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '')
                ->squish()
                ->toString())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
