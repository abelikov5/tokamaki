<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosterVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'poster_id', 'source_id', 'nm_id', 'vendor_code', 'size_code', 'width_cm',
        'height_cm', 'price', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(Poster::class);
    }

    public function getWildberriesUrlAttribute(): string
    {
        return 'https://www.wildberries.ru/catalog/'.$this->nm_id.'/detail.aspx';
    }
}
