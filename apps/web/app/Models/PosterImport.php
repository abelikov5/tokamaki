<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosterImport extends Model
{
    use HasFactory;

    protected $attributes = [
        'source' => 'manual',
        'generate_ai' => true,
    ];

    protected $fillable = [
        'source_user_id', 'source', 'selection_mode', 'status', 'requested_nm_ids', 'random_count', 'only_new',
        'image_limit', 'generate_ai',
        'total_count', 'processed_count', 'created_count', 'updated_count', 'failed_count',
        'skipped_count', 'errors', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_nm_ids' => 'array',
            'only_new' => 'boolean',
            'image_limit' => 'integer',
            'generate_ai' => 'boolean',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function indexNowSubmissions(): HasMany
    {
        return $this->hasMany(IndexNowSubmission::class);
    }
}
