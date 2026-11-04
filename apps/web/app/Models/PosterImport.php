<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosterImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_user_id', 'selection_mode', 'status', 'requested_nm_ids', 'random_count', 'only_new',
        'total_count', 'processed_count', 'created_count', 'updated_count', 'failed_count',
        'skipped_count', 'errors', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_nm_ids' => 'array',
            'only_new' => 'boolean',
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
