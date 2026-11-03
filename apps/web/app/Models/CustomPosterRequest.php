<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomPosterRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'status', 'name', 'phone', 'email', 'size_code', 'original_name', 'photo_path',
        'comment', 'ip_address', 'user_agent',
    ];

    public function getAdminPhotoUrlAttribute(): string
    {
        return route('admin.custom-poster-requests.photo', $this);
    }
}
