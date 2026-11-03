<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PosterCategory extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $category): void {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    protected $fillable = [
        'name', 'slug', 'type', 'is_active', 'sort_order', 'seo_title', 'seo_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function posters(): BelongsToMany
    {
        return $this->belongsToMany(Poster::class, 'poster_category_poster');
    }

    public function primaryPosters(): HasMany
    {
        return $this->hasMany(Poster::class, 'category_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function resolveCanonical(string $name, string $type = 'theme'): self
    {
        $name = Str::of($name)->squish()->toString();
        $slug = Str::slug($name);

        return self::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'type' => in_array($type, array_keys(self::typeOptions()), true) ? $type : 'theme',
                'is_active' => true,
                'sort_order' => ((int) self::max('sort_order')) + 10,
            ],
        );
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            'theme' => 'Сюжет',
            'style' => 'Стиль',
            'room' => 'Комната',
            'collection' => 'Коллекция',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
