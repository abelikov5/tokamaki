<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomPosterRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'status', 'poster_id', 'name', 'phone', 'email', 'city', 'street', 'size_code',
        'amount', 'original_name', 'photo_path', 'comment', 'ip_address', 'user_agent',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(Poster::class);
    }

    public function getAdminPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? route('admin.custom-poster-requests.photo', $this) : $this->poster?->primary_image_url;
    }
}
