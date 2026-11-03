<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosterImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'poster_id', 'source_id', 'role', 'sort_order', 'remote_url', 'path',
        'mime_type', 'alt_text', 'checksum',
    ];

    public function poster(): BelongsTo
    {
        return $this->belongsTo(Poster::class);
    }
}
