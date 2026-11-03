<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PosterImage extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(function (self $image): void {
            $oldPath = $image->getOriginal('path');

            if ($image->isDirty('path') && filled($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        });

        static::deleting(function (self $image): void {
            if (filled($image->path)) {
                Storage::disk('public')->delete($image->path);
            }
        });
    }

    protected $fillable = [
        'poster_id', 'source_id', 'role', 'sort_order', 'remote_url', 'path',
        'mime_type', 'alt_text', 'checksum',
    ];

    public function poster(): BelongsTo
    {
        return $this->belongsTo(Poster::class);
    }
}
