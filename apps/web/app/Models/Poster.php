<?php

namespace App\Models;

use Database\Factories\PosterFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Poster extends Model
{
    /** @use HasFactory<PosterFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (self $poster): void {
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
        'short_description', 'description', 'seo_title', 'seo_description', 'category',
        'style', 'room', 'primary_color', 'orientation', 'search_keywords', 'source_payload',
        'content_hash', 'source_updated_at', 'imported_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'search_keywords' => 'array',
            'source_payload' => 'array',
            'source_updated_at' => 'datetime',
            'imported_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(PosterImage::class)->orderBy('sort_order');
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
}
